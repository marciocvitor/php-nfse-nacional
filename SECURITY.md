# Política de Segurança

## Versões Suportadas

Use esta seção para verificar quais versões do projeto são atualmente suportadas com atualizações de segurança.

| Versão | Suportada          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reportando uma Vulnerabilidade

**Por favor, NÃO reporte vulnerabilidades de segurança através de issues públicas no GitHub.**

### Como Reportar

Se você descobrir uma vulnerabilidade de segurança, por favor:

1. **Não** abra uma issue pública
2. **Não** divulgue publicamente até que seja corrigida
3. Envie um email para os mantenedores com:
   - **Assunto**: [VULNERABILIDADE] Descrição breve
   - **Descrição detalhada** da vulnerabilidade
   - **Passos para reproduzir**
   - **Impacto potencial**
   - **Possíveis soluções** (se você tiver sugestões)

### O que esperar

- Resposta em até 48 horas confirmando o recebimento
- Avaliação inicial em até 7 dias
- Estimativa de tempo para correção
- Notificação quando a vulnerabilidade for corrigida
- Reconhecimento no CHANGELOG (se você permitir)

### Processo de Correção

1. Confirmamos a vulnerabilidade
2. Desenvolvemos uma correção
3. Testamos a correção
4. Liberamos uma versão corrigida
5. Publicamos um aviso de segurança (se necessário)

## Práticas de Segurança

### Para Usuários da Biblioteca

- ✅ Sempre mantenha a biblioteca atualizada
- ✅ Use certificados digitais válidos e protegidos
- ✅ Nunca compartilhe certificados ou senhas
- ✅ Use HTTPS em produção
- ✅ Mantenha as dependências atualizadas (`composer update`)
- ✅ Valide todas as entradas antes de enviar ao webservice
- ✅ Armazene certificados em local seguro fora do webroot
- ✅ Use variáveis de ambiente para configurações sensíveis

### Para Contribuidores

- ✅ Nunca commite informações sensíveis
- ✅ Use variáveis de ambiente para configurações
- ✅ Valide todas as entradas
- ✅ Siga as melhores práticas de segurança PHP
- ✅ Revise o código antes de fazer merge
- ✅ Execute os testes de segurança quando disponíveis

## Boas Práticas de Uso

### Certificados Digitais

```php
// ✅ Correto: Armazene fora do webroot
$config = Config::fromArray([
    'certPfx' => '/path/outside/webroot/certificado.pfx',
    'certPassword' => getenv('CERT_PASSWORD'), // Use variáveis de ambiente
]);

// ❌ Errado: Não armazene no webroot
$config = Config::fromArray([
    'certPfx' => '/caminho/para/webroot/public/certificado.pfx', // NUNCA faça isso!
    'certPassword' => 'senha123', // NUNCA hardcode senhas
]);
```

### Logs

```php
// ✅ Correto: Armazene logs fora do webroot
$tools = new Tools($config, logDir: '/caminho/seguro/logs/nfse');

// ❌ Errado: Não exponha logs
$tools = new Tools($config, logDir: '/caminho/para/webroot/logs'); // NUNCA faça isso!
```

## Dependências

Este projeto depende de várias bibliotecas de terceiros. Recomendamos:

1. Executar `composer audit` regularmente para verificar vulnerabilidades conhecidas
2. Manter as dependências atualizadas
3. Revisar o CHANGELOG das dependências antes de atualizar

## Agradecimentos

Agradecemos a todos que reportam vulnerabilidades de forma responsável. Sua contribuição ajuda a manter este projeto seguro para toda a comunidade.

