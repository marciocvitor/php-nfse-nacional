<?php

declare(strict_types=1);

namespace NFSeNacional\Validator;

use NFSeNacional\Exception\ValidationException;

/**
 * Validador de DPS conforme Norma Técnica do Portal Nacional de NFSe
 * Baseado na estrutura de validação da lib spednfe-php
 */
class DpsValidator
{
    /**
     * Valida prestador conforme NT - campos obrigatórios e opcionais
     */
    public function validatePrestador(array $prestador): array
    {
        $errors = [];
        
        // CNPJ - obrigatório
        $cnpj = preg_replace('/[^0-9]/', '', $prestador['cnpj'] ?? '');
        if (strlen($cnpj) !== 14) {
            $errors[] = 'CNPJ do prestador inválido (deve ter 14 dígitos)';
        }
        
        // IM (Inscrição Municipal) - obrigatório
        $im = preg_replace('/[^0-9]/', '', $prestador['im'] ?? '');
        if (empty($im)) {
            $errors[] = 'Inscrição municipal do prestador é obrigatória';
        }
        
        // Razão Social - obrigatório
        $razaoSocial = trim($prestador['razaoSocial'] ?? '');
        if (empty($razaoSocial)) {
            $errors[] = 'Razão social do prestador é obrigatória';
        }
        
        // Email e Telefone - conforme XML aprovado, aparecem sempre
        // Vamos validar se estão presentes (podem ser obrigatórios na prática)
        $email = trim($prestador['email'] ?? '');
        $telefone = preg_replace('/[^0-9]/', '', $prestador['telefone'] ?? '');
        
        if (empty($email)) {
            $errors[] = 'Email do prestador é obrigatório conforme NT';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email do prestador inválido';
        }
        
        if (empty($telefone)) {
            $errors[] = 'Telefone do prestador é obrigatório conforme NT';
        } elseif (strlen($telefone) < 10 || strlen($telefone) > 11) {
            $errors[] = 'Telefone do prestador inválido (deve ter 10 ou 11 dígitos)';
        }
        
        // regTrib - obrigatório conforme XML aprovado
        if (!isset($prestador['opSimpNac'])) {
            $errors[] = 'opSimpNac do prestador é obrigatório';
        }
        if (!isset($prestador['regApTribSN'])) {
            $errors[] = 'regApTribSN do prestador é obrigatório';
        }
        if (!isset($prestador['regEspTrib'])) {
            $errors[] = 'regEspTrib do prestador é obrigatório';
        }
        
        if (!empty($errors)) {
            throw new ValidationException("Erros na validação do prestador:\n" . implode("\n", array_map(fn($e) => "  • {$e}", $errors)));
        }
        
        // Retornar campos validados + campos adicionais (endereço, etc.) preservados
        return array_merge($prestador, [
            'cnpj' => $cnpj,
            'im' => $im,
            'razaoSocial' => mb_substr($razaoSocial, 0, 150),
            'email' => mb_substr($email, 0, 100),
            'telefone' => $telefone,
            'opSimpNac' => (int)($prestador['opSimpNac'] ?? 3),
            'regApTribSN' => (int)($prestador['regApTribSN'] ?? 1),
            'regEspTrib' => (int)($prestador['regEspTrib'] ?? 0),
        ]);
    }

    /**
     * Valida tomador conforme NT - campos obrigatórios e opcionais
     */
    public function validateTomador(array $tomador): array
    {
        $errors = [];
        
        // CPF/CNPJ - obrigatório
        $documento = preg_replace('/[^0-9]/', '', $tomador['cpfCnpj'] ?? '');
        if (!in_array(strlen($documento), [11, 14], true)) {
            $errors[] = 'Documento do tomador deve ser CPF (11 dígitos) ou CNPJ (14 dígitos)';
        }
        
        // Razão Social/Nome - obrigatório
        $razaoSocial = trim($tomador['razaoSocial'] ?? '');
        if (empty($razaoSocial)) {
            $errors[] = 'Razão social/nome do tomador é obrigatório';
        }
        
        // Email - obrigatório
        $email = trim($tomador['email'] ?? '');
        if (empty($email)) {
            $errors[] = 'Email do tomador é obrigatório';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email do tomador inválido';
        }
        
        // Telefone - conforme XML aprovado, aparece sempre
        $telefone = preg_replace('/[^0-9]/', '', $tomador['telefone'] ?? '');
        if (empty($telefone)) {
            $errors[] = 'Telefone do tomador é obrigatório conforme NT';
        } elseif (strlen($telefone) < 10 || strlen($telefone) > 11) {
            $errors[] = 'Telefone do tomador inválido (deve ter 10 ou 11 dígitos)';
        }
        
        // Endereço - se tiver endereço, deve ter cMun e CEP
        $codigoMunicipio = '';
        $cep = '';
        if (!empty($tomador['endereco']) || !empty($tomador['cep']) || !empty($tomador['numero']) || !empty($tomador['bairro'])) {
            // Se tiver qualquer campo de endereço, validar cMun e CEP
            $codigoMunicipio = preg_replace('/[^0-9]/', '', $tomador['codigoMunicipio'] ?? '');
            if (empty($codigoMunicipio)) {
                $errors[] = 'Código do município do tomador é obrigatório quando há endereço';
            } elseif (strlen($codigoMunicipio) !== 7) {
                $errors[] = 'Código do município do tomador inválido (deve ter 7 dígitos - IBGE)';
            }
            
            $cep = preg_replace('/[^0-9]/', '', $tomador['cep'] ?? '');
            if (empty($cep)) {
                $errors[] = 'CEP do tomador é obrigatório conforme NT quando há endereço';
            } elseif (strlen($cep) !== 8) {
                $errors[] = 'CEP do tomador inválido (deve ter 8 dígitos)';
            }
        } else {
            // Se não tiver endereço, não processar codigoMunicipio e cep (deixar vazios)
            $codigoMunicipio = '';
            $cep = '';
        }
        
        if (!empty($errors)) {
            throw new ValidationException("Erros na validação do tomador:\n" . implode("\n", array_map(fn($e) => "  • {$e}", $errors)));
        }
        
        return [
            'cpfCnpj' => $documento,
            'razaoSocial' => mb_substr($razaoSocial, 0, 150),
            'email' => mb_substr($email, 0, 100),
            'telefone' => $telefone,
            'endereco' => $tomador['endereco'] ?? '',
            'numero' => $tomador['numero'] ?? '',
            'complemento' => $tomador['complemento'] ?? '',
            'bairro' => $tomador['bairro'] ?? '',
            'municipio' => $tomador['municipio'] ?? '',
            'uf' => $tomador['uf'] ?? '',
            'cep' => $cep, // Usar variável processada, não ternário que mascara vazio
            'codigoMunicipio' => $codigoMunicipio, // Usar variável processada, não ternário que mascara vazio
        ];
    }

    /**
     * Valida serviço conforme NT
     */
    public function validateServico(array $servico): array
    {
        $errors = [];
        
        // Campos obrigatórios
        $required = [
            'itemListaServico' => 'Item da lista de serviço (LC116)',
            'discriminacao' => 'Discriminação do serviço',
            'codigoMunicipio' => 'Código do município',
            'valorServicos' => 'Valor dos serviços',
        ];
        
        foreach ($required as $campo => $label) {
            if (!isset($servico[$campo]) || $servico[$campo] === '') {
                $errors[] = "{$label} é obrigatório";
            }
        }
        
        // Validação de valor
        $valorServicos = (float)($servico['valorServicos'] ?? 0);
        if ($valorServicos <= 0) {
            $errors[] = 'Valor dos serviços deve ser maior que zero';
        }
        
        // Validação de código do município
        $codigoMunicipio = preg_replace('/[^0-9]/', '', $servico['codigoMunicipio'] ?? '');
        if (!empty($codigoMunicipio) && strlen($codigoMunicipio) !== 7) {
            $errors[] = 'Código do município inválido (deve ter 7 dígitos - IBGE)';
        }
        
        // cLocPrestacao - obrigatório conforme XML aprovado
        $cLocPrestacao = preg_replace('/[^0-9]/', '', $servico['cLocPrestacao'] ?? '');
        if (empty($cLocPrestacao)) {
            $errors[] = 'cLocPrestacao é obrigatório conforme NT';
        } elseif (strlen($cLocPrestacao) !== 7) {
            $errors[] = 'cLocPrestacao inválido (deve ter 7 dígitos - IBGE)';
        }
        
        // cTribNac - obrigatório conforme XML aprovado
        $cTribNac = $servico['cTribNac'] ?? '';
        if (empty($cTribNac)) {
            $errors[] = 'cTribNac é obrigatório conforme NT';
        }
        
        // cTribMun - obrigatório conforme XML aprovado
        $cTribMun = $servico['cTribMun'] ?? '';
        if (empty($cTribMun)) {
            $errors[] = 'cTribMun é obrigatório conforme NT';
        }
        
        if (!empty($errors)) {
            throw new ValidationException("Erros na validação do serviço:\n" . implode("\n", array_map(fn($e) => "  • {$e}", $errors)));
        }
        
        return [
            'itemListaServico' => $servico['itemListaServico'],
            'discriminacao' => mb_substr($servico['discriminacao'], 0, 2000),
            'codigoMunicipio' => $codigoMunicipio,
            'valorServicos' => number_format($valorServicos, 2, '.', ''),
            'valorIss' => number_format((float) ($servico['valorIss'] ?? 0), 2, '.', ''),
            'aliquota' => number_format((float) ($servico['aliquota'] ?? 0), 4, '.', ''),
            'issRetido' => (int) ($servico['issRetido'] ?? 2),
            'cLocPrestacao' => $cLocPrestacao,
            'cTribNac' => $cTribNac,
            'cTribMun' => $cTribMun,
            'cNBS' => $servico['cNBS'] ?? '',
        ];
    }
    
    /**
     * Valida todos os dados antes do envio (similar à estrutura spednfe-php)
     */
    public function validateAll(array $dados): void
    {
        $errors = [];
        
        try {
            $this->validatePrestador($dados['prestador'] ?? []);
        } catch (ValidationException $e) {
            $errors[] = $e->getMessage();
        }
        
        try {
            $this->validateTomador($dados['tomador'] ?? []);
        } catch (ValidationException $e) {
            $errors[] = $e->getMessage();
        }
        
        try {
            $this->validateServico($dados['servico'] ?? []);
        } catch (ValidationException $e) {
            $errors[] = $e->getMessage();
        }
        
        // Validações adicionais
        if (empty($dados['identificacao']['numero'])) {
            $errors[] = 'Número da DPS é obrigatório';
        }
        
        if (empty($dados['competencia'])) {
            $errors[] = 'Competência é obrigatória';
        }
        
        if (empty($dados['cLocEmi'])) {
            $errors[] = 'Código do município de emissão (cLocEmi) é obrigatório';
        } elseif (strlen(preg_replace('/[^0-9]/', '', $dados['cLocEmi'])) !== 7) {
            $errors[] = 'Código do município de emissão inválido (deve ter 7 dígitos - IBGE)';
        }
        
        if (!empty($errors)) {
            throw new ValidationException("Erros de validação antes do envio:\n" . implode("\n", array_map(fn($e) => "  • {$e}", $errors)));
        }
    }
}

