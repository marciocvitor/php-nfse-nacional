<?php

declare(strict_types=1);

/**
 * Exemplo básico de uso da biblioteca PHP NFSe Nacional
 * Compatível com PHP 8.2+
 */

require __DIR__ . '/../vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

try {
    // 1. CONFIGURAÇÃO
    // Nota: URLs da API são automáticas baseadas no ambiente
    // Homologação: https://adn.producaorestrita.nfse.gov.br
    // Produção: https://adn.nfse.gov.br
    $config = Config::fromArray([
        'cnpj' => '12345678901234',
        'im' => '123456',
        'cmun' => '3550308', // São Paulo
        'razaosocial' => 'Minha Empresa LTDA',
        'ambiente' => 2, // 1=Produção, 2=Homologação
        'certPfx' => __DIR__ . '/certificado.pfx',
        'certPassword' => 'senha_certificado',
        // apiBaseUrl é opcional - usa padrão se não informado
        // 'apiBaseUrl' => 'https://adn.producaorestrita.nfse.gov.br'
    ]);
    
    $tools = new Tools($config);
    
    // 2. CRIAR DPS
    $dps = new Make();
    
    $dps->setIdentificacao([
        'numero' => '123',
        'serie' => 'UNICA',
        'tipo' => 1
    ]);
    
    $dps->setPrestador([
        'cnpj' => '12345678901234',
        'im' => '123456',
        'razaoSocial' => 'Minha Empresa LTDA'
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
        'aliquota' => 0.0500
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
