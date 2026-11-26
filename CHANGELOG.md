# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

---

## [Não lançado]

### Adicionado

- **Cabeçalho do DANFSe local no padrão nacional (referência NFS-e 3275).** Quatro colunas: logo NFS-e | "DANFSe v1.0 - Documento Auxiliar da NFS-e" | brasão e "Prefeitura Municipal de {xLocEmi}" | QR Code com a legenda de autenticidade.
  - **Logo nacional embutido** em `src/Pdf/imagens/nfse-logo.png` (PNG 540 x 107 com transparência) e carregado automaticamente; não há parâmetro nem dependência externa.
  - **QR Code gerado automaticamente** a partir da chave de acesso, apontando para `https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave={chave}`. Usa o `TCPDF2DBarcode` já presente (tecnickcom/tcpdf), sem dependência nova, e o resultado é reprodutível para a mesma chave.
  - **Brasão do município opcional**, informado por quem usa a biblioteca (o RP/ERP), pois não consta no XML autorizado nem na API de parametrização. Uso: `new DanfseGeneratorNacional(['brasao' => $png])` ou `->setBrasao($png)`; aceita caminho de arquivo, bytes do PNG ou data URI `data:image/png;base64,...`. Sem brasão, o layout segue normalmente, sem a imagem. `setBrasao(null)` remove o brasão.
  - `Tools::obterDanfse(..., ?string $brasao = null)` repassa o brasão e o retorno ganhou a chave `avisos` (lista de textos em português; vazia quando está tudo certo).
- **Padrão exigido para o brasão** (constantes `BRASAO_*` e `DanfseGeneratorNacional::padraoBrasao()`):
  - formato **PNG** (JPEG, GIF, BMP, WEBP etc. são recusados);
  - no máximo **300 x 300 pixels** (recomendado **192 x 192**);
  - no máximo **60 KB** (61.440 bytes);
  - arquivo íntegro, que possa ser lido como imagem.
- **Validação do brasão.** Brasão fora do padrão (formato inválido, arquivo corrompido ou truncado, dimensão ou tamanho excessivo, caminho inexistente) não é carregado: `setBrasao()`/construtor lançam `ValidationException` com mensagem em português que diz o que está errado e qual é o padrão esperado, e o brasão anterior, se houver, é mantido. `Tools::obterDanfse()` não lança: gera o DANFSe sem o brasão e devolve a mensagem em `avisos`. A geração do DANFSe nunca é interrompida por causa do brasão.

---

## [2.0.3] - 2026-11-26

### Adicionado

- **Marca d'água "CANCELADA" no DANFSe local.** `DanfseGeneratorNacional::gerar($response, $dadosAdicionais = [], bool $canceled = false)` e `Tools::obterDanfse($chave, $xml, $dadosAdicionais = [], bool $canceled = false)` aceitam o novo parâmetro opcional. Com `true`, o PDF recebe a palavra CANCELADA em vermelho (`#fb0000`), diagonal (50°), tamanho 120 e opacidade 0,4, desenhada no próprio mPDF antes do `WriteHTML` (o PDF pronto não é reaberto). O status de cancelamento não consta no XML autorizado, então quem usa a biblioteca precisa informá-lo (no ERP, `nfse_cod_status = 135`).
- Sem o parâmetro, ou com `false`, o PDF sai igual ao da 2.0.2. O PDF oficial da API, quando disponível, vem pronto e não recebe a marca.

---

## [2.0.2] - 2026-11-26

Correções confirmadas em homologação (SEFIN produção restrita, `tpAmb = 2`).

### Corrigido

- **DPS com `tpEmit = 1`** - `Make` não emite mais `xNome` nem o grupo `end` do prestador, que a SEFIN rejeitava (E0121 e E0128). Telefone e e-mail continuam sendo enviados. A razão social deixou de ser exigida em `setPrestador()`; ela continua obrigatória em `getDPSXML()` quando `tpEmit` é diferente de 1, caso em que `xNome` e o endereço são emitidos normalmente.
- **Cancelamento** - o `Id` do `pedRegEvento` agora é `PRE` + chave + `101101` (59 caracteres, conforme `TSIdPedRegEvt`). Antes o sufixo `nPedRegEvento` levava o `Id` a 62 caracteres e a SEFIN respondia E1235.
- **Alíquota zero** - `pAliq` não é mais emitido quando a alíquota informada é zero (`0`, `'0'`, `'0.00'`, `'0.0000'`), evitando o erro E0625.
- **DANFSe local** - `DanfseGeneratorNacional` lia vazios os valores da DPS (`vServ`, tributação e ISSQN) em XML autorizado real, pois a DPS vem aninhada em `infNFSe`. Corrigida a leitura; há teste com a estrutura real.

### Adicionado

- `DanfseIndisponivelException` (estende `CommunicationException`). `Tools::baixarDanfse()` a lança em falhas de conexão/TLS (ex.: `sslv3 alert handshake failure`) e em respostas 5xx/410, pois a API de download do DANFSe foi descontinuada em 03/08/2026 (NT 008 v1.02). Depois da primeira falha a instância não tenta de novo.
- `Tools::obterDanfse($chave, $xmlNfse)` - tenta a API uma vez e, se estiver indisponível, gera o PDF com `DanfseGeneratorNacional`. Retorna `['pdf' => ..., 'origem' => 'api'|'local']`.

### Alterado

- `mpdf/mpdf ^8.0` passa a ser declarado em `require` (já era usado por `DanfseGeneratorNacional`).

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

