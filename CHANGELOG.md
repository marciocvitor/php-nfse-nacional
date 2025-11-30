# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

---

## [2.0.0] - 2025-01-XX

### Alterado (Breaking Changes)

- **Migração completa de SOAP/WSDL para REST API** - O Portal Nacional usa APIs REST, não SOAP
- Removida configuração de `wsdl` - URLs são automáticas baseadas no ambiente
- URLs da API configuradas automaticamente:
  - Homologação: `https://sefin.producaorestrita.nfse.gov.br`
  - Produção: `https://sefin.nfse.gov.br`
- Formato de envio de DPS: XML compactado em GZIP e codificado em Base64 (campo `dpsXmlGZipB64`)
- Endpoints REST: `/SefinNacional/nfse`, `/SefinNacional/lote`, etc.

### Adicionado

- Método `consultarDps()` - Consulta DPS por ID para obter chave de acesso
- Método `consultarNfsePorChave()` - Consulta NFSe pela chave de acesso
- Método `baixarDanfse()` - Baixa DANFSe (PDF) oficial diretamente da API
- Suporte a compressão GZIP+Base64 para envio de DPS
- Configuração opcional `apiBaseUrl` para URLs customizadas
- Método `getDPSXML()` na classe `Make` - Gera XML apenas da DPS (sem wrapper NFSe/infNFSe)

### Removido

- `SoapClient` - Removido completamente
- Método `callSoap()` - Substituído por `callRest()`
- Método `getWsdl()` - Não é mais necessário
- Configuração de `wsdl` no `Config` - URLs são automáticas
- Extensão PHP `ext-soap` - Não é mais necessária

### Nota Técnica

- **Migração REST API**: Adaptação para a arquitetura REST do Portal Nacional de NFSe

---

## [1.0.0] - 2025-11-14

### Adicionado

- Sistema completo de emissão de NFSe no Portal Nacional (padrão ABRASF)
- Validação pré-envio de XMLs contra schemas XSD oficiais
- Assinatura digital XML (XMLDSig SHA256/RSA) com certificados A1
- Envio de DPS individual (síncrono)
- Envio de lote de DPS (assíncrono, até 50 DPS)
- Consulta de lote por protocolo
- Consulta de NFSe individual
- Cancelamento de NFSe
- Sistema de log automático com data, versão e NT
- Arquivos de controle de versão (`VERSION.txt` e `NT.txt`)
- Documentação completa (README, MANUAL-LOTE, INSTALL, QUICK-START)
- Exemplos práticos de uso

### Nota Técnica

- **NT inicial - Layout 1.00**: Padrão ABRASF Portal Nacional NFSe

### Schemas

- `EnviarDpsEnvio.xsd` (versão 1.00)
- `EnviarLoteRpsEnvio.xsd` (versão 1.00)

---

## Formato de Entrada

Quando novas versões forem lançadas, adicione uma nova entrada seguindo este formato:

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Adicionado
- Nova funcionalidade 1
- Nova funcionalidade 2

### Alterado
- Mudança em funcionalidade existente

### Corrigido
- Correção de bug 1

### Nota Técnica
- **NT XXXX.YYY v.Z.ZZ**: Descrição da NT aplicada

### Schemas
- `Schema.xsd` (versão X.XX)
```

---

[1.0.0]: https://github.com/marciocvitor/php-nfse-nacional/releases/tag/v1.0.0

