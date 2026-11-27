# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

---

## [Não lançado]

### Adicionado

- **Cabeçalho do DANFSe local no padrão do portal nacional (DANFSe v2.0).** Logo NFS-e | "DANFSe v2.0 - Documento Auxiliar da NFS-e" | Município (nome - UF), Ambiente Gerador e Tipo de Ambiente | QR Code com a legenda de autenticidade. Sem brasão, como no PDF oficial baixado do portal.
  - **Logo nacional embutido** em `src/Pdf/imagens/nfse-logo.png` (PNG 540 x 107 com transparência) e carregado automaticamente; não há parâmetro nem dependência externa.
  - **QR Code gerado automaticamente** a partir da chave de acesso, apontando para `https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave={chave}`. Usa o `TCPDF2DBarcode` já presente (tecnickcom/tcpdf), sem dependência nova, e o resultado é reprodutível para a mesma chave.
  - **Dados lidos do XML autorizado:** município de `infNFSe/xLocEmi` e UF de `infNFSe/emit/enderNac/UF`; Ambiente Gerador de `infNFSe/ambGer`; Tipo de Ambiente de `infDPS/tpAmb`. O que não vier no XML aparece como "-". A versão "v2.0" é a do leiaute do DANFSe reproduzido (o XML só traz `versao="1.01"`, que é a do esquema da NFS-e, e não é exibida).
- **Rodapé no padrão do portal nacional**, fixo no fim da página: faixa com "DATA CIENTIFICAÇÃO:", "IDENTIFICAÇÃO E ASSINATURA" e "Nº NFS-e / CHAVE NFS-e". Substitui os textos antigos de código de verificação e de representação gráfica.
- **Informações complementares** passam a trazer a linha "Totais aproximados dos Tributos cfe. Lei n° 12.741/2012: Federais; Estaduais; Municipais" (valores de `vTotTrib` da DPS quando informados, "-" quando não).
- Opção `verificarSsl` em `Config` (padrão `true`).
- `phpcs.xml.dist`, `phpstan.neon.dist` e `.gitattributes` (fim de linha LF e pacote do Composer sem testes/exemplos).

### Alterado

- **Verificação TLS ligada também em homologação.** Antes o certificado do servidor só era verificado em produção. Para desligar (apenas depuração), use `'verificarSsl' => false`.
- **Nome do município do prestador vem do próprio XML** (`xLocEmi`, `xLocPrestacao` ou `xLocIncid` com o mesmo código IBGE); sem nome no XML, mostra o código IBGE e a UF. O gerador não depende mais da classe `Db_conn` do ERP nem de consulta SQL.
- `DanfseGeneratorNacional` cria um mPDF novo a cada `gerar()`: a mesma instância pode gerar vários PDFs (antes o mPDF do construtor era reaproveitado entre chamadas).
- Rótulo "Descrição do Serviço" no quadro do serviço.
- `composer.json` declara as extensões usadas (`ext-zlib`, `ext-gd`, `ext-mbstring`) e exige `squizlabs/php_codesniffer` ^3.13.6 (CVE-2026-67434 nas anteriores).
- CI falha quando testes, PHPStan ou PSR-12 falham (antes os passos tinham `continue-on-error`).
- `DanfseGenerator` (leiaute ABRASF) e `Tools::gerarDanfse()`, `gerarDanfseArquivo()` e `gerarDanfseBase64()` marcados como `@deprecated`; para a NFS-e Nacional use `Tools::obterDanfse()`.

### Corrigido

- **Valores do XML escapados no HTML do DANFSe.** Nomes, endereços e descrição do serviço com `<`, `>` ou `&` eram inseridos crus no HTML do mPDF (podiam quebrar o leiaute ou fazer o mPDF buscar recursos externos).
- Número da DPS no log de emissão lido de `nDPS` (antes procurava a tag ABRASF `Numero` e registrava sempre "N/A").
- Mensagens de erro estruturadas da API de eventos: o erro de schema `RNG9997` ganha a mesma explicação legível já usada na emissão.
- Arquivo PEM temporário criado com permissão `0600`; `DanfseGenerator::gerarArquivo()` lança exceção quando não consegue gravar.
- Código morto e comentários obsoletos removidos do gerador do DANFSe; formatação PSR-12 em todo o código.

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

