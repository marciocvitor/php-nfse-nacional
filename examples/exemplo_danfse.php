<?php

declare(strict_types=1);

/**
 * EXEMPLO: Geração de DANFSe (PDF)
 * 
 * Demonstra como gerar o PDF do DANFSe após emitir ou consultar uma NFSe
 */

require __DIR__ . '/../vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

try {
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║          EXEMPLO: GERAÇÃO DE DANFSe (PDF)                    ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
    
    // ========================================
    // PASSO 1: CONFIGURAÇÃO
    // ========================================
    echo "→ PASSO 1: Configurando...\n";
    
    $config = Config::fromArray([
        'cnpj' => '12345678901234',
        'im' => '123456',
        'cmun' => '3550308',
        'razaosocial' => 'Minha Empresa LTDA',
        'ambiente' => 2, // 1=Produção, 2=Homologação
        'certPfx' => __DIR__ . '/certificado.pfx',
        'certPassword' => 'senha',
        // URLs da API são automáticas baseadas no ambiente
        // apiBaseUrl é opcional - usa padrão se não informado
    ]);
    
    $tools = new Tools($config);
    echo "  ✓ Configuração OK\n\n";
    
    // ========================================
    // PASSO 2: EMITIR NFSe (ou consultar existente)
    // ========================================
    echo "→ PASSO 2: Emitindo NFSe...\n";
    
    $dps = (new Make())
        ->setIdentificacao([
            'numero' => '123',
            'serie' => 'A1',
            'tipo' => 1
        ])
        ->setPrestador([
            'cnpj' => '12345678901234',
            'im' => '123456',
            'razaoSocial' => 'Minha Empresa LTDA'
        ])
        ->setTomador([
            'cpfCnpj' => '98765432100',
            'razaoSocial' => 'Cliente Teste',
            'email' => 'cliente@teste.com'
        ])
        ->setServico([
            'itemListaServico' => '01.01',
            'discriminacao' => 'Serviço de desenvolvimento de software',
            'codigoMunicipio' => '3550308',
            'valorServicos' => 1000.00,
            'valorIss' => 50.00,
            'aliquota' => 0.0500,
            'issRetido' => 2
        ]);
    
    $response = $tools->enviarDps($dps->getXML());
    
    if (!$response->sucesso) {
        throw new Exception("Erro ao emitir NFSe: " . $response->mensagem);
    }
    
    echo "  ✓ NFSe emitida: " . $response->numero . "\n";
    echo "  ✓ Código: " . $response->codigoVerificacao . "\n\n";
    
    // ========================================
    // PASSO 3: GERAR DANFSe (PDF)
    // ========================================
    echo "→ PASSO 3: Gerando DANFSe (PDF)...\n";
    
    // Dados adicionais que podem não estar no XML
    $dadosAdicionais = [
        'prestador' => [
            'razaoSocial' => 'Minha Empresa LTDA' // Se não estiver no XML
        ]
    ];
    
    // Opção 1: Gerar e salvar em arquivo
    $caminhoPdf = __DIR__ . '/danfse_' . $response->numero . '.pdf';
    $tools->gerarDanfseArquivo($response, $caminhoPdf, $dadosAdicionais);
    echo "  ✓ PDF salvo em: {$caminhoPdf}\n";
    
    // Opção 2: Gerar e obter como string (para download direto)
    // $pdfContent = $tools->gerarDanfse($response, $dadosAdicionais);
    // header('Content-Type: application/pdf');
    // header('Content-Disposition: attachment; filename="danfse_' . $response->numero . '.pdf"');
    // echo $pdfContent;
    
    // Opção 3: Gerar e obter como base64 (para exibir em HTML)
    // $pdfBase64 = $tools->gerarDanfseBase64($response, $dadosAdicionais);
    // echo '<iframe src="data:application/pdf;base64,' . $pdfBase64 . '"></iframe>';
    
    echo "\n";
    
    // ========================================
    // PASSO 4: EXEMPLO COM CONSULTA
    // ========================================
    echo "→ PASSO 4: Exemplo - Gerar DANFSe de NFSe consultada...\n";
    
    // Se você já tem o número de uma NFSe, pode consultar e gerar o PDF
    // $responseConsulta = $tools->consultarNfse('12345');
    // if ($responseConsulta->sucesso) {
    //     $tools->gerarDanfseArquivo($responseConsulta, __DIR__ . '/danfse_consultada.pdf');
    //     echo "  ✓ PDF gerado da NFSe consultada\n";
    // }
    
    echo "  ✓ Exemplo concluído\n\n";
    
    // ========================================
    // RESUMO
    // ========================================
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                    PROCESSO FINALIZADO                       ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
    
    echo "RESUMO:\n";
    echo "✓ NFSe emitida: " . $response->numero . "\n";
    echo "✓ Código de verificação: " . $response->codigoVerificacao . "\n";
    echo "✓ DANFSe gerado: {$caminhoPdf}\n";
    echo "✓ Status: Sucesso\n\n";
    
} catch (Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

