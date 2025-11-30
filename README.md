# PHP NFSe Nacional

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://php.net)

Biblioteca PHP open source para emissão de **Nota Fiscal de Serviço Eletrônica (NFSe)** no **Portal Nacional** seguindo o padrão **ABRASF**, com validação de schemas e assinatura XML no mesmo nível praticado pela **nfephp-org**.

---

## Instalação

### Via Composer (Repositório Privado)

Adicione o repositório no seu `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/marciocvitor/php-nfse-nacional.git"
        }
    ],
    "require": {
        "php-nfse/nfse-nacional": "dev-main"
    }
}
```

Então execute:

```bash
composer install
```

---

## Uso Básico

### Emissão Individual (Síncrona)

```php
<?php

require 'vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

// Configuração
// Nota: URLs da API são automáticas baseadas no ambiente
// Homologação: https://sefin.producaorestrita.nfse.gov.br
// Produção: https://sefin.nfse.gov.br
$config = Config::fromArray([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'cmun' => '3550308',
    'razaosocial' => 'Minha Empresa LTDA',
    'ambiente' => 2, // 1=Produção, 2=Homologação
    'certPfx' => __DIR__ . '/certificados/empresa-hom.pfx',
    'certPassword' => 'senha',
    // apiBaseUrl é opcional - usa padrão se não informado
    // 'apiBaseUrl' => 'https://sefin.producaorestrita.nfse.gov.br'
]);

$tools = new Tools($config);

// Criar DPS
$dps = new Make();
$dps->setIdentificacao([
    'numero' => '123',
    'serie' => 'A1',
    'tipo' => 1
]);
$dps->setPrestador([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'razaoSocial' => 'Minha Empresa LTDA'
]);
$dps->setTomador([
    'cpfCnpj' => '98765432100',
    'razaoSocial' => 'Cliente',
    'email' => 'cliente@teste.com'
]);
$dps->setServico([
    'itemListaServico' => '01.01',
    'discriminacao' => 'Serviço de desenvolvimento',
    'codigoMunicipio' => '3550308',
    'valorServicos' => 1000.00,
    'valorIss' => 50.00,
    'aliquota' => 0.0500,
    'issRetido' => 2
]);

// Gerar XML e enviar
$xml = $dps->getXML();
$response = $tools->enviarDps($xml);

if ($response->sucesso) {
    echo "NFSe emitida: " . $response->numero;
}
```

---

### Envio em Lote (Assíncrono)

```php
// Criar múltiplas DPS (cada Make gera seu XML já pronto para validação)
$listaDps = [$dps1->getXML(), $dps2->getXML(), $dps3->getXML()];

// Enviar lote (até 50 DPS)
$response = $tools->enviarLoteDps($listaDps, 'LOTE001');
$protocolo = $response->dados['parsed']['Protocolo'] ?? null;

// Consultar resultado
$resultado = $tools->consultarLote($protocolo);
echo "NFS-e geradas: " . count($resultado->dados['parsed']['ListaNfse'] ?? []) . PHP_EOL;
```

**Documentação completa:** [MANUAL-LOTE.md](MANUAL-LOTE.md)
## Requisitos

- PHP >= 8.2
- Certificado Digital A1 (.pfx) válido
- Extensões PHP:
  - `ext-json`
  - `ext-openssl`
  - `ext-dom`
  - `ext-simplexml`
  - `ext-libxml`
  - `ext-zlib` (para compressão GZIP)
- Dependências adicionais:
  - `guzzlehttp/guzzle`
  - `justinrainbow/json-schema`
  - `robrichards/xmlseclibs`

### Endpoints da API REST

O Portal Nacional usa **APIs REST** (não SOAP/WSDL). As URLs são configuradas automaticamente baseadas no ambiente:

- **Homologação:** `https://sefin.producaorestrita.nfse.gov.br`
- **Produção:** `https://sefin.nfse.gov.br`

Você pode configurar manualmente se necessário:

```php
$config = Config::fromArray([
    // ... outros parâmetros
    'apiBaseUrl' => 'https://sefin.producaorestrita.nfse.gov.br' // opcional
]);
```

**Endpoints disponíveis:**
- `POST /SefinNacional/nfse` - Enviar DPS individual (síncrono)
- `POST /SefinNacional/lote` - Enviar lote de DPS (assíncrono)
- `POST /SefinNacional/lote/consulta` - Consultar resultado do lote
- `POST /SefinNacional/nfse/consulta` - Consultar NFSe individual
- `GET /SefinNacional/dps/{idDps}` - Consultar DPS por ID
- `GET /SefinNacional/nfse/{chaveAcesso}` - Consultar NFSe por chave de acesso
- `GET /SefinNacional/danfse/{chaveAcesso}` - Baixar DANFSe (PDF)
- `POST /SefinNacional/nfse/{chaveAcesso}/eventos` - Cancelar NFSe

> Consulte a [documentação oficial do Portal Nacional](https://www.gov.br/nfse/pt-br) e o [Swagger da API](https://www.nfse.gov.br/swagger/contribuintesissqn/) para mais detalhes.

---

## Estrutura do Projeto

```
php-nfse-nacional/
├── composer.json           # Configuração Composer
├── README.md              # Documentação
├── QUICK-START.md         # Guia rápido
├── INSTALL.md             # Guia completo instalação
├── LICENSE                # Licença MIT
├── src/
│   ├── Config/           # Gerenciamento de configuração
│   ├── Tools/            # Comunicação com webservice
│   ├── Make/             # Geração de XML DPS/RPS
│   ├── Security/         # Certificados e assinatura XML
│   ├── Validator/        # Regras de negócio + schemas
│   ├── Schemas/          # XSDs oficiais ABRASF
│   └── Common/           # Classes comuns (Response)
└── examples/
    └── exemplo_basico.php # Exemplo de uso
```

---

## Documentação

- **[GUIA-APLICACAO.md](GUIA-APLICACAO.md)** - ⭐ **Como aplicar a biblioteca no seu projeto** (3 opções)
- **[QUICK-START.md](QUICK-START.md)** - Início rápido em 3 passos
- **[INSTALL.md](INSTALL.md)** - Guia completo de instalação e configuração
- **[MANUAL-LOTE.md](MANUAL-LOTE.md)** - Processo completo de envio em lote e tratamentos
- **[CHANGELOG.md](CHANGELOG.md)** - Histórico de versões e mudanças da biblioteca
- **[NOTAS-TECNICAS.md](NOTAS-TECNICAS.md)** - Histórico de Notas Técnicas (NTs) implementadas
- **[examples/](examples/)** - Exemplos práticos de uso

---

## Conformidade e Funcionalidades

- [x] Assinatura digital XML (XMLDSig SHA256/RSA) com certificados A1
- [x] Validação pré-envio contra schemas oficiais ABRASF
- [x] Envio de DPS individual (síncrono) via REST API
- [x] Envio de lote (até 50 DPS) via REST API (assíncrono)
- [x] Consulta de lote por protocolo
- [x] Consulta de NFSe (`ConsultarNfse`)
- [x] Consulta de DPS por ID (`consultarDps`)
- [x] Consulta de NFSe por chave de acesso (`consultarNfsePorChave`)
- [x] Cancelamento (`CancelarNfse`)
- [x] Download de DANFSe oficial da API (`baixarDanfse`)
- [x] Configuração automática de URLs baseada no ambiente
- [x] Sistema de log com versão e NT (similar ao nfephp-org)
- [x] Geração de DANFSe/PDF (Documento Auxiliar da NFSe)
- [x] API REST (migração completa de SOAP para REST)
- [ ] Integração com filas/persistência (em desenvolvimento)

---

## Sistema de Log e Controle de Versão

A biblioteca possui sistema de log automático que registra todas as operações com **data, versão do layout e Nota Técnica (NT)** aplicada, similar ao nfephp-org.

### Arquivos de Controle

- **`VERSION.txt`**: Versão do layout atual (ex: `1.00`)
- **`NT.txt`**: Nota Técnica aplicada (ex: `NT inicial - Layout 1.00`)

### Arquivo de Log

O log é gerado automaticamente em: `{sys_temp_dir}/nfse-nacional/nfse-operacoes.log`

**Formato do log:**
```
[2025-11-14 15:30:45] EnviarDps              | Versão: 1.00 | NT: NT inicial - Layout 1.00 | DPS enviado com sucesso. NFSe: 12345 | {"numeroDps":"123","numeroNfse":"12345","sucesso":true}
```

### Customizar diretório de log

```php
$tools = new Tools($config, logDir: '/caminho/customizado/logs');
```

### Exemplo de leitura do log

```php
$logger = new \NFSeNacional\Common\Logger();
echo "Log file: " . $logger->getLogFile() . PHP_EOL;
echo "Versão atual: " . $logger->getVersaoLayout() . PHP_EOL;
echo "NT aplicada: " . $logger->getNotaTecnica() . PHP_EOL;
```

**Importante:** 
- Ao atualizar a biblioteca para uma nova NT, atualize os arquivos `VERSION.txt` e `NT.txt` na raiz do projeto
- Registre a nova NT no arquivo [NOTAS-TECNICAS.md](NOTAS-TECNICAS.md)
- Documente as mudanças no [CHANGELOG.md](CHANGELOG.md)

---

## Geração de DANFSe (PDF)

A biblioteca gera automaticamente o **DANFSe (Documento Auxiliar da Nota Fiscal de Serviço Eletrônica)** em PDF a partir do XML da NFSe.

### Uso Básico

```php
// Após emitir ou consultar uma NFSe
$response = $tools->enviarDps($xml);

// Gerar PDF e salvar em arquivo
$tools->gerarDanfseArquivo($response, '/caminho/danfse.pdf');

// Ou obter como string (para download direto)
$pdfContent = $tools->gerarDanfse($response);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="danfse.pdf"');
echo $pdfContent;

// Ou obter como base64 (para exibir em HTML)
$pdfBase64 = $tools->gerarDanfseBase64($response);
echo '<iframe src="data:application/pdf;base64,' . $pdfBase64 . '"></iframe>';
```

### Dados Adicionais

Você pode passar dados adicionais que não estão no XML:

```php
$dadosAdicionais = [
    'prestador' => [
        'razaoSocial' => 'Minha Empresa LTDA'
    ]
];

$tools->gerarDanfseArquivo($response, '/caminho/danfse.pdf', $dadosAdicionais);
```

### Conteúdo do DANFSe

O PDF gerado contém:
- Cabeçalho com número e código de verificação
- Dados do prestador (CNPJ, IM, Razão Social)
- Dados do tomador (CPF/CNPJ, Razão Social, E-mail)
- Dados do serviço (Item da lista, Discriminação, Código do município)
- Valores (Valor dos serviços, Alíquota, Valor do ISS, ISS Retido)
- Rodapé com código de verificação e link para consulta

**Exemplo completo:** [examples/exemplo_danfse.php](examples/exemplo_danfse.php)

---

## Contribuindo

Contribuições são bem-vindas! Por favor:

1. Faça um Fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`)
3. Commit suas mudanças (`git commit -am 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

---

## Licença

Este projeto está licenciado sob a licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.

---

## Suporte

- **Issues:** [GitHub Issues](https://github.com/marciocvitor/php-nfse-nacional/issues)
- **Documentação:** [Wiki](https://github.com/marciocvitor/php-nfse-nacional)

---

## Aviso Legal

Esta biblioteca é um projeto open source independente e não possui vínculo oficial com o Governo Federal, ABRASF ou qualquer órgão público. Use por sua conta e risco.

---

**Desenvolvido pela comunidade PHP brasileira**
