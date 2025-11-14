# ��� Guia Rápido - php-nfse-nacional

## ⚡ 3 Passos para Começar

### 1️⃣ Subir para GitHub (Privado)

```bash
# 1. Crie repositório em: https://github.com/new
#    Nome: php-nfse-nacional
#    Tipo: PRIVADO ✓

# 2. Execute no terminal:
cd /c/xampp/htdocs/php-nfse-nacional
git branch -M main
git remote add origin https://github.com/SEU-USUARIO/php-nfse-nacional.git
git push -u origin main
```

### 2️⃣ Configurar Token GitHub

```bash
# 1. Crie token em: https://github.com/settings/tokens
#    Permissão: ✓ repo

# 2. Configure no Composer:
composer config --global github-oauth.github.com SEU_TOKEN_AQUI
```

### 3️⃣ Usar no Projeto

**composer.json do seu projeto:**
```json
{
    "repositories": [{
        "type": "vcs",
        "url": "https://github.com/SEU-USUARIO/php-nfse-nacional.git"
    }],
    "require": {
        "php-nfse/nfse-nacional": "dev-main"
    }
}
```

```bash
composer install
```

## ��� Código de Exemplo

```php
<?php
require 'vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;

$config = new Config([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'ambiente' => 2
]);

$tools = new Tools($config);
$response = $tools->enviarDps($xml);

echo $response->sucesso ? "✓ Sucesso!" : "✗ Erro";
```

## �� Documentação Completa

- [INSTALL.md](INSTALL.md) - Guia completo de instalação
- [README.md](README.md) - Documentação do projeto
- [examples/](examples/) - Exemplos de uso

---

**Dúvidas?** Abra uma issue no GitHub!
