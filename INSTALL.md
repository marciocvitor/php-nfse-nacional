# Ì≥¶ Instala√ß√£o e Uso via Composer (Reposit√≥rio Privado)

## Ì¥ê Configura√ß√£o de Acesso ao Reposit√≥rio Privado

### Op√ß√£o 1: Token de Acesso Pessoal (Recomendado)

#### 1. Criar Token no GitHub:
```
1. Acesse: https://github.com/settings/tokens
2. Clique em "Generate new token (classic)"
3. Nome: "composer-php-nfse-nacional"
4. Marque: ‚úì repo (acesso completo a reposit√≥rios privados)
5. Clique em "Generate token"
6. COPIE O TOKEN (aparece s√≥ uma vez!)
```

#### 2. Configurar Composer com Token:

```bash
# Configurar globalmente (recomendado)
composer config --global github-oauth.github.com SEU_TOKEN_AQUI
```

Ou adicione no `auth.json` do projeto:

```json
{
    "github-oauth": {
        "github.com": "SEU_TOKEN_AQUI"
    }
}
```

---

### Op√ß√£o 2: SSH (Alternativa)

```bash
# 1. Configure sua chave SSH no GitHub
# 2. Use URL SSH ao inv√©s de HTTPS

{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:SEU-USUARIO/php-nfse-nacional.git"
        }
    ]
}
```

---

## Ì≥• Instala√ß√£o no Projeto

### 1. No seu `composer.json`:

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

### 2. Instalar:

```bash
composer install
```

Ou adicionar diretamente:

```bash
composer require php-nfse/nfse-nacional:dev-main
```

---

## Ìø∑Ô∏è Usando com Tags/Vers√µes

### 1. Criar vers√£o no GitHub:

```bash
# No reposit√≥rio php-nfse-nacional
git tag -a v1.0.0 -m "Vers√£o 1.0.0"
git push origin v1.0.0
```

### 2. No composer.json use a vers√£o:

```json
{
    "require": {
        "php-nfse/nfse-nacional": "^1.0"
    }
}
```

---

## Ì∫Ä Exemplo de Uso

```php
<?php

require 'vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

$config = new Config([
    'cnpj' => '12345678901234',
    'im' => '123456',
    'ambiente' => 2
]);

$tools = new Tools($config);
$dps = new Make();

// Seu c√≥digo aqui...
```

---

## Ì¥Ñ Atualizando a Biblioteca

```bash
# Atualizar para √∫ltima vers√£o
composer update php-nfse/nfse-nacional

# Ou instalar vers√£o espec√≠fica
composer require php-nfse/nfse-nacional:v1.0.1
```

---

## ‚ö†Ô∏è Troubleshooting

### Erro: "Repository not found"
- Verifique se o token tem permiss√£o `repo`
- Confirme que o reposit√≥rio est√° acess√≠vel
- Token pode ter expirado

### Erro: "Authentication required"
- Configure o token: `composer config --global github-oauth.github.com TOKEN`

### Limpar cache:
```bash
composer clear-cache
rm -rf vendor/
composer install
```

---

## Ì≥ö Documenta√ß√£o Adicional

- [Composer - Private Repositories](https://getcomposer.org/doc/articles/authentication-for-private-packages.md)
- [GitHub - Personal Access Tokens](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token)

