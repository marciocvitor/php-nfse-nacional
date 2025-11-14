# PHP NFSe Nacional Ì∑ßÌ∑∑

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

Biblioteca PHP open source para emiss√£o de **Nota Fiscal de Servi√ßo Eletr√¥nica (NFSe)** no **Portal Nacional** seguindo o padr√£o **ABRASF**.

---

## Ì∫Ä Instala√ß√£o

### Via Composer (Reposit√≥rio Privado)

Adicione o reposit√≥rio no seu `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/SEU-USUARIO/php-nfse-nacional.git"
        }
    ],
    "require": {
        "php-nfse/nfse-nacional": "dev-main"
    }
}
```

Ent√£o execute:

```bash
composer install
```

---

## Ì≥ñ Uso B√°sico

```php
<?php

require 'vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

// Configura√ß√£o
$config = new Config([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'cmun' => '3550308',
    'razaosocial' => 'Minha Empresa LTDA',
    'ambiente' => 2, // 1=Produ√ß√£o, 2=Homologa√ß√£o
    'certPfx' => 'caminho/certificado.pfx',
    'certPassword' => 'senha'
]);

$tools = new Tools($config);

// Criar DPS
$dps = new Make();
$dps->setPrestador(['cnpj' => '12345678901234', 'im' => '123456']);
$dps->setTomador([
    'cpfCnpj' => '98765432100',
    'razaoSocial' => 'Cliente',
    'email' => 'cliente@teste.com'
]);
$dps->setServico([
    'itemListaServico' => '01.01',
    'discriminacao' => 'Servi√ßo de desenvolvimento',
    'codigoMunicipio' => '3550308',
    'valorServicos' => 1000.00,
    'valorIss' => 50.00,
    'aliquota' => 5.00
]);

// Gerar XML e enviar
$xml = $dps->getXML();
$response = $tools->enviarDps($xml);

if ($response->sucesso) {
    echo "NFSe emitida: " . $response->numero;
}
```

---

## Ì≥ã Requisitos

- PHP >= 7.4
- Extens√µes: json, openssl, soap, dom, simplexml, libxml
- Certificado Digital A1 (.pfx)

---

## Ì¥ù Contribuindo

Contribui√ß√µes s√£o bem-vindas! Abra issues ou pull requests.

---

## ‚öñÔ∏è Licen√ßa

MIT License - veja [LICENSE](LICENSE)

---

**Desenvolvido pela comunidade PHP brasileira** Ì∑ßÌ∑∑
