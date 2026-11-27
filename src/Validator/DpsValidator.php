<?php

declare(strict_types=1);

namespace NFSeNacional\Validator;

use NFSeNacional\Exception\ValidationException;

/**
 * Validação e normalização dos dados da DPS antes da geração do XML.
 *
 * As regras seguem o schema oficial DPS_v1.01.xsd (NT 009 v1.01).
 * Campos opcionais no XSD continuam opcionais aqui; o que é obrigatório
 * para o projeto (ex.: razão social do tomador) é validado explicitamente.
 */
class DpsValidator
{
    /** Regimes de Simples Nacional aceitos (TSOpSimpNac). */
    private const OP_SIMP_NAC = [1, 2, 3];

    /** Regime de apuração do Simples Nacional aceito (TSRegimeApuracaoSimpNac). */
    private const REG_AP_TRIB_SN = [1, 2, 3];

    /** Regime especial de tributação aceito (TSRegEspTrib). */
    private const REG_ESP_TRIB = [0, 1, 2, 3, 4, 5, 6, 9];

    /** Indicador de ISSQN aceito nesta versão (TSTribISSQN). */
    private const TRIB_ISSQN = [1, 2, 3, 4];

    /**
     * Valida e normaliza o prestador.
     *
     * @return array dados normalizados (cnpj com 14 caracteres alfanuméricos maiúsculos)
     */
    public function validatePrestador(array $prestador): array
    {
        $errors = [];

        $cnpj = $this->normalizarCnpj($prestador['cnpj'] ?? '');
        if ($cnpj === null) {
            $errors[] = 'CNPJ do prestador inválido (deve ter 14 caracteres: números ou letras maiúsculas)';
        }

        $im = trim((string) ($prestador['im'] ?? $prestador['inscricaoMunicipal'] ?? ''));
        if ($im === '') {
            $errors[] = 'Inscrição municipal do prestador é obrigatória';
        }

        // A razão social só é enviada na DPS quando o emitente não é o prestador (tpEmit diferente de 1);
        // por isso a exigência é feita em Make::getDPSXML(), que conhece o tpEmit.
        $razaoSocial = trim((string) ($prestador['razaoSocial'] ?? ''));

        $opSimpNac = $this->inteiroEm($prestador['opSimpNac'] ?? null, self::OP_SIMP_NAC);
        if ($opSimpNac === null) {
            $errors[] = 'opSimpNac do prestador é obrigatório (1, 2 ou 3)';
        }

        $regEspTrib = $this->inteiroEm($prestador['regEspTrib'] ?? 0, self::REG_ESP_TRIB);
        if ($regEspTrib === null) {
            $errors[] = 'regEspTrib do prestador inválido';
        }

        $regApTribSN = null;
        if (isset($prestador['regApTribSN']) && $prestador['regApTribSN'] !== '') {
            $regApTribSN = $this->inteiroEm($prestador['regApTribSN'], self::REG_AP_TRIB_SN);
            if ($regApTribSN === null) {
                $errors[] = 'regApTribSN do prestador inválido (1, 2 ou 3)';
            }
        }

        $email = $this->emailOpcional($prestador['email'] ?? '', 'prestador', $errors);
        $telefone = $this->telefoneOpcional($prestador['telefone'] ?? $prestador['fone'] ?? '', 'prestador', $errors);
        $this->validarEnderecoOpcional($prestador, 'prestador', $errors);

        $this->lancarSeHouver($errors, 'prestador');

        return array_merge($prestador, [
            'cnpj' => $cnpj,
            'im' => $im,
            'razaoSocial' => mb_substr($razaoSocial, 0, 150),
            'email' => $email,
            'telefone' => $telefone,
            'opSimpNac' => $opSimpNac,
            'regApTribSN' => $regApTribSN,
            'regEspTrib' => $regEspTrib,
        ]);
    }

    /**
     * Valida e normaliza o tomador.
     */
    public function validateTomador(array $tomador): array
    {
        $errors = [];

        $documento = $this->normalizarDocumentoTomador($tomador['cpfCnpj'] ?? '');
        if ($documento === null) {
            $errors[] = 'Documento do tomador deve ser CPF (11 dígitos) ou CNPJ (14 caracteres)';
        }

        $razaoSocial = trim((string) ($tomador['razaoSocial'] ?? ''));
        if ($razaoSocial === '') {
            $errors[] = 'Razão social/nome do tomador é obrigatório';
        }

        $email = $this->emailOpcional($tomador['email'] ?? '', 'tomador', $errors);
        $telefone = $this->telefoneOpcional($tomador['telefone'] ?? $tomador['fone'] ?? '', 'tomador', $errors);
        $this->validarEnderecoOpcional($tomador, 'tomador', $errors);

        $this->lancarSeHouver($errors, 'tomador');

        return array_merge($tomador, [
            'cpfCnpj' => $documento,
            'razaoSocial' => mb_substr($razaoSocial, 0, 150),
            'email' => $email,
            'telefone' => $telefone,
        ]);
    }

    /**
     * Valida e normaliza os dados do serviço.
     */
    public function validateServico(array $servico): array
    {
        $errors = [];

        $discriminacao = trim((string) ($servico['discriminacao'] ?? $servico['descricao'] ?? ''));
        if ($discriminacao === '') {
            $errors[] = 'Discriminação do serviço é obrigatória';
        }

        $cTribNac = preg_replace('/\D/', '', (string) ($servico['cTribNac'] ?? ''));
        if (strlen($cTribNac) !== 6) {
            $errors[] = 'cTribNac é obrigatório e deve ter 6 dígitos (código de tributação nacional)';
        }

        $cTribMun = isset($servico['cTribMun']) && $servico['cTribMun'] !== ''
            ? (string) $servico['cTribMun']
            : null;
        if ($cTribMun !== null && !preg_match('/^[0-9]{3}$/', $cTribMun)) {
            $errors[] = 'cTribMun deve ter 3 dígitos quando informado';
        }

        $cNBS = isset($servico['cNBS']) && $servico['cNBS'] !== '' ? (string) $servico['cNBS'] : null;
        if ($cNBS !== null && !preg_match('/^[0-9]{9}$/', $cNBS)) {
            $errors[] = 'cNBS deve ter 9 dígitos quando informado';
        }

        $valorServicos = (float) ($servico['valorServicos'] ?? $servico['valor'] ?? 0);
        if ($valorServicos <= 0) {
            $errors[] = 'Valor dos serviços deve ser maior que zero';
        }

        $issRetido = (int) ($servico['issRetido'] ?? 2);
        if (!in_array($issRetido, [1, 2], true)) {
            $errors[] = 'issRetido deve ser 1 (sim) ou 2 (não)';
        }

        $tribISSQN = (int) ($servico['tribISSQN'] ?? 1);
        if (!in_array($tribISSQN, self::TRIB_ISSQN, true)) {
            $errors[] = 'tribISSQN inválido (1 a 4)';
        }

        $cLocPrestacao = isset($servico['cLocPrestacao']) && $servico['cLocPrestacao'] !== ''
            ? preg_replace('/\D/', '', (string) $servico['cLocPrestacao'])
            : null;
        if ($cLocPrestacao !== null && strlen($cLocPrestacao) !== 7) {
            $errors[] = 'cLocPrestacao deve ter 7 dígitos (código IBGE)';
        }

        // Alíquota do ISS (pAliq, TSDec1V2): de 0 a 9,99 com até 2 casas decimais.
        // Aceita 'pAliq' (nome do XSD) ou 'aliquota' (nome usado pelo ERP). Opcional.
        $pAliq = null;
        $aliquotaInformada = $servico['pAliq'] ?? $servico['aliquota'] ?? null;
        if ($aliquotaInformada !== null && $aliquotaInformada !== '') {
            $aliquotaForaDaFaixa = !is_numeric($aliquotaInformada)
                || (float) $aliquotaInformada < 0
                || (float) $aliquotaInformada > 9.99;
            if ($aliquotaForaDaFaixa) {
                $errors[] = 'Alíquota do ISS (pAliq) deve ser numérica, entre 0 e 9,99';
            } else {
                $pAliq = number_format(round((float) $aliquotaInformada, 2), 2, '.', '');
            }
        }
        unset($servico['pAliq'], $servico['aliquota']);

        $this->lancarSeHouver($errors, 'serviço');

        return array_merge($servico, [
            'discriminacao' => mb_substr($discriminacao, 0, 2000),
            'cTribNac' => $cTribNac,
            'cTribMun' => $cTribMun,
            'cNBS' => $cNBS,
            'valorServicos' => round($valorServicos, 2),
            'issRetido' => $issRetido,
            'tribISSQN' => $tribISSQN,
            'pAliq' => $pAliq,
            'cLocPrestacao' => $cLocPrestacao,
        ]);
    }

    /**
     * Normaliza CNPJ alfanumérico (NT 009). Aceita máscara e remove pontuação.
     *
     * @return string|null 14 caracteres [0-9A-Z] ou null se inválido
     */
    public function normalizarCnpj(string $valor): ?string
    {
        $cnpj = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $valor));

        return preg_match('/^[0-9A-Z]{14}$/', $cnpj) === 1 ? $cnpj : null;
    }

    /**
     * Documento do tomador: CPF (11 dígitos) ou CNPJ (14 caracteres).
     */
    private function normalizarDocumentoTomador(string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', $valor);
        if (strlen($digitos) === 11) {
            return $digitos;
        }

        return $this->normalizarCnpj($valor);
    }

    /**
     * Endereço é opcional. Se qualquer campo for informado, todos os obrigatórios
     * do grupo TCEndereco (cMun, CEP, xLgr, nro, xBairro) devem estar presentes.
     */
    private function validarEnderecoOpcional(array $dados, string $quem, array &$errors): void
    {
        $campos = ['codigoMunicipio', 'cep', 'endereco', 'numero', 'bairro'];
        $informados = array_filter($campos, fn(string $c) => isset($dados[$c]) && trim((string) $dados[$c]) !== '');

        if ($informados === []) {
            return;
        }

        foreach ($campos as $campo) {
            if (!in_array($campo, $informados, true)) {
                $errors[] = "Endereço do {$quem}: campo '{$campo}' é obrigatório quando há endereço";
            }
        }

        if (isset($dados['codigoMunicipio']) && !preg_match('/^[0-9]{7}$/', (string) $dados['codigoMunicipio'])) {
            $errors[] = "Código do município do {$quem} deve ter 7 dígitos (IBGE)";
        }

        if (isset($dados['cep']) && !preg_match('/^[0-9]{8}$/', preg_replace('/\D/', '', (string) $dados['cep']))) {
            $errors[] = "CEP do {$quem} deve ter 8 dígitos";
        }
    }

    private function emailOpcional(string $email, string $quem, array &$errors): ?string
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 80) {
            $errors[] = "E-mail do {$quem} inválido (máximo 80 caracteres)";
        }

        return $email;
    }

    private function telefoneOpcional(string $telefone, string $quem, array &$errors): ?string
    {
        $digitos = preg_replace('/\D/', '', $telefone);
        if ($digitos === '') {
            return null;
        }
        if (strlen($digitos) < 6 || strlen($digitos) > 20) {
            $errors[] = "Telefone do {$quem} deve ter entre 6 e 20 dígitos";
        }

        return $digitos;
    }

    /**
     * Retorna o inteiro se estiver na lista permitida, senão null.
     */
    private function inteiroEm(mixed $valor, array $permitidos): ?int
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        $inteiro = (int) $valor;

        return in_array($inteiro, $permitidos, true) ? $inteiro : null;
    }

    private function lancarSeHouver(array $errors, string $quem): void
    {
        if ($errors === []) {
            return;
        }

        throw new ValidationException(
            "Erros na validação do {$quem}:\n" . implode("\n", array_map(fn(string $e) => "  • {$e}", $errors))
        );
    }
}
