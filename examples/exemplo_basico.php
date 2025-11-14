<?php
/**
 * Exemplo básico de uso da biblioteca PHP NFSe Nacional
 */

require __DIR__ . '/../vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

try {
    // 1. CONFIGURAÇÃO
    $config = new Config([
        'cnpj' => '12345678901234',
        'im' => '123456',
        'cmun' => '3550308', // São Paulo
        'razaosocial' => 'Minha Empresa LTDA',
        'ambiente' => 2, // 1=Produção, 2=Homologação
        'certPfx' => __DIR__ . '/certificado.pfx',
        'certPassword' => 'senha_certificado'
    ]);
    
    $tools = new Tools($config);
    
    // 2. CRIAR DPS
    $dps = new Make();
    
    $dps->setPrestador([
        'cnpj' => '12345678901234',
        'im' => '123456'
    ]);
    
    $dps->setTomador([
        'cpfCnpj' => '98765432100',
        'razaoSocial' => 'Cliente Teste',
        'email' => 'cliente@teste.com'
    ]);
    
    $dps->setServico([
        'itemListaServico' => '01.01',
        'discriminacao' => 'Serviço de desenvolvimento de software',
        'codigoMunicipio' => '3550308',
        'valorServicos' => 1000.00,
        'valorIss' => 50.00,
        'aliquota' => 5.00
    ]);
    
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
