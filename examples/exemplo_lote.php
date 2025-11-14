<?php

declare(strict_types=1);

/**
 * EXEMPLO COMPLETO: Envio em LOTE de NFSe
 * 
 * Demonstra o processo ASSÍNCRONO de envio em lote:
 * 1. Criar múltiplas DPS
 * 2. Enviar lote
 * 3. Receber protocolo
 * 4. Consultar resultado
 */

require __DIR__ . '/../vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

try {
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║          EXEMPLO: ENVIO EM LOTE (ASSÍNCRONO)                ║\n";
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
        'ambiente' => 2,
        'certPfx' => __DIR__ . '/certificado.pfx',
        'certPassword' => 'senha'
    ]);
    
    $tools = new Tools($config);
    echo "  ✓ Configuração OK\n\n";
    
    // ========================================
    // PASSO 2: CRIAR MÚLTIPLAS DPS
    // ========================================
    echo "→ PASSO 2: Criando 3 DPS para envio em lote...\n";
    
    $listaDps = [];
    
    // DPS 1
    $dps1 = new Make();
    $dps1->setPrestador(['cnpj' => '12345678901234', 'im' => '123456']);
    $dps1->setTomador([
        'cpfCnpj' => '11111111111',
        'razaoSocial' => 'Cliente 1',
        'email' => 'cliente1@teste.com'
    ]);
    $dps1->setServico([
        'itemListaServico' => '01.01',
        'discriminacao' => 'Serviço 1',
        'codigoMunicipio' => '3550308',
        'valorServicos' => 1000.00,
        'valorIss' => 50.00,
        'aliquota' => 5.00
    ]);
    $listaDps[] = $dps1->getXML();
    echo "  ✓ DPS 1 criada (R$ 1.000,00)\n";
    
    // DPS 2
    $dps2 = new Make();
    $dps2->setPrestador(['cnpj' => '12345678901234', 'im' => '123456']);
    $dps2->setTomador([
        'cpfCnpj' => '22222222222',
        'razaoSocial' => 'Cliente 2',
        'email' => 'cliente2@teste.com'
    ]);
    $dps2->setServico([
        'itemListaServico' => '01.01',
        'discriminacao' => 'Serviço 2',
        'codigoMunicipio' => '3550308',
        'valorServicos' => 2000.00,
        'valorIss' => 100.00,
        'aliquota' => 5.00
    ]);
    $listaDps[] = $dps2->getXML();
    echo "  ✓ DPS 2 criada (R$ 2.000,00)\n";
    
    // DPS 3
    $dps3 = new Make();
    $dps3->setPrestador(['cnpj' => '12345678901234', 'im' => '123456']);
    $dps3->setTomador([
        'cpfCnpj' => '33333333333',
        'razaoSocial' => 'Cliente 3',
        'email' => 'cliente3@teste.com'
    ]);
    $dps3->setServico([
        'itemListaServico' => '01.01',
        'discriminacao' => 'Serviço 3',
        'codigoMunicipio' => '3550308',
        'valorServicos' => 1500.00,
        'valorIss' => 75.00,
        'aliquota' => 5.00
    ]);
    $listaDps[] = $dps3->getXML();
    echo "  ✓ DPS 3 criada (R$ 1.500,00)\n";
    echo "  ✓ Total: " . count($listaDps) . " DPS prontas\n\n";
    
    // ========================================
    // PASSO 3: ENVIAR LOTE (ASSÍNCRONO)
    // ========================================
    echo "→ PASSO 3: Enviando lote...\n";
    
    $numeroLote = 'LOTE' . date('YmdHis');
    $response = $tools->enviarLoteDps(
        listaDps: $listaDps,
        numeroLote: $numeroLote
    );
    
    if (!$response->sucesso) {
        throw new Exception("Erro ao enviar lote: " . $response->mensagem);
    }
    
    $protocolo = $response->dados['protocolo'];
    echo "  ✓ Lote enviado com sucesso!\n";
    echo "  ✓ Protocolo: {$protocolo}\n";
    echo "  ✓ Número do Lote: {$numeroLote}\n";
    echo "  ✓ Quantidade: " . $response->dados['quantidadeDps'] . " DPS\n";
    echo "  ⏳ Processamento em andamento...\n\n";
    
    // ========================================
    // PASSO 4: AGUARDAR PROCESSAMENTO
    // ========================================
    echo "→ PASSO 4: Aguardando processamento (simulação)...\n";
    echo "  ⏳ Aguardando 3 segundos...\n";
    sleep(3);
    echo "  ✓ Tempo de espera finalizado\n\n";
    
    // ========================================
    // PASSO 5: CONSULTAR RESULTADO DO LOTE
    // ========================================
    echo "→ PASSO 5: Consultando resultado do lote...\n";
    
    $resultado = $tools->consultarLote($protocolo);
    
    if (!$resultado->sucesso) {
        throw new Exception("Erro ao consultar lote: " . $resultado->mensagem);
    }
    
    echo "  ✓ Lote processado!\n";
    echo "  ✓ Situação: " . $resultado->dados['situacao'] . "\n";
    echo "  ✓ Processadas: " . $resultado->dados['quantidadeProcessada'] . "\n";
    echo "  ✓ Com Sucesso: " . $resultado->dados['quantidadeComSucesso'] . "\n";
    echo "  ✓ Com Erro: " . $resultado->dados['quantidadeComErro'] . "\n\n";
    
    // ========================================
    // PASSO 6: EXIBIR NFSes GERADAS
    // ========================================
    echo "→ PASSO 6: NFSes geradas:\n\n";
    
    foreach ($resultado->dados['nfses'] as $index => $nfse) {
        echo "  NFSe " . ($index + 1) . ":\n";
        echo "    Número: " . $nfse['numero'] . "\n";
        echo "    Código Verificação: " . $nfse['codigoVerificacao'] . "\n";
        echo "    Data Emissão: " . $nfse['dataEmissao'] . "\n\n";
    }
    
    // ========================================
    // RESUMO
    // ========================================
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                    PROCESSO FINALIZADO                       ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
    
    echo "RESUMO:\n";
    echo "✓ DPS criadas: " . count($listaDps) . "\n";
    echo "✓ Lote enviado: {$numeroLote}\n";
    echo "✓ Protocolo: {$protocolo}\n";
    echo "✓ NFSes geradas: " . count($resultado->dados['nfses']) . "\n";
    echo "✓ Status: Sucesso\n\n";
    
} catch (Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
