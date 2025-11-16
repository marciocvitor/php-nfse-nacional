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
    // 1. CONFIGURAÇÃO (usando named arguments do PHP 8.0+)
    $config = Config::fromArray([
        'cnpj' => '12345678901234',
        'im' => '123456',
        'cmun' => '3550308', // São Paulo
        'razaosocial' => 'Minha Empresa LTDA',
        'ambiente' => 2, // 1=Produção, 2=Homologação
        'certPfx' => __DIR__ . '/certificado.pfx',
        'certPassword' => 'senha_certificado',
        'wsdl' => [
            'homologacao' => [
                'RecepcionarDps' => 'https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl',
                'RecepcionarLoteRps' => 'https://homologacao.nfse.gov.br/wsdl/RecepcionarLoteRps.wsdl',
                'ConsultarLoteRps' => 'https://homologacao.nfse.gov.br/wsdl/ConsultarLoteRps.wsdl',
                'ConsultarNfse' => 'https://homologacao.nfse.gov.br/wsdl/ConsultarNfse.wsdl',
                'CancelarNfse' => 'https://homologacao.nfse.gov.br/wsdl/CancelarNfse.wsdl'
            ]
        ]
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
