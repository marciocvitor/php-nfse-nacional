# Como Testar URLs de WSDL do Portal Nacional NFSe

Este guia explica como testar e verificar se as URLs dos WSDLs estão corretas e acessíveis.

## Script de Teste Automático

Execute o script `test_wsdl_urls.php` para testar automaticamente várias variações de URLs:

```bash
php test_wsdl_urls.php
```

O script irá:
1. Testar múltiplas variações de URLs para homologação e produção
2. Verificar se as URLs estão acessíveis (HTTP 200-399)
3. Validar se o conteúdo é um WSDL válido
4. Tentar criar um SoapClient para confirmar que é um WSDL funcional
5. Exibir um resumo com as URLs válidas encontradas

## Exemplo de Saída

```
=== TESTE DE URLs WSDL - PORTAL NACIONAL NFSe ===

--- TESTANDO URLs DE HOMOLOGAÇÃO ---

✓ [HTTP 200] https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl ✓ SOAP
    Content-Type: application/xml

✗ [HTTP 404] https://homologacao.nfse.gov.br/RecepcionarDps.svc?wsdl
    Erro: HTTP 404

=== RESUMO ===

✓ URLs de HOMOLOGAÇÃO válidas encontradas:
  - https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl
```

## Teste Manual com cURL

Você também pode testar manualmente usando cURL:

```bash
# Teste básico de acesso
curl -I https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl

# Teste com download do conteúdo
curl https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl

# Teste com verificação SSL desabilitada (útil em homologação)
curl -k https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl
```

## Teste Manual no Navegador

1. Abra seu navegador
2. Acesse a URL do WSDL diretamente
3. Se o WSDL for válido, você verá o XML do WSDL
4. Se der erro 404 ou DNS, a URL está incorreta

Exemplo:
```
https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl
```

## Teste com PHP SoapClient

Crie um script PHP simples para testar:

```php
<?php
$url = 'https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl';

try {
    $client = new SoapClient($url, [
        'cache_wsdl' => WSDL_CACHE_NONE,
        'trace' => true,
    ]);
    
    echo "✓ WSDL válido!\n";
    echo "Operações disponíveis:\n";
    print_r($client->__getFunctions());
    
} catch (\SoapFault $e) {
    echo "✗ Erro ao carregar WSDL: " . $e->getMessage() . "\n";
}
```

## Variações de URL Comuns

O Portal Nacional pode usar diferentes formatos de URL. O script testa:

### Formato 1: `/wsdl/Operacao.wsdl`
```
https://homologacao.nfse.gov.br/wsdl/RecepcionarDps.wsdl
https://api.nfse.gov.br/wsdl/RecepcionarDps.wsdl
```

### Formato 2: `.svc?wsdl` (WCF)
```
https://homologacao.nfse.gov.br/RecepcionarDps.svc?wsdl
https://api.nfse.gov.br/RecepcionarDps.svc?wsdl
```

### Formato 3: `.svc?singleWsdl` (WCF Single)
```
https://homologacao.nfse.gov.br/RecepcionarDps.svc?singleWsdl
```

### Formato 4: Portal gov.br
```
https://www.gov.br/nfse/homologacao/wsdl/RecepcionarDps.wsdl
https://www.gov.br/nfse/producao/wsdl/RecepcionarDps.wsdl
```

## Onde Encontrar as URLs Corretas

1. **Portal Nacional de NFSe**
   - URL: https://www.gov.br/nfse/pt-br
   - Seção: Documentação Técnica
   - Manual de Integração

2. **Notas Técnicas (NTs)**
   - Consulte as NTs mais recentes
   - Geralmente contêm informações sobre endpoints

3. **Suporte do Portal Nacional**
   - Entre em contato se não encontrar nas documentações
   - E-mail ou telefone disponível no portal

## Configurando URLs no Código

Após identificar as URLs corretas, configure no `Config`:

```php
$config = Config::fromArray([
    // ... outros parâmetros ...
    'wsdl' => [
        'homologacao' => [
            'RecepcionarDps' => 'URL_VALIDA_AQUI',
            'RecepcionarLoteRps' => 'URL_VALIDA_AQUI',
            'ConsultarLoteRps' => 'URL_VALIDA_AQUI',
            'ConsultarNfse' => 'URL_VALIDA_AQUI',
            'CancelarNfse' => 'URL_VALIDA_AQUI',
        ],
        'producao' => [
            // URLs de produção
        ]
    ]
]);
```

## Troubleshooting

### Erro: DNS_PROBE_POSSIBLE
- **Causa**: Domínio não existe ou não está resolvendo
- **Solução**: Verifique se a URL está correta na documentação oficial

### Erro: HTTP 404
- **Causa**: URL não encontrada no servidor
- **Solução**: Teste outras variações de URL ou consulte a documentação

### Erro: SSL/TLS
- **Causa**: Problema com certificado SSL
- **Solução**: Em homologação, pode ser necessário desabilitar verificação SSL

### Erro: Timeout
- **Causa**: Servidor não responde ou está sobrecarregado
- **Solução**: Tente novamente mais tarde ou verifique sua conexão

## Próximos Passos

1. Execute o script de teste: `php test_wsdl_urls.php`
2. Identifique as URLs válidas no resumo
3. Configure as URLs no seu `Config`
4. Teste a emissão de uma NFSe de teste

---

**Dúvidas?** Consulte a documentação oficial do Portal Nacional ou abra uma issue no GitHub.

