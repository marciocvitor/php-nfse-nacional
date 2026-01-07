# Guia Rápido - php-nfse-nacional

## 3 Passos para Começar

### Passo 1: Subir para GitHub (Privado)

```bash
# 1. Crie repositório em: https://github.com/new
#    Nome: php-nfse-nacional
#    Tipo: PRIVADO

# 2. Execute no terminal (no diretório da biblioteca):
cd /caminho/para/php-nfse-nacional
git branch -M main
git remote add origin https://github.com/SEU-USUARIO/php-nfse-nacional.git
git push -u origin main
```

### Passo 2: Configurar Token GitHub

```bash
# 1. Crie token em: https://github.com/settings/tokens
#    Permissão: repo

# 2. Configure no Composer:
composer config --global github-oauth.github.com SEU_TOKEN_AQUI
```

### Passo 3: Usar no Projeto

**composer.json do seu projeto:**
```json
{
    "repositories": [{
        "type": "vcs",
        "url": "https://github.com/marciocvitor/php-nfse-nacional.git"
    }],
    "require": {
        "php-nfse/nfse-nacional": "dev-main"
    }
}
```

```bash
composer install
```

## Código de Exemplo

```php
<?php
require 'vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

$config = Config::fromArray([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'cmun' => '3550308',
    'razaosocial' => 'Minha Empresa LTDA',
    'ambiente' => 2, // 1=Produção, 2=Homologação
    'certPfx' => __DIR__ . '/certificados/empresa.pfx',
    'certPassword' => 'senha',
    // URLs da API são automáticas baseadas no ambiente
    // apiBaseUrl é opcional - usa padrão se não informado
]);

$tools = new Tools($config);

$dps = (new Make())
    ->setIdentificacao(['numero' => '123', 'serie' => 'A1', 'tipo' => 1])
    ->setPrestador(['cnpj' => '12345678901234', 'im' => '123456', 'razaoSocial' => 'Minha Empresa LTDA'])
    ->setTomador(['cpfCnpj' => '98765432100', 'razaoSocial' => 'Cliente Teste', 'email' => 'cliente@teste.com'])
    ->setServico([
        'itemListaServico' => '01.01',
        'discriminacao' => 'Serviço de desenvolvimento',
        'codigoMunicipio' => '3550308',
        'valorServicos' => 1000.00,
        'valorIss' => 50.00,
        'aliquota' => 0.0500
    ]);

$response = $tools->enviarDps($dps->getXML());

echo $response->sucesso ? 'Sucesso!' : 'Erro: ' . $response->mensagem;
```

## Documentação Completa

- [INSTALL.md](INSTALL.md) - Guia completo de instalação
- [README.md](README.md) - Documentação do projeto
- [examples/](examples/) - Exemplos de uso

---

**Dúvidas?** Abra uma issue no GitHub!
