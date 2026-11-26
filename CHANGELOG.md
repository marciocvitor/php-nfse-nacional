# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

---

## [2.0.1] - 2026-11-26

### Adicionado

- Alíquota do ISS (`pAliq`) na DPS. Informe em `setServico()` com a chave `aliquota` (nome usado pelo ERP) ou `pAliq` (nome do XSD), em percentual de 0 a 9,99 com até 2 casas decimais. Opcional no leiaute; obrigatória para municípios fora do Sistema Nacional.
- Uma nova chamada de `setServico()` com alíquota substitui a anterior.

### Corrigido

- Chaves desconhecidas em `setIdentificacao()` e `setServico()` (ex.: `tipo`, `valorIss`, `natOp`) continuam sendo ignoradas, agora com teste de regressão.

---

## [2.0.0] - 2026-11-26

### Alterado (Breaking Changes)

- **Leiaute NT 009 v1.01** - Schemas XSD substituídos pelos oficiais de produção restrita (publicados em 24/11/2026). `DPS_v1.01.xsd` é o schema de validação da DPS.
- **Envio de lote (ABRASF) removido** - Métodos `enviarLoteDps()`, `consultarLote()`, `consultarNfse()`, `gerarXmlLote()`, `gerarXmlConsultaLote()` e `gerarXmlConsultaNfse()` foram removidos. Use `enviarDps()` (uma DPS por requisição).
- **Padrão ABRASF removido** - Schemas `EnviarDpsEnvio.xsd` e `EnviarLoteRpsEnvio.xsd` excluídos.
- **Classe `Make`**:
  - Parâmetro `tipo` removido de `setIdentificacao()`; `serie` agora é numérica (ex.: `'1'`).
  - `setServico()`: `itemListaServico` substituído por `cTribNac` (obrigatório, 6 dígitos); `valorIss`/`aliquota`/`natOp`/`incentivadorCultural` removidos (cálculo de tributos fica a cargo do leiaute NT 009).
  - `setCLocEmi()` (código IBGE do município emissor) obrigatório.
  - `setPrestador()`: exige `opSimpNac`, `regEspTrib` (e `regApTribSN` quando optante pelo Simples Nacional).
  - Métodos `getDPSXML()` e `getXML()` geram o XML da DPS (sem wrapper `infNFSe`).
- **CNPJ alfanumérico** aceito em prestador e tomador (`[0-9A-Z]{14}`), conforme NT 009.
- **`cancelarNfse()`** agora recebe o código do motivo: `cancelarNfse(string $chaveAcesso, string $motivo, int $cMotivo = 2)`. Endpoint: `POST /nfse/{chaveAcesso}/eventos`.
- **Validação de negócio** (`DpsValidator`): erros agregados em `ValidationException` com mensagens em lista.
- Extensão `ext-soap` não é mais requerida.

### Corrigido

- Geração de DPS com namespace `http://www.sped.fazenda.gov.br/nfse` sem `xmlns=""` indevido.
- ID da DPS (45 caracteres: `DPS` + código IBGE + tipo de inscrição + CNPJ + série + número) e da assinatura conforme XSD.
- Evento de cancelamento (`pedRegEvento` / `e101101`) gerado conforme o schema oficial.

### Adicionado (NT 009)

- Testes automatizados com PHPUnit (`composer test`) incluindo validação contra os schemas oficiais.
- `phpunit.xml.dist`.

### Migração REST (versão em desenvolvimento)

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

