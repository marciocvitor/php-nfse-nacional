<?php

declare(strict_types=1);

namespace NFSeNacional\Validator;

use NFSeNacional\Exception\ValidationException;

class DpsValidator
{
    public function validatePrestador(array $prestador): array
    {
        $cnpj = preg_replace('/[^0-9]/', '', $prestador['cnpj'] ?? '');
        $im = preg_replace('/[^0-9]/', '', $prestador['im'] ?? '');

        if (strlen($cnpj) !== 14) {
            throw new ValidationException('CNPJ do prestador inválido');
        }

        if ($im === '') {
            throw new ValidationException('Inscrição municipal do prestador é obrigatória');
        }

        return [
            'cnpj' => $cnpj,
            'im' => $im,
            'razaoSocial' => mb_substr(trim($prestador['razaoSocial'] ?? ''), 0, 150),
        ];
    }

    public function validateTomador(array $tomador): array
    {
        $documento = preg_replace('/[^0-9]/', '', $tomador['cpfCnpj'] ?? '');
        if (!in_array(strlen($documento), [11, 14], true)) {
            throw new ValidationException('Documento do tomador deve ser CPF (11) ou CNPJ (14)');
        }

        if (empty($tomador['razaoSocial'])) {
            throw new ValidationException('Razão social/nome do tomador é obrigatório');
        }

        return [
            'cpfCnpj' => $documento,
            'razaoSocial' => mb_substr(trim($tomador['razaoSocial']), 0, 150),
            'email' => mb_substr(trim($tomador['email'] ?? ''), 0, 100),
            'endereco' => $tomador['endereco'] ?? [],
        ];
    }

    public function validateServico(array $servico): array
    {
        $required = ['itemListaServico', 'discriminacao', 'codigoMunicipio', 'valorServicos'];
        foreach ($required as $campo) {
            if (!isset($servico[$campo]) || $servico[$campo] === '') {
                throw new ValidationException("Campo {$campo} é obrigatório no serviço");
            }
        }

        $valorServicos = number_format((float) $servico['valorServicos'], 2, '.', '');
        if ((float) $valorServicos <= 0) {
            throw new ValidationException('Valor dos serviços deve ser maior que zero');
        }

        return [
            'itemListaServico' => $servico['itemListaServico'],
            'discriminacao' => mb_substr($servico['discriminacao'], 0, 2000),
            'codigoMunicipio' => preg_replace('/[^0-9]/', '', $servico['codigoMunicipio']),
            'valorServicos' => $valorServicos,
            'valorIss' => number_format((float) ($servico['valorIss'] ?? 0), 2, '.', ''),
            'aliquota' => number_format((float) ($servico['aliquota'] ?? 0), 4, '.', ''),
            'issRetido' => (int) ($servico['issRetido'] ?? 2),
        ];
    }
}

