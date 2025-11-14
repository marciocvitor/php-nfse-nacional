# PHP NFSe Nacional

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

Biblioteca PHP open source para emissão de **Nota Fiscal de Serviço Eletrônica (NFSe)** no **Portal Nacional** seguindo o padrão **ABRASF**.

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

```php
<?php

require 'vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

// Configuração
$config = new Config([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'cmun' => '3550308',
    'razaosocial' => 'Minha Empresa LTDA',
    'ambiente' => 2, // 1=Produção, 2=Homologação
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
    'discriminacao' => 'Serviço de desenvolvimento',
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

## Requisitos

- PHP >= 7.4
- Extensões PHP:
  - `ext-json`
  - `ext-openssl`
  - `ext-soap`
  - `ext-dom`
  - `ext-simplexml`
  - `ext-libxml`
- Certificado Digital A1 (.pfx)

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
│   └── Common/           # Classes comuns (Response)
└── examples/
    └── exemplo_basico.php # Exemplo de uso
```

---

## Documentação

- **[QUICK-START.md](QUICK-START.md)** - Início rápido em 3 passos
- **[INSTALL.md](INSTALL.md)** - Guia completo de instalação e configuração
- **[examples/](examples/)** - Exemplos práticos de uso

---

## Funcionalidades

- [x] Emissão de DPS/RPS
- [x] Consulta de NFSe
- [x] Cancelamento de NFSe
- [x] Suporte a certificado A1
- [x] Ambientes: Homologação e Produção
- [ ] Geração de PDF (DANFSe) - Em desenvolvimento
- [ ] Envio em lote - Em desenvolvimento
- [ ] Assinatura digital completa - Em desenvolvimento

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
