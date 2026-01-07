# Guia Prático: Como Aplicar a Biblioteca no Seu Projeto

Este guia mostra **3 formas** de usar a biblioteca PHP NFSe Nacional no seu projeto.

---

## Opção 1: Usar Localmente (Desenvolvimento/Teste)

### Passo 1: Copiar a biblioteca para seu projeto

```bash
# No diretório do seu projeto
mkdir -p libs
cp -r /caminho/para/php-nfse-nacional libs/php-nfse-nacional
```

### Passo 2: Configurar autoload no seu projeto

**No seu `composer.json`:**

```json
{
    "autoload": {
        "psr-4": {
            "SeuProjeto\\": "src/",
            "NFSeNacional\\": "libs/php-nfse-nacional/src/"
        }
    }
}
```

**Ou usar diretamente:**

```php
<?php
// No início do seu arquivo PHP
require_once __DIR__ . '/libs/php-nfse-nacional/vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;
```

---

## Opção 2: Publicar no GitHub e Usar via Composer (Recomendado)

### Passo 1: Criar repositório no GitHub

1. Acesse: https://github.com/new
2. Nome: `php-nfse-nacional` (ou outro nome)
3. Tipo: **Privado** ou **Público** (sua escolha)
4. Clique em "Create repository"

### Passo 2: Enviar código para GitHub

```bash
# No diretório da biblioteca
cd /caminho/para/php-nfse-nacional

# Inicializar git (se ainda não tiver)
git init
git add .
git commit -m "Versão inicial da biblioteca PHP NFSe Nacional"

# Adicionar remote
git remote add origin https://github.com/SEU-USUARIO/php-nfse-nacional.git

# Enviar código
git branch -M main
git push -u origin main
```

### Passo 3: Configurar acesso (se repositório privado)

**Criar Token GitHub:**

1. Acesse: https://github.com/settings/tokens
2. Clique em "Generate new token (classic)"
3. Nome: `composer-php-nfse-nacional`
4. Marque: ✅ **repo** (acesso completo)
5. Clique em "Generate token"
6. **COPIE O TOKEN** (aparece só uma vez!)

**Configurar Composer:**

```bash
# Globalmente (recomendado)
composer config --global github-oauth.github.com SEU_TOKEN_AQUI

# Ou apenas no projeto (criar auth.json na raiz)
```

### Passo 4: Instalar no seu projeto

**No `composer.json` do seu projeto:**

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

**Instalar:**

```bash
cd /caminho/do/seu/projeto
composer install
```

---

## Opção 3: Usar como Submódulo Git

### Passo 1: Adicionar como submódulo

```bash
# No diretório do seu projeto
git submodule add https://github.com/SEU-USUARIO/php-nfse-nacional.git libs/php-nfse-nacional
```

### Passo 2: Configurar autoload

**No seu `composer.json`:**

```json
{
    "autoload": {
        "psr-4": {
            "NFSeNacional\\": "libs/php-nfse-nacional/src/"
        }
    }
}
```

**Atualizar autoload:**

```bash
composer dump-autoload
```

---

## Exemplo Completo de Uso no Seu Projeto

### Estrutura de exemplo:

```
meu-projeto/
├── composer.json
├── src/
│   └── EmissaoNfse.php
├── certificados/
│   └── empresa.pfx
└── vendor/ (ou libs/)
```

### Código de exemplo (`src/EmissaoNfse.php`):

```php
<?php

namespace MeuProjeto;

require __DIR__ . '/../vendor/autoload.php';

use NFSeNacional\Config\Config;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Make\Make;

class EmissaoNfse
{
    private Tools $tools;
    
    public function __construct()
    {
        $config = Config::fromArray([
            'cnpj' => '12345678901234',
            'im' => '123456',
            'cmun' => '3550308',
            'razaosocial' => 'Minha Empresa LTDA',
            'ambiente' => 2, // 1=Produção, 2=Homologação
            'certPfx' => __DIR__ . '/../certificados/empresa.pfx',
            'certPassword' => 'senha_do_certificado',
            // URLs da API são automáticas baseadas no ambiente
            // apiBaseUrl é opcional - usa padrão se não informado
        ]);
        
        $this->tools = new Tools($config);
    }
    
    public function emitirNfse(array $dados): array
    {
        try {
            $dps = (new Make())
                ->setIdentificacao([
                    'numero' => $dados['numero'],
                    'serie' => $dados['serie'] ?? 'UNICA',
                    'tipo' => $dados['tipo'] ?? 1
                ])
                ->setPrestador([
                    'cnpj' => $dados['prestador']['cnpj'],
                    'im' => $dados['prestador']['im'],
                    'razaoSocial' => $dados['prestador']['razaoSocial']
                ])
                ->setTomador([
                    'cpfCnpj' => $dados['tomador']['cpfCnpj'],
                    'razaoSocial' => $dados['tomador']['razaoSocial'],
                    'email' => $dados['tomador']['email'] ?? ''
                ])
                ->setServico([
                    'itemListaServico' => $dados['servico']['itemListaServico'],
                    'discriminacao' => $dados['servico']['discriminacao'],
                    'codigoMunicipio' => $dados['servico']['codigoMunicipio'],
                    'valorServicos' => $dados['servico']['valorServicos'],
                    'valorIss' => $dados['servico']['valorIss'],
                    'aliquota' => $dados['servico']['aliquota'],
                    'issRetido' => $dados['servico']['issRetido'] ?? 2
                ]);
            
            $response = $this->tools->enviarDps($dps->getXML());
            
            return [
                'sucesso' => $response->sucesso,
                'numero' => $response->numero,
                'codigoVerificacao' => $response->codigoVerificacao,
                'mensagem' => $response->mensagem,
                'xml' => $response->xml
            ];
            
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
}

// Uso:
$emissor = new EmissaoNfse();
$resultado = $emissor->emitirNfse([
    'numero' => '123',
    'prestador' => [
        'cnpj' => '12345678901234',
        'im' => '123456',
        'razaoSocial' => 'Minha Empresa LTDA'
    ],
    'tomador' => [
        'cpfCnpj' => '98765432100',
        'razaoSocial' => 'Cliente Teste',
        'email' => 'cliente@teste.com'
    ],
    'servico' => [
        'itemListaServico' => '01.01',
        'discriminacao' => 'Serviço de desenvolvimento',
        'codigoMunicipio' => '3550308',
        'valorServicos' => 1000.00,
        'valorIss' => 50.00,
        'aliquota' => 0.0500
    ]
]);

if ($resultado['sucesso']) {
    echo "NFSe emitida: " . $resultado['numero'] . "\n";
} else {
    echo "Erro: " . $resultado['erro'] . "\n";
}
```

---

## Checklist de Configuração

Antes de usar, certifique-se de ter:

- [ ] PHP >= 8.2 instalado
- [ ] Composer instalado
- [ ] Certificado digital A1 (.pfx) válido
- [ ] Senha do certificado
- [ ] CNPJ e Inscrição Municipal cadastrados no Portal Nacional
- [ ] Ambiente configurado (1=Produção, 2=Homologação)
- [ ] URLs da API são automáticas (ou configure manualmente via `apiBaseUrl`)

---

## URLs da API REST

As URLs da API são configuradas automaticamente baseadas no ambiente:
- **Homologação:** `https://sefin.producaorestrita.nfse.gov.br`
- **Produção:** `https://sefin.nfse.gov.br`

Você pode configurar manualmente se necessário usando o parâmetro `apiBaseUrl` na configuração.

Para mais informações, consulte:
- [Portal Nacional de NFSe](https://www.gov.br/nfse/pt-br)
- [Swagger da API](https://www.nfse.gov.br/swagger/contribuintesissqn/)

---

## Próximos Passos

- Veja exemplos completos em: `examples/exemplo_basico.php` e `examples/exemplo_lote.php`
- Leia a documentação completa: [README.md](README.md)
- Consulte o manual de lote: [MANUAL-LOTE.md](MANUAL-LOTE.md)

---

**Dúvidas?** Consulte a documentação ou abra uma issue no GitHub!

