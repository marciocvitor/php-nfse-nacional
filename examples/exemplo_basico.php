<?php

/**
 * Exemplo básico de uso da biblioteca PHP NFSe Nacional
 * Compatível com PHP 8.2+
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

try {
    // 1. CONFIGURAÇÃO
    // Nota: URLs da API são automáticas baseadas no ambiente
    // Homologação: https://sefin.producaorestrita.nfse.gov.br
    // Produção: https://sefin.nfse.gov.br
    $config = Config::fromArray([
        'cnpj' => '12345678901234',
        'im' => '123456',
        'cmun' => '3550308', // São Paulo
        'razaosocial' => 'Minha Empresa LTDA',
        'ambiente' => 2, // 1=Produção, 2=Homologação
        'certPfx' => __DIR__ . '/certificado.pfx',
        'certPassword' => 'senha_certificado',
        // apiBaseUrl é opcional - usa padrão se não informado
        // 'apiBaseUrl' => 'https://sefin.producaorestrita.nfse.gov.br'
    ]);

    $tools = new Tools($config);

    // 2. CRIAR DPS
    $dps = new Make();

    // Série deve ser numérica (1 a 4 dígitos) conforme TSSerieDPS
    $dps->setCLocEmi('3550308'); // IBGE do município de emissão (obrigatório)

    $dps->setIdentificacao([
        'numero' => '123',
        'serie' => '1'
    ]);

    $dps->setPrestador([
        'cnpj' => '12345678000195', // CNPJ numérico ou alfanumérico (NT 009)
        'im' => '123456',
        'razaoSocial' => 'Minha Empresa LTDA',
        'opSimpNac' => 3, // 1=Não optante, 2=MEI, 3=ME/EPP (confirmar no Anexo oficial)
        'regEspTrib' => 0, // 0 = nenhum
        'regApTribSN' => 1
    ]);

    $dps->setTomador([
        'cpfCnpj' => '98765432100',
        'razaoSocial' => 'Cliente Teste',
        'email' => 'cliente@teste.com'
    ]);

    $dps->setServico([
        'cTribNac' => '010701', // Código de tributação nacional (6 dígitos)
        'discriminacao' => 'Serviço de desenvolvimento de software',
        'valorServicos' => 1000.00,
        'issRetido' => 2 // 1=Sim, 2=Não
    ]);
    $dps->setPTotTribSN('10.51'); // Opcional: percentual de tributos do Simples Nacional

    // 3. GERAR XML
    $xml = $dps->getXML();
    echo "XML Gerado:\n";
    echo $xml . "\n\n";

    // 4. ENVIAR DPS
    $response = $tools->enviarDps($xml);

    if ($response->sucesso) {
        echo "✓ Sucesso!\n";
        echo "Número: " . $response->numero . "\n";
        echo "Código: " . $response->codigoVerificacao . "\n";
    } else {
        echo "✗ Erro: " . $response->mensagem . "\n";
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
