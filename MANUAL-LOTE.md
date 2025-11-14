# Manual Completo: Envio em Lote de NFSe

## Índice

1. [Introdução](#introdução)
2. [Diferenças: Individual vs Lote](#diferenças-individual-vs-lote)
3. [Processo Completo de Envio em Lote](#processo-completo-de-envio-em-lote)
4. [Exemplos Práticos](#exemplos-práticos)
5. [Tratamento de Erros](#tratamento-de-erros)
6. [Boas Práticas](#boas-práticas)
7. [FAQ](#faq)

---

## Introdução

O **Portal Nacional de NFSe** oferece duas formas de emissão:

- **Individual (Síncrona):** Envia 1 DPS e recebe a NFSe imediatamente
- **Lote (Assíncrona):** Envia múltiplas DPS de uma vez e consulta o resultado depois

Este manual explica o **processo em lote (assíncrono)**.

---

## Diferenças: Individual vs Lote

### Emissão Individual (Síncrona)

```
┌─────────────┐        ┌──────────────┐        ┌─────────────┐
│   Cliente   │───1───>│    Portal    │───2───>│   Cliente   │
│  Envia DPS  │        │  Processa e  │        │ Recebe NFSe │
│             │        │Retorna NFSe  │        │             │
└─────────────┘        └──────────────┘        └─────────────┘
     [Mesma Requisição - Resposta Imediata]
```

**Código:**
```php
$response = $tools->enviarDps($xml);
// NFSe JÁ está disponível em $response
```

---

### Envio em Lote (Assíncrono)

```
ETAPA 1: ENVIO
┌─────────────┐        ┌──────────────┐        ┌─────────────┐
│   Cliente   │───1───>│    Portal    │───2───>│   Cliente   │
│Envia 50 DPS │        │Aceita e gera │        │   Recebe    │
│             │        │  Protocolo   │        │  Protocolo  │
└─────────────┘        └──────────────┘        └─────────────┘

ETAPA 2: PROCESSAMENTO (background)
                       ┌──────────────┐
                       │    Portal    │
                       │   Processa   │
                       │50 DPS        │
                       └──────────────┘
                      [Pode levar tempo]

ETAPA 3: CONSULTA
┌─────────────┐        ┌──────────────┐        ┌─────────────┐
│   Cliente   │───3───>│    Portal    │───4───>│   Cliente   │
│Consulta com │        │Retorna NFSes │        │Recebe 50    │
│ Protocolo   │        │  Processadas │        │   NFSes     │
└─────────────┘        └──────────────┘        └─────────────┘
```

**Código:**
```php
// 1. Enviar lote
$response = $tools->enviarLoteDps($listaDps, 'LOTE001');
$protocolo = $response->dados['protocolo'];

// 2. Aguardar processamento
sleep(5);

// 3. Consultar resultado
$resultado = $tools->consultarLote($protocolo);
// Agora sim, NFSes estão disponíveis
```

---

## Processo Completo de Envio em Lote

### Passo 1: Criar Múltiplas DPS

```php
<?php

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

// Configuração
$config = Config::fromArray([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'ambiente' => 2
]);

$tools = new Tools($config);

// Criar lista de DPS
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
    'discriminacao' => 'Serviço de desenvolvimento',
    'codigoMunicipio' => '3550308',
    'valorServicos' => 1000.00,
    'valorIss' => 50.00,
    'aliquota' => 5.00
]);
$listaDps[] = $dps1->getXML();

// DPS 2, 3, 4... (repita o processo)
// ... adicione mais DPS

echo "Total de DPS criadas: " . count($listaDps) . "\n";
```

**Importante:**
- ✅ Limite: **50 DPS por lote**
- ✅ Cada DPS deve ser válida individualmente
- ✅ Use um array para armazenar os XMLs

---

### Passo 2: Enviar Lote

```php
// Gerar número único do lote
$numeroLote = 'LOTE' . date('YmdHis'); // Ex: LOTE20251114235959

// Enviar lote
$response = $tools->enviarLoteDps(
    listaDps: $listaDps,
    numeroLote: $numeroLote
);

if ($response->sucesso) {
    // Salvar protocolo para consulta posterior
    $protocolo = $response->dados['protocolo'];
    $quantidadeDps = $response->dados['quantidadeDps'];
    
    echo "Lote enviado com sucesso!\n";
    echo "Protocolo: {$protocolo}\n";
    echo "Quantidade: {$quantidadeDps} DPS\n";
    
    // IMPORTANTE: Guardar protocolo no banco de dados
    salvarProtocolo($protocolo, $numeroLote);
    
} else {
    echo "Erro: " . $response->mensagem . "\n";
}
```

**Retorno esperado:**
```php
[
    'sucesso' => true,
    'mensagem' => 'Lote enviado com sucesso...',
    'dados' => [
        'protocolo' => 'PROT20251114235959123',
        'numeroLote' => 'LOTE20251114235959',
        'quantidadeDps' => 50,
        'dataEnvio' => '2025-11-14 23:59:59'
    ]
]
```

---

### Passo 3: Aguardar Processamento

O Portal Nacional processa o lote **em background**. O tempo varia conforme:
- Quantidade de DPS no lote
- Carga do servidor
- Validações necessárias

**Tempos estimados:**
- 1-10 DPS: ~2-5 segundos
- 11-30 DPS: ~5-15 segundos
- 31-50 DPS: ~15-30 segundos

```php
// Opção 1: Aguardar um tempo fixo
sleep(10); // 10 segundos

// Opção 2: Polling (consultar periodicamente)
$tentativas = 0;
$maxTentativas = 12; // Máximo 2 minutos (12 x 10s)

while ($tentativas < $maxTentativas) {
    $resultado = $tools->consultarLote($protocolo);
    
    if ($resultado->dados['situacao'] === 'Processado') {
        break; // Lote pronto!
    }
    
    echo "Aguardando... Tentativa " . ($tentativas + 1) . "\n";
    sleep(10); // Aguarda 10 segundos
    $tentativas++;
}
```

---

### Passo 4: Consultar Resultado

```php
$resultado = $tools->consultarLote($protocolo);

if ($resultado->sucesso) {
    echo "Situação: " . $resultado->dados['situacao'] . "\n";
    echo "Processadas: " . $resultado->dados['quantidadeProcessada'] . "\n";
    echo "Com Sucesso: " . $resultado->dados['quantidadeComSucesso'] . "\n";
    echo "Com Erro: " . $resultado->dados['quantidadeComErro'] . "\n";
    
    // Processar NFSes geradas
    foreach ($resultado->dados['nfses'] as $nfse) {
        echo "NFSe: " . $nfse['numero'] . "\n";
        echo "Código: " . $nfse['codigoVerificacao'] . "\n";
        
        // Salvar no banco de dados
        salvarNfse($nfse);
    }
}
```

**Estrutura da resposta:**
```php
[
    'sucesso' => true,
    'mensagem' => 'Lote processado com sucesso',
    'dados' => [
        'protocolo' => 'PROT20251114235959123',
        'situacao' => 'Processado',
        'quantidadeProcessada' => 50,
        'quantidadeComSucesso' => 48,
        'quantidadeComErro' => 2,
        'nfses' => [
            [
                'numero' => '10001',
                'codigoVerificacao' => 'ABC123',
                'dataEmissao' => '2025-11-14 23:59:59',
                'xml' => '<xml>...</xml>'
            ],
            // ... mais 47 NFSes
        ],
        'erros' => [
            [
                'numeroRps' => '005',
                'codigo' => 'E001',
                'mensagem' => 'CNPJ do tomador inválido'
            ],
            // ... erros das 2 DPS que falharam
        ]
    ]
]
```

---

### Passo 5: Tratamento de Erros no Lote

```php
if ($resultado->dados['quantidadeComErro'] > 0) {
    echo "ATENÇÃO: Algumas DPS falharam!\n";
    
    foreach ($resultado->dados['erros'] as $erro) {
        echo "RPS: " . $erro['numeroRps'] . "\n";
        echo "Erro: " . $erro['mensagem'] . "\n";
        
        // Reenviar individualmente ou corrigir
        reenviarDps($erro['numeroRps']);
    }
}
```

---

## Exemplos Práticos

### Exemplo 1: Lote Simples (3 DPS)

```php
<?php

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

$config = Config::fromArray([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'ambiente' => 2
]);

$tools = new Tools($config);
$listaDps = [];

// Criar 3 DPS
for ($i = 1; $i <= 3; $i++) {
    $dps = new Make();
    $dps->setPrestador(['cnpj' => '12345678901234', 'im' => '123456']);
    $dps->setTomador([
        'cpfCnpj' => str_pad($i, 11, '0', STR_PAD_LEFT),
        'razaoSocial' => "Cliente {$i}"
    ]);
    $dps->setServico([
        'itemListaServico' => '01.01',
        'discriminacao' => "Serviço {$i}",
        'valorServicos' => 1000.00 * $i
    ]);
    
    $listaDps[] = $dps->getXML();
}

// Enviar lote
$response = $tools->enviarLoteDps($listaDps, 'LOTE001');
$protocolo = $response->dados['protocolo'];

// Aguardar
sleep(5);

// Consultar
$resultado = $tools->consultarLote($protocolo);
echo "NFSes geradas: " . count($resultado->dados['nfses']) . "\n";
```

---

### Exemplo 2: Lote Grande (50 DPS) com Loop

```php
<?php

$listaDps = [];
$clientes = obterListaClientes(); // Busca do banco

foreach ($clientes as $index => $cliente) {
    if ($index >= 50) break; // Limite de 50
    
    $dps = new Make();
    $dps->setPrestador(['cnpj' => '12345678901234', 'im' => '123456']);
    $dps->setTomador([
        'cpfCnpj' => $cliente['cnpj'],
        'razaoSocial' => $cliente['nome'],
        'email' => $cliente['email']
    ]);
    $dps->setServico([
        'itemListaServico' => $cliente['codigo_servico'],
        'discriminacao' => $cliente['descricao'],
        'valorServicos' => $cliente['valor']
    ]);
    
    $listaDps[] = $dps->getXML();
}

$response = $tools->enviarLoteDps($listaDps, 'LOTE' . time());
// ... continuar processo
```

---

### Exemplo 3: Processamento com Fila (Recomendado)

```php
<?php

// 1. Adicionar lote na fila
$job = [
    'protocolo' => null,
    'listaDps' => $listaDps,
    'numeroLote' => 'LOTE' . time(),
    'status' => 'pendente'
];

adicionarNaFila($job);

// 2. Worker processa (pode ser cronjob)
function processarFila() {
    $jobs = buscarJobsPendentes();
    
    foreach ($jobs as $job) {
        // Enviar lote
        $response = $tools->enviarLoteDps(
            $job['listaDps'],
            $job['numeroLote']
        );
        
        // Atualizar com protocolo
        atualizarJob($job['id'], [
            'protocolo' => $response->dados['protocolo'],
            'status' => 'enviado'
        ]);
    }
}

// 3. Outro worker consulta resultados
function consultarResultados() {
    $jobs = buscarJobsEnviados();
    
    foreach ($jobs as $job) {
        $resultado = $tools->consultarLote($job['protocolo']);
        
        if ($resultado->dados['situacao'] === 'Processado') {
            // Salvar NFSes
            salvarNfses($resultado->dados['nfses']);
            
            // Marcar como concluído
            atualizarJob($job['id'], ['status' => 'concluido']);
        }
    }
}
```

---

## Tratamento de Erros

### Erros no Envio do Lote

```php
$response = $tools->enviarLoteDps($listaDps, $numeroLote);

if (!$response->sucesso) {
    switch ($response->codigoErro) {
        case 'E001':
            // Lista vazia
            echo "Erro: Nenhuma DPS fornecida\n";
            break;
            
        case 'E002':
            // Limite excedido
            echo "Erro: Máximo 50 DPS por lote\n";
            // Dividir em múltiplos lotes
            break;
            
        case 'E003':
            // Certificado inválido
            echo "Erro: Certificado digital inválido ou vencido\n";
            break;
            
        default:
            echo "Erro: " . $response->mensagem . "\n";
    }
}
```

---

### Erros em DPS Individuais do Lote

```php
$resultado = $tools->consultarLote($protocolo);

if ($resultado->dados['quantidadeComErro'] > 0) {
    foreach ($resultado->dados['erros'] as $erro) {
        echo "DPS com erro:\n";
        echo "  RPS: " . $erro['numeroRps'] . "\n";
        echo "  Código: " . $erro['codigo'] . "\n";
        echo "  Mensagem: " . $erro['mensagem'] . "\n";
        
        // Estratégias de tratamento:
        
        // 1. Reenviar individualmente (síncrono)
        if ($erro['codigo'] === 'E_TEMP') {
            $dpsXml = buscarDpsPorNumero($erro['numeroRps']);
            $resposta = $tools->enviarDps($dpsXml);
        }
        
        // 2. Corrigir e reenviar
        if ($erro['codigo'] === 'E_CNPJ_INVALIDO') {
            corrigirCnpj($erro['numeroRps']);
            // Incluir em próximo lote
        }
        
        // 3. Notificar usuário
        if ($erro['codigo'] === 'E_DADOS_INCOMPLETOS') {
            notificarErro($erro);
        }
    }
}
```

---

## Boas Práticas

### 1. Tamanho Ideal do Lote

```php
// ✅ BOM: 20-30 DPS por lote
$listaDps = array_slice($todasDps, 0, 25);

// ⚠️  EVITAR: Lotes muito pequenos (ineficiente)
$listaDps = [$dps1]; // Use envio individual

// ⚠️  EVITAR: Lotes no limite (50) - mais risco de timeout
$listaDps = array_slice($todasDps, 0, 50);
```

---

### 2. Controle de Lotes no Banco de Dados

```sql
CREATE TABLE lotes_nfse (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_lote VARCHAR(50) UNIQUE,
    protocolo VARCHAR(100),
    quantidade_dps INT,
    status ENUM('pendente', 'enviado', 'processando', 'concluido', 'erro'),
    data_envio DATETIME,
    data_processamento DATETIME,
    tentativas INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE dps_lote (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lote_id INT,
    numero_rps VARCHAR(50),
    status ENUM('pendente', 'sucesso', 'erro'),
    numero_nfse VARCHAR(50),
    codigo_verificacao VARCHAR(50),
    mensagem_erro TEXT,
    FOREIGN KEY (lote_id) REFERENCES lotes_nfse(id)
);
```

---

### 3. Retry com Backoff Exponencial

```php
function consultarLoteComRetry($protocolo, $maxTentativas = 10) {
    $tentativa = 0;
    $aguardar = 2; // Começa com 2 segundos
    
    while ($tentativa < $maxTentativas) {
        $resultado = $tools->consultarLote($protocolo);
        
        if ($resultado->dados['situacao'] === 'Processado') {
            return $resultado;
        }
        
        $tentativa++;
        echo "Tentativa {$tentativa}: Aguardando {$aguardar}s...\n";
        sleep($aguardar);
        
        // Backoff exponencial: 2, 4, 8, 16, 32...
        $aguardar = min($aguardar * 2, 60); // Máximo 60s
    }
    
    throw new Exception("Timeout ao consultar lote");
}
```

---

### 4. Processamento Assíncrono com Queue

```php
// Usar Laravel Queue, RabbitMQ, Redis Queue, etc.

// Enviar para fila
Queue::push(new ProcessarLoteJob($listaDps, $numeroLote));

// Job
class ProcessarLoteJob {
    public function handle() {
        // 1. Enviar lote
        $response = $this->tools->enviarLoteDps(...);
        
        // 2. Agendar consulta futura
        ConsultarLoteJob::dispatch($response->protocolo)
            ->delay(now()->addSeconds(10));
    }
}

class ConsultarLoteJob {
    public function handle() {
        $resultado = $this->tools->consultarLote($this->protocolo);
        
        if ($resultado->dados['situacao'] !== 'Processado') {
            // Reagendar consulta
            self::dispatch($this->protocolo)
                ->delay(now()->addSeconds(10));
        } else {
            // Processar resultados
            $this->processarNfses($resultado->dados['nfses']);
        }
    }
}
```

---

### 5. Monitoramento e Logs

```php
// Registrar todas as operações
Log::info('Lote enviado', [
    'numero_lote' => $numeroLote,
    'protocolo' => $protocolo,
    'quantidade' => count($listaDps)
]);

// Métricas
Metrics::increment('nfse.lotes.enviados');
Metrics::gauge('nfse.lotes.pendentes', count($lotesPendentes));
Metrics::histogram('nfse.lote.tempo_processamento', $tempoDecorrido);

// Alertas
if ($resultado->dados['quantidadeComErro'] > 10) {
    Alert::send('Alto índice de erros no lote ' . $numeroLote);
}
```

---

## FAQ

### Q1: Qual o limite de DPS por lote?
**R:** Até **50 DPS** por lote.

---

### Q2: Quanto tempo leva para processar um lote?
**R:** De **2 a 30 segundos**, dependendo da quantidade de DPS e carga do servidor.

---

### Q3: Posso enviar múltiplos lotes simultaneamente?
**R:** Sim, mas recomenda-se aguardar o processamento de um lote antes de enviar outro para evitar sobrecarga.

---

### Q4: O que acontece se uma DPS do lote falhar?
**R:** As demais DPS são processadas normalmente. A DPS com erro retorna na lista de erros da consulta.

---

### Q5: Preciso aguardar o processamento ou posso fazer depois?
**R:** Você pode fazer a consulta a qualquer momento usando o protocolo. Recomenda-se implementar um sistema de fila para processar assincronamente.

---

### Q6: Posso cancelar um lote em processamento?
**R:** Não. Uma vez enviado, o lote será processado. Você pode cancelar as NFSes individualmente após a emissão.

---

### Q7: Como saber se o lote está pronto?
**R:** Consultando pelo protocolo. Quando `situacao === 'Processado'`, o lote está pronto.

---

### Q8: Posso consultar lotes antigos?
**R:** Sim, usando o protocolo. Recomenda-se guardar os protocolos no banco de dados.

---

### Q9: Qual a diferença entre enviar 50 DPS individuais vs 1 lote de 50?
**R:** 
- **Individual:** 50 requisições, resposta imediata para cada uma
- **Lote:** 1 requisição + 1 consulta, mais eficiente, mas assíncrono

---

### Q10: Devo sempre usar lote?
**R:** 
- **Use lote:** Para 10+ NFSes
- **Use individual:** Para 1-5 NFSes ou quando precisar de resposta imediata

---

## Resumo

```
PROCESSO EM LOTE (6 passos):

1. Criar múltiplas DPS (até 50)
2. Enviar lote → Receber protocolo
3. Aguardar processamento (assíncrono)
4. Consultar resultado pelo protocolo
5. Processar NFSes geradas
6. Tratar erros (se houver)

VANTAGENS:
✅ Eficiente para alto volume
✅ Menos requisições ao servidor
✅ Processamento otimizado

DESVANTAGENS:
⏳ Não é imediato
⏳ Mais complexo de implementar
⏳ Requer sistema de fila/polling
```

---

Para mais informações, consulte os exemplos em `/examples/exemplo_lote.php`

