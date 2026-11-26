<?php

declare(strict_types=1);

namespace NFSeNacional\Make;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use NFSeNacional\Exception\ValidationException;
use NFSeNacional\Validator\DpsValidator;

/**
 * Gera o XML da DPS (Declaração de Prestação de Serviço) da NFS-e Nacional.
 *
 * Estrutura conforme o schema oficial DPS_v1.01.xsd (NT 009 v1.01,
 * esquemas de produção restrita de 24/11/2026):
 *
 *   <DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">
 *     <infDPS Id="DPS...">
 *       tpAmb, dhEmi, verAplic, serie, nDPS, dCompet, tpEmit, finNFSe, cLocEmi,
 *       prest, toma, indDest, serv, valores
 *     </infDPS>
 *   </DPS>
 *
 * A assinatura XMLDSig é adicionada depois, em Tools::enviarDps().
 *
 * Não cobre (ainda): grupo IBSCBS, notas de ajuste, comércio exterior,
 * condomínio, locação e intermediário. São campos opcionais no schema.
 *
 * @package NFSeNacional\Make
 */
class Make
{
    /** Versão do layout (atributo versao da DPS). */
    public const VERSAO_LAYOUT = '1.01';

    /** Namespace oficial do Portal Nacional. */
    public const NAMESPACE = 'http://www.sped.fazenda.gov.br/nfse';

    private const FUSO = 'America/Sao_Paulo';

    private DpsValidator $validator;

    /** @var array{numero: string, serie: string} */
    private array $identificacao = ['numero' => '', 'serie' => '1'];

    private ?string $competencia = null;
    private ?string $dhEmi = null;
    private int $tpAmb = 2; // 1=Produção, 2=Homologação
    private string $verAplic = '1.0.0';
    private int $tpEmit = 1; // 1=Prestador, 2=Tomador, 3=Intermediário
    private string $cLocEmi = '';

    private array $prestador = [];
    private array $tomador = [];
    private array $servico = [];

    private ?string $pTotTribSN = null;
    private ?string $infDpsId = null;

    public function __construct(?DpsValidator $validator = null)
    {
        $this->validator = $validator ?? new DpsValidator();
    }

    /**
     * @param array{numero?: string|int, serie?: string|int} $identificacao
     */
    public function setIdentificacao(array $identificacao): self
    {
        $this->identificacao = [
            'numero' => (string) ($identificacao['numero'] ?? $this->identificacao['numero']),
            'serie' => (string) ($identificacao['serie'] ?? $this->identificacao['serie']),
        ];

        return $this;
    }

    /**
     * Data de competência (YYYY-MM-DD).
     */
    public function setCompetencia(string $competencia): self
    {
        $this->competencia = $competencia;

        return $this;
    }

    /**
     * Data/hora de emissão (YYYY-MM-DDTHH:mm:ss-03:00). Se omitida, usa o horário atual de Brasília.
     */
    public function setDhEmi(string $dhEmi): self
    {
        $this->dhEmi = $dhEmi;

        return $this;
    }

    /**
     * Tipo de ambiente: 1=Produção, 2=Homologação (Produção Restrita).
     */
    public function setTpAmb(int $tpAmb): self
    {
        $this->tpAmb = $tpAmb;

        return $this;
    }

    public function setVerAplic(string $verAplic): self
    {
        $this->verAplic = $verAplic;

        return $this;
    }

    /**
     * Tipo de emitente: 1=Prestador, 2=Tomador, 3=Intermediário.
     */
    public function setTpEmit(int $tpEmit): self
    {
        $this->tpEmit = $tpEmit;

        return $this;
    }

    /**
     * Código IBGE (7 dígitos) do município de emissão.
     */
    public function setCLocEmi(string $cLocEmi): self
    {
        $this->cLocEmi = preg_replace('/\D/', '', $cLocEmi);

        return $this;
    }

    /**
     * Dados do prestador. Chaves: cnpj, im, razaoSocial, opSimpNac (1|2|3),
     * regEspTrib, regApTribSN, email, telefone, endereco, numero, complemento,
     * bairro, codigoMunicipio, cep.
     */
    public function setPrestador(array $data): self
    {
        $this->prestador = $this->validator->validatePrestador($data);

        return $this;
    }

    /**
     * Dados do tomador. Chaves: cpfCnpj, razaoSocial, email, telefone, endereco,
     * numero, complemento, bairro, codigoMunicipio, cep.
     */
    public function setTomador(array $data): self
    {
        $this->tomador = $this->validator->validateTomador($data);

        return $this;
    }

    /**
     * Dados do serviço. Chaves: cTribNac (6 dígitos, obrigatório), cTribMun (3 dígitos),
     * cNBS (9 dígitos), discriminacao, valorServicos, issRetido (1|2), tribISSQN (1 a 4),
     * cLocPrestacao (IBGE; padrão = cLocEmi), piscofins (opcional).
     */
    public function setServico(array $data): self
    {
        $this->servico = $this->validator->validateServico(array_merge($this->servico, $data));

        return $this;
    }

    /**
     * Alias de setServico() para compatibilidade.
     */
    public function addServico(array $data): self
    {
        return $this->setServico($data);
    }

    /**
     * Atualiza apenas o valor do serviço.
     * Aceita valorServicos (camelCase) ou ValorServicos (PascalCase).
     */
    public function setValores(array $valores): self
    {
        $valor = $valores['valorServicos'] ?? $valores['ValorServicos'] ?? $valores['valor_servicos'] ?? null;
        if ($valor !== null) {
            $this->servico['valorServicos'] = (float) $valor;
        }

        return $this;
    }

    /**
     * Percentual total de tributos do Simples Nacional (totTrib/pTotTribSN).
     * Se não informado, o grupo totTrib usa indTotTrib=0.
     */
    public function setPTotTribSN(?string $pTotTribSN): self
    {
        $this->pTotTribSN = $pTotTribSN;

        return $this;
    }

    /**
     * Gera o XML da DPS validado contra o schema oficial.
     */
    public function getDPSXML(): string
    {
        $this->assertRequiredData();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $dps = $dom->createElementNS(self::NAMESPACE, 'DPS');
        $dps->setAttribute('versao', self::VERSAO_LAYOUT);
        $dom->appendChild($dps);

        $this->infDpsId = $this->gerarIdDps();
        $inf = $this->el($dom, $dps, 'infDPS');
        $inf->setAttribute('Id', $this->infDpsId);

        $this->el($dom, $inf, 'tpAmb', (string) $this->tpAmb);
        $this->el($dom, $inf, 'dhEmi', $this->dhEmi ?? $this->agoraBrasilia());
        $this->el($dom, $inf, 'verAplic', $this->verAplic);
        $this->el($dom, $inf, 'serie', $this->identificacao['serie']);
        $this->el($dom, $inf, 'nDPS', ltrim($this->identificacao['numero'], '0'));
        $this->el($dom, $inf, 'dCompet', $this->competencia ?? $this->hojeBrasilia());
        $this->el($dom, $inf, 'tpEmit', (string) $this->tpEmit);
        $this->el($dom, $inf, 'finNFSe', '0'); // 0 = NFS-e regular
        $this->el($dom, $inf, 'cLocEmi', $this->cLocEmi);

        $this->montarPrestador($dom, $inf);
        $this->montarTomador($dom, $inf);
        $this->el($dom, $inf, 'indDest', '0'); // 0 = destinatário é o próprio tomador
        $this->montarServico($dom, $inf);
        $this->montarValores($dom, $inf);

        $xml = $dom->saveXML();
        if ($xml === false || $xml === '') {
            throw new \RuntimeException('Falha ao gerar XML da DPS');
        }

        return $xml;
    }

    /**
     * Alias de getDPSXML(), mantido para compatibilidade com versões anteriores.
     */
    public function getXML(): string
    {
        return $this->getDPSXML();
    }

    /**
     * Id da infDPS gerado na última chamada de getDPSXML().
     */
    public function getInfDpsId(): ?string
    {
        return $this->infDpsId;
    }

    /**
     * Id da DPS (45 caracteres): DPS + cLocEmi(7) + tipo(1) + inscrição(14) + série(5) + nDPS(15).
     */
    private function gerarIdDps(): string
    {
        $cnpj = strtoupper($this->prestador['cnpj']);
        $serie = str_pad($this->identificacao['serie'], 5, '0', STR_PAD_LEFT);
        $numero = str_pad($this->identificacao['numero'], 15, '0', STR_PAD_LEFT);

        return 'DPS' . $this->cLocEmi . '2' . $cnpj . $serie . $numero;
    }

    private function montarPrestador(DOMDocument $dom, DOMElement $inf): void
    {
        $prest = $this->el($dom, $inf, 'prest');
        $this->el($dom, $prest, 'CNPJ', $this->prestador['cnpj']);
        $this->el($dom, $prest, 'IM', $this->prestador['im']);
        $this->el($dom, $prest, 'xNome', $this->prestador['razaoSocial']);

        if ($this->temEndereco($this->prestador)) {
            $this->montarEndereco($dom, $prest, $this->prestador);
        }

        if (!empty($this->prestador['telefone'])) {
            $this->el($dom, $prest, 'fone', $this->prestador['telefone']);
        }
        if (!empty($this->prestador['email'])) {
            $this->el($dom, $prest, 'email', $this->prestador['email']);
        }

        $regTrib = $this->el($dom, $prest, 'regTrib');
        $this->el($dom, $regTrib, 'opSimpNac', (string) $this->prestador['opSimpNac']);
        if ($this->prestador['regApTribSN'] !== null) {
            $this->el($dom, $regTrib, 'regApTribSN', (string) $this->prestador['regApTribSN']);
        }
        $this->el($dom, $regTrib, 'regEspTrib', (string) $this->prestador['regEspTrib']);
    }

    private function montarTomador(DOMDocument $dom, DOMElement $inf): void
    {
        $toma = $this->el($dom, $inf, 'toma');
        $doc = $this->tomador['cpfCnpj'];

        if (strlen($doc) === 11) {
            $this->el($dom, $toma, 'CPF', $doc);
        } else {
            $this->el($dom, $toma, 'CNPJ', strtoupper($doc));
        }

        $this->el($dom, $toma, 'xNome', $this->tomador['razaoSocial']);

        if ($this->temEndereco($this->tomador)) {
            $this->montarEndereco($dom, $toma, $this->tomador);
        }

        if (!empty($this->tomador['telefone'])) {
            $this->el($dom, $toma, 'fone', $this->tomador['telefone']);
        }
        if (!empty($this->tomador['email'])) {
            $this->el($dom, $toma, 'email', $this->tomador['email']);
        }
    }

    /**
     * Grupo TCEndereco: endNac (cMun + CEP) seguido de xLgr, nro, xCpl (opcional) e xBairro.
     */
    private function montarEndereco(DOMDocument $dom, DOMElement $pai, array $dados): void
    {
        $end = $this->el($dom, $pai, 'end');
        $endNac = $this->el($dom, $end, 'endNac');
        $this->el($dom, $endNac, 'cMun', (string) $dados['codigoMunicipio']);
        $this->el($dom, $endNac, 'CEP', preg_replace('/\D/', '', (string) $dados['cep']));

        $this->el($dom, $end, 'xLgr', (string) $dados['endereco']);
        $this->el($dom, $end, 'nro', (string) $dados['numero']);
        if (!empty($dados['complemento'])) {
            $this->el($dom, $end, 'xCpl', (string) $dados['complemento']);
        }
        $this->el($dom, $end, 'xBairro', (string) $dados['bairro']);
    }

    private function montarServico(DOMDocument $dom, DOMElement $inf): void
    {
        $serv = $this->el($dom, $inf, 'serv');

        $locPrest = $this->el($dom, $serv, 'locPrest');
        $this->el($dom, $locPrest, 'cLocPrestacao', $this->servico['cLocPrestacao'] ?? $this->cLocEmi);

        $cServ = $this->el($dom, $serv, 'cServ');
        $this->el($dom, $cServ, 'cTribNac', $this->servico['cTribNac']);
        if (!empty($this->servico['cTribMun'])) {
            $this->el($dom, $cServ, 'cTribMun', $this->servico['cTribMun']);
        }
        if (!empty($this->servico['cNBS'])) {
            $this->el($dom, $cServ, 'cNBS', $this->servico['cNBS']);
        }
        $this->el($dom, $cServ, 'xDescServ', $this->servico['discriminacao']);
    }

    private function montarValores(DOMDocument $dom, DOMElement $inf): void
    {
        $valorServicos = $this->servico['valorServicos'];

        $valores = $this->el($dom, $inf, 'valores');
        $vServPrest = $this->el($dom, $valores, 'vServPrest');
        $this->el($dom, $vServPrest, 'vServ', $this->dec2($valorServicos));

        $trib = $this->el($dom, $valores, 'trib');

        $tribMun = $this->el($dom, $trib, 'tribMun');
        $this->el($dom, $tribMun, 'tribISSQN', (string) $this->servico['tribISSQN']);
        // tpRetISSQN: 1=não retido, 2=retido pelo tomador, 3=retido pelo intermediário
        $this->el($dom, $tribMun, 'tpRetISSQN', $this->servico['issRetido'] === 1 ? '2' : '1');

        if (!empty($this->servico['piscofins']) && is_array($this->servico['piscofins'])) {
            $this->montarPisCofins($dom, $trib, $this->servico['piscofins'], $valorServicos);
        }

        $totTrib = $this->el($dom, $trib, 'totTrib');
        if ($this->pTotTribSN !== null && $this->pTotTribSN !== '') {
            $this->el($dom, $totTrib, 'pTotTribSN', $this->pTotTribSN);
        } else {
            $this->el($dom, $totTrib, 'indTotTrib', '0');
        }
    }

    /**
     * Grupo tribFed/piscofins (NT 004/007). Só é gerado se o serviço trouxer 'piscofins'.
     */
    private function montarPisCofins(DOMDocument $dom, DOMElement $trib, array $pc, float $valorServicos): void
    {
        $tribFed = $this->el($dom, $trib, 'tribFed');
        $piscofins = $this->el($dom, $tribFed, 'piscofins');

        $vBC = (float) ($pc['vBCPisCofins'] ?? $valorServicos);
        $aliqPis = (float) ($pc['pAliqPis'] ?? 0);
        $aliqCofins = (float) ($pc['pAliqCofins'] ?? 0);

        $this->el($dom, $piscofins, 'CST', (string) ($pc['CST'] ?? '01'));
        $this->el($dom, $piscofins, 'vBCPisCofins', $this->dec2($vBC));
        $this->el($dom, $piscofins, 'pAliqPis', $this->dec2($aliqPis));
        $this->el($dom, $piscofins, 'pAliqCofins', $this->dec2($aliqCofins));
        $this->el($dom, $piscofins, 'vPis', $this->dec2($vBC * $aliqPis / 100));
        $this->el($dom, $piscofins, 'vCofins', $this->dec2($vBC * $aliqCofins / 100));
        $this->el($dom, $piscofins, 'tpRetPisCofins', (string) ($pc['tpRetPisCofins'] ?? '1'));
    }

    private function assertRequiredData(): void
    {
        $erros = [];

        if (!preg_match('/^[1-9][0-9]{0,14}$/', $this->identificacao['numero'])) {
            $erros[] = 'Número da DPS é obrigatório (1 a 15 dígitos, sem zeros à esquerda)';
        }
        if (!preg_match('/^[0-9]{1,4}$/', $this->identificacao['serie'])) {
            $erros[] = 'Série da DPS deve ter de 1 a 4 dígitos numéricos';
        }
        if (!in_array($this->tpAmb, [1, 2], true)) {
            $erros[] = 'tpAmb deve ser 1 (produção) ou 2 (homologação)';
        }
        if (!in_array($this->tpEmit, [1, 2, 3], true)) {
            $erros[] = 'tpEmit deve ser 1, 2 ou 3';
        }
        if (!preg_match('/^[0-9]{7}$/', $this->cLocEmi)) {
            $erros[] = 'Código do município de emissão (cLocEmi) é obrigatório (7 dígitos IBGE)';
        }
        if ($this->competencia !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->competencia)) {
            $erros[] = 'Competência deve estar no formato YYYY-MM-DD';
        }
        if (empty($this->prestador)) {
            $erros[] = 'Dados do prestador não informados';
        }
        if (empty($this->tomador)) {
            $erros[] = 'Dados do tomador não informados';
        }
        if (empty($this->servico['cTribNac']) || empty($this->servico['discriminacao'])) {
            $erros[] = 'Dados do serviço incompletos (cTribNac e discriminacao são obrigatórios)';
        }
        if (empty($this->servico['valorServicos'])) {
            $erros[] = 'Valor do serviço (valorServicos) é obrigatório';
        }

        if (!empty($erros)) {
            throw new ValidationException('Erros de validação: ' . implode('; ', $erros));
        }
    }

    /**
     * Cria um elemento na namespace da NFS-e Nacional, com texto opcional.
     * Todos os elementos precisam usar a mesma namespace para não gerar xmlns="" extra.
     */
    private function el(DOMDocument $dom, DOMElement $pai, string $nome, ?string $texto = null): DOMElement
    {
        $elemento = $dom->createElementNS(self::NAMESPACE, $nome);
        if ($texto !== null) {
            $elemento->appendChild($dom->createTextNode($texto));
        }
        $pai->appendChild($elemento);

        return $elemento;
    }

    private function temEndereco(array $dados): bool
    {
        return !empty($dados['codigoMunicipio']) && !empty($dados['cep']) && !empty($dados['endereco']);
    }

    private function dec2(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    private function agoraBrasilia(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::FUSO)))->format('Y-m-d\TH:i:sP');
    }

    private function hojeBrasilia(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::FUSO)))->format('Y-m-d');
    }
}
