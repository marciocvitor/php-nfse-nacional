# Migração de SOAP/WSDL para REST API

## ⚠️ MUDANÇA IMPORTANTE

O projeto foi **completamente refatorado** para usar **APIs REST** ao invés de SOAP/WSDL, conforme a arquitetura atual do Portal Nacional de NFSe.

## O que mudou?

### ❌ ANTES (SOAP/WSDL - INCORRETO)
- Usava `SoapClient` do PHP
- Tentava acessar WSDLs que não existem
- URLs como `https://homologacao.nfse.gov.br/wsdl/...`

### ✅ AGORA (REST API - CORRETO)
- Usa `GuzzleHttp\Client` para chamadas REST
- Endpoints REST oficiais do Portal Nacional
- URLs corretas: `https://adn.nfse.gov.br` e `https://adn.producaorestrita.nfse.gov.br`

## URLs Corretas

### Homologação (Produção Restrita)
```
https://adn.producaorestrita.nfse.gov.br
```

### Produção
```
https://adn.nfse.gov.br
```

## Endpoints da API

Baseado na documentação Swagger: https://www.nfse.gov.br/swagger/contribuintesissqn/

- **POST /nfse** - Recepciona DPS e gera NFSe (síncrono)
- **POST /lote** - Recepciona lote de DPS (assíncrono)
- **POST /lote/consulta** - Consulta resultado do lote
- **POST /nfse/consulta** - Consulta NFSe individual
- **POST /nfse/cancelamento** - Cancela NFSe

## Mudanças no Código

### Config.php

**ANTES:**
```php
'wsdl' => [
    'homologacao' => [
        'RecepcionarDps' => 'https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl',
    ]
]
```

**AGORA:**
```php
// URLs são automáticas baseadas no ambiente
// Ou configure manualmente:
'apiBaseUrl' => 'https://adn.producaorestrita.nfse.gov.br' // opcional
```

### Tools.php

**ANTES:**
```php
$soapResult = $this->callSoap('RecepcionarDps', $signedXml);
```

**AGORA:**
```php
$restResult = $this->callRest('POST', '/nfse', $signedXml);
```

## O que permanece igual?

✅ Geração de XML (DPS) - **mantido**
✅ Assinatura digital XML - **mantido**
✅ Validação de schemas XSD - **mantido**
✅ Estrutura de Response - **mantido**
✅ Logging - **mantido**
✅ Geração de DANFSe/PDF - **mantido**

## O que foi removido?

❌ `SoapClient` - removido
❌ Método `callSoap()` - removido
❌ Método `getWsdl()` - removido
❌ Validação de WSDL - removida
❌ Configuração de `wsdl` no Config - removida

## Como usar agora?

### Exemplo Básico

```php
<?php
require 'vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

// Configuração (sem WSDL!)
$config = Config::fromArray([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'cmun' => '3550308',
    'razaosocial' => 'Minha Empresa LTDA',
    'ambiente' => 2, // 1=Produção, 2=Homologação
    'certPfx' => __DIR__ . '/certificado.pfx',
    'certPassword' => 'senha',
    // apiBaseUrl é opcional - usa padrão se não informado
    // 'apiBaseUrl' => 'https://adn.producaorestrita.nfse.gov.br'
]);

$tools = new Tools($config);

// Gera DPS
$dps = (new Make())
    ->setIdentificacao(['numero' => '123', 'serie' => 'A1', 'tipo' => 1])
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
        'discriminacao' => 'Serviço de desenvolvimento',
        'codigoMunicipio' => '3550308',
        'valorServicos' => '1000.00',
        'valorIss' => '50.00',
        'aliquota' => '0.0500'
    ]);

// Envia via REST API
$response = $tools->enviarDps($dps->getXML());

if ($response->sucesso) {
    echo "NFSe emitida: " . $response->numero . "\n";
} else {
    echo "Erro: " . $response->mensagem . "\n";
}
```

## Documentação Oficial

- **Portal Nacional:** https://www.gov.br/nfse/pt-br
- **Swagger API:** https://www.nfse.gov.br/swagger/contribuintesissqn/
- **Manual Integrado:** https://www.gov.br/nfse/pt-br/biblioteca/manuais-e-tutoriais/manual-integrado-nfse

## Autenticação

⚠️ **IMPORTANTE:** O Portal Nacional pode exigir autenticação adicional (token, etc.) além do certificado digital.

Consulte a documentação oficial para:
- Como obter token de autenticação
- Headers adicionais necessários
- Formato de autenticação específico

## Próximos Passos

1. ✅ Código refatorado para REST
2. ⚠️ Verificar se há necessidade de token/autenticação adicional
3. ⚠️ Testar com ambiente real de homologação
4. ⚠️ Ajustar endpoints se necessário conforme documentação oficial

---

**Data da migração:** 2025-01-XX
**Versão:** 2.0.0 (breaking change)

