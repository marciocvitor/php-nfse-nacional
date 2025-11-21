<?php

/**
 * Script para testar URLs de WSDL do Portal Nacional de NFSe
 * 
 * Este script testa várias variações possíveis de URLs para identificar
 * quais estão acessíveis e válidas.
 * 
 * Uso: php test_wsdl_urls.php
 */

echo "=== TESTE DE URLs WSDL - PORTAL NACIONAL NFSe ===\n\n";

// Lista de variações de URLs para testar
$urlsParaTestar = [
    // Padrão 1: /wsdl/Operacao.wsdl
    'homologacao' => [
        'https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl',
        'https://homologacao.nfse.gov.br/RecepcionarDps.svc?wsdl',
        'https://homologacao.nfse.gov.br/RecepcionarDps.svc?singleWsdl',
        'https://www.gov.br/nfse/homologacao/wsdl/RecepcionarDps.wsdl',
        'https://nfse.gov.br/homologacao/wsdl/RecepcionarDps.wsdl',
        'https://api-homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl',
        'https://homologacao.api.nfse.gov.br/wsdl/RecepcionarDps.wsdl',
    ],
    'producao' => [
        'https://api.nfse.gov.br/wsdl/RecepcionarDps.wsdl',
        'https://nfse.gov.br/wsdl/RecepcionarDps.wsdl',
        'https://www.gov.br/nfse/producao/wsdl/RecepcionarDps.wsdl',
        'https://api.nfse.gov.br/RecepcionarDps.svc?wsdl',
        'https://api.nfse.gov.br/RecepcionarDps.svc?singleWsdl',
        'https://producao.nfse.gov.br/wsdl/RecepcionarDps.wsdl',
    ],
];

/**
 * Testa se uma URL está acessível
 */
function testarUrl(string $url, bool $verificarSoap = false): array
{
    $resultado = [
        'url' => $url,
        'acessivel' => false,
        'httpCode' => null,
        'erro' => null,
        'tipo' => null,
        'soapValido' => false,
    ];

    // Teste 1: Verificar se URL está acessível via HTTP
    $ch = curl_init($url);
    if ($ch === false) {
        $resultado['erro'] = 'Não foi possível inicializar cURL';
        return $resultado;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => false, // Precisamos do conteúdo para verificar se é WSDL
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false, // Desabilita em teste
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'PHP-NFSe-Nacional-Test/1.0',
    ]);

    $conteudo = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $resultado['httpCode'] = $httpCode;
    $resultado['tipo'] = $contentType;

    if ($erroCurl) {
        $resultado['erro'] = $erroCurl;
        return $resultado;
    }

    // Verifica se retornou código HTTP válido
    if ($httpCode >= 200 && $httpCode < 400) {
        $resultado['acessivel'] = true;

        // Verifica se o conteúdo parece ser um WSDL válido
        if ($conteudo && (str_contains($conteudo, '<?xml') || str_contains($conteudo, '<wsdl:') || str_contains($conteudo, '<definitions'))) {
            $resultado['soapValido'] = true;
        }

        // Se solicitado, tenta criar SoapClient para validar
        if ($verificarSoap && $resultado['soapValido']) {
            try {
                $client = @new SoapClient($url, [
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'connection_timeout' => 10,
                ]);
                $resultado['soapValido'] = ($client !== null);
            } catch (\Exception $e) {
                $resultado['soapValido'] = false;
                $resultado['erro'] = $e->getMessage();
            }
        }
    } else {
        $resultado['erro'] = "HTTP {$httpCode}";
    }

    return $resultado;
}

/**
 * Exibe resultado do teste de forma formatada
 */
function exibirResultado(array $resultado): void
{
    $status = $resultado['acessivel'] ? '✓' : '✗';
    $soapStatus = $resultado['soapValido'] ? '✓ SOAP' : '';
    
    echo sprintf(
        "%s [HTTP %s] %s %s\n",
        $status,
        $resultado['httpCode'] ?? 'N/A',
        $resultado['url'],
        $soapStatus
    );

    if ($resultado['tipo']) {
        echo "    Content-Type: {$resultado['tipo']}\n";
    }

    if ($resultado['erro'] && !$resultado['acessivel']) {
        echo "    Erro: {$resultado['erro']}\n";
    }

    echo "\n";
}

// Testa URLs de homologação
echo "--- TESTANDO URLs DE HOMOLOGAÇÃO ---\n\n";
$homologacaoValidas = [];

foreach ($urlsParaTestar['homologacao'] as $url) {
    $resultado = testarUrl($url, true);
    exibirResultado($resultado);
    
    if ($resultado['acessivel'] && $resultado['soapValido']) {
        $homologacaoValidas[] = $url;
    }
}

// Testa URLs de produção
echo "\n--- TESTANDO URLs DE PRODUÇÃO ---\n\n";
$producaoValidas = [];

foreach ($urlsParaTestar['producao'] as $url) {
    $resultado = testarUrl($url, true);
    exibirResultado($resultado);
    
    if ($resultado['acessivel'] && $resultado['soapValido']) {
        $producaoValidas[] = $url;
    }
}

// Resumo
echo "\n=== RESUMO ===\n\n";

if (!empty($homologacaoValidas)) {
    echo "✓ URLs de HOMOLOGAÇÃO válidas encontradas:\n";
    foreach ($homologacaoValidas as $url) {
        echo "  - {$url}\n";
    }
} else {
    echo "✗ Nenhuma URL de HOMOLOGAÇÃO válida encontrada\n";
    echo "  Verifique se:\n";
    echo "  1. Você está conectado à internet\n";
    echo "  2. As URLs estão corretas na documentação oficial\n";
    echo "  3. O serviço não está em manutenção\n";
}

echo "\n";

if (!empty($producaoValidas)) {
    echo "✓ URLs de PRODUÇÃO válidas encontradas:\n";
    foreach ($producaoValidas as $url) {
        echo "  - {$url}\n";
    }
} else {
    echo "✗ Nenhuma URL de PRODUÇÃO válida encontrada\n";
    echo "  (Isso é normal se você não tiver acesso ao ambiente de produção)\n";
}

echo "\n";

// Sugestão de configuração
if (!empty($homologacaoValidas) || !empty($producaoValidas)) {
    echo "=== CONFIGURAÇÃO SUGERIDA ===\n\n";
    echo "Use as URLs válidas encontradas acima no seu Config:\n\n";
    echo "'wsdl' => [\n";
    
    if (!empty($homologacaoValidas)) {
        echo "    'homologacao' => [\n";
        echo "        'RecepcionarDps' => '{$homologacaoValidas[0]}',\n";
        echo "        // Adicione outras operações conforme necessário\n";
        echo "    ],\n";
    }
    
    if (!empty($producaoValidas)) {
        echo "    'producao' => [\n";
        echo "        'RecepcionarDps' => '{$producaoValidas[0]}',\n";
        echo "        // Adicione outras operações conforme necessário\n";
        echo "    ],\n";
    }
    
    echo "]\n";
}

echo "\n";

