<?php

/**
 * EXEMPLO: Geração do DANFSe (PDF) de uma NFS-e Nacional
 *
 * Tools::obterDanfse() tenta a API oficial do portal e, se ela estiver indisponível
 * (serviço descontinuado em 03/08/2026), gera o DANFSe localmente a partir do XML autorizado.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;

try {
    $config = Config::fromArray([
        'cnpj' => '12345678000195',
        'im' => '123456',
        'cmun' => '3550308',
        'razaoSocial' => 'Minha Empresa LTDA',
        'ambiente' => 2, // 1 = Produção, 2 = Homologação (produção restrita)
        'certPfx' => __DIR__ . '/certificado.pfx',
        'certPassword' => 'senha',
    ]);
    $tools = new Tools($config);

    // Chave de acesso (50 dígitos) da NFS-e emitida, ex.: a devolvida por enviarDps()
    $chaveAcesso = '35503082112345678000195000000000000126010000000001';

    // XML autorizado: guardado pelo seu sistema na emissão ou obtido no portal
    $xmlNfse = $tools->consultarNfsePorChave($chaveAcesso)->xml ?? '';

    // Para NFS-e cancelada, passe true no 4º parâmetro (marca d'água "CANCELADA")
    $danfse = $tools->obterDanfse($chaveAcesso, $xmlNfse, [], false);

    $caminhoPdf = __DIR__ . '/danfse_' . $chaveAcesso . '.pdf';
    file_put_contents($caminhoPdf, $danfse['pdf']);

    echo "DANFSe gerado ({$danfse['origem']}): {$caminhoPdf}\n";
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
}
