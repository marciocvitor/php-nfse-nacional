# Guia de Contribuição

Obrigado por considerar contribuir com o PHP NFSe Nacional! 🎉

## 📋 Como Contribuir

### Reportar Bugs

1. Verifique se o bug já não foi reportado nas [Issues](https://github.com/marciocvitor/php-nfse-nacional/issues)
2. Use o template de [Bug Report](.github/ISSUE_TEMPLATE/bug_report.md)
3. Forneça informações detalhadas sobre o problema

### Sugerir Funcionalidades

1. Verifique se a funcionalidade já não foi sugerida
2. Use o template de [Feature Request](.github/ISSUE_TEMPLATE/feature_request.md)
3. Explique o problema que a funcionalidade resolveria

### Contribuir com Código

#### 1. Fork o Projeto

```bash
# Clone seu fork
git clone https://github.com/SEU_USUARIO/php-nfse-nacional.git
cd php-nfse-nacional
```

#### 2. Crie uma Branch

```bash
git checkout -b feature/nova-funcionalidade
# ou
git checkout -b fix/correcao-bug
```

#### 3. Configure o Ambiente de Desenvolvimento

```bash
# Instale dependências
composer install

# Instale dependências de desenvolvimento
composer install --dev
```

#### 4. Crie uma relação com o projeto original

```bash
git remote add upstream git@github.com:marciocvitor/php-nfse-nacional.git
```

#### 5. Antes de começar a codar, sincronize com o repositório principal

```bash
git fetch upstream
git merge upstream/main
git push
```

#### 6. Faça suas Alterações

- Siga os padrões PSR-12 de codificação
- Adicione testes para novas funcionalidades
- Atualize a documentação quando necessário
- Certifique-se de que os testes passam

#### 7. Validação de Código

```bash
# Verificar estilo de código
composer phpcs

# Análise estática
composer phpstan

# Executar testes
composer test
```

#### 8. Commit suas Alterações

Use mensagens de commit claras e descritivas:

```bash
git commit -m "feat: adiciona nova funcionalidade X"
git commit -m "fix: corrige bug na validação Y"
git commit -m "docs: atualiza README com exemplo Z"
```

**Convenções de Commit:**
- `feat:` Nova funcionalidade
- `fix:` Correção de bug
- `docs:` Documentação
- `style:` Formatação (sem mudança de código)
- `refactor:` Refatoração
- `test:` Testes
- `chore:` Tarefas de manutenção

#### 9. Push para seu Fork

```bash
git push origin feature/nova-funcionalidade
```

#### 10. Abra um Pull Request

1. Vá para o repositório original
2. Clique em "New Pull Request"
3. Selecione sua branch
4. Preencha o template de PR
5. Aguarde revisão

## 🔍 Checklist para Pull Requests

- [ ] Código segue os padrões PSR-12
- [ ] Testes foram adicionados/atualizados
- [ ] Todos os testes passam (`composer test`)
- [ ] Código passou na análise estática (`composer phpstan`)
- [ ] Código passou no code sniffer (`composer phpcs`)
- [ ] Documentação foi atualizada
- [ ] CHANGELOG.md foi atualizado (se aplicável)
- [ ] Não há informações sensíveis no código (CNPJ, senhas, etc.)

## 📝 Padrões de Código

### PHP

- PHP >= 8.2
- Seguir PSR-12
- Type hints obrigatórios
- PHPDoc para todas as classes e métodos públicos

### Estrutura de Classes

```php
<?php

declare(strict_types=1);

namespace NFSeNacional\Namespace;

/**
 * Descrição da classe
 */
class MinhaClasse
{
    /**
     * Descrição do método
     *
     * @param string $parametro Descrição
     * @return array
     * @throws Exception
     */
    public function meuMetodo(string $parametro): array
    {
        // Implementação
    }
}
```

## 🧪 Testes

### Executar Testes

```bash
composer test
```

### Criar Novos Testes

Adicione testes na pasta `tests/` seguindo o padrão:

```php
<?php

declare(strict_types=1);

namespace NFSeNacional\Tests;

use PHPUnit\Framework\TestCase;

class MinhaClasseTest extends TestCase
{
    public function testMetodo(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

## 📚 Documentação

- Mantenha o README.md atualizado
- Adicione exemplos em `examples/`
- Documente mudanças no CHANGELOG.md
- Atualize NOTAS-TECNICAS.md se houver mudanças relacionadas

## 🔒 Segurança

⚠️ **IMPORTANTE**: 
- Nunca commite certificados digitais ou chaves privadas
- Remova informações sensíveis antes de fazer PR
- Se encontrar uma vulnerabilidade, consulte [SECURITY.md](SECURITY.md)

## ❓ Dúvidas?

- Abra uma [Issue](https://github.com/marciocvitor/php-nfse-nacional/issues) com a label `question`
- Consulte a documentação em `docs/`
- Veja os exemplos em `examples/`

## 📜 Código de Conduta

Este projeto segue o [Código de Conduta](CODE_OF_CONDUCT.md). Ao participar, você concorda em manter este código.

## 🙏 Obrigado!

Sua contribuição é muito valiosa para a comunidade PHP brasileira!

