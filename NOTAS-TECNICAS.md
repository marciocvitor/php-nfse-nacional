# Notas Técnicas - NFSe Nacional

Este arquivo mantém o histórico das **Notas Técnicas (NTs)** implementadas na biblioteca PHP NFSe Nacional, seguindo o padrão do Portal Nacional de NFSe.

---

## Como Atualizar

Quando uma nova Nota Técnica for publicada pelo Portal Nacional:

1. **Atualize o arquivo `VERSION.txt`** com a nova versão do layout
2. **Atualize o arquivo `NT.txt`** com a descrição da NT
3. **Adicione uma entrada abaixo** com a data, versão e descrição
4. **Atualize os schemas** em `src/Schemas/` conforme necessário

---

## Histórico de Notas Técnicas

### NT Inicial - Layout 1.00
**Data:** Implementação inicial  
**Versão do Layout:** 1.00  
**Nota Técnica:** NT inicial - Layout 1.00 - Padrão ABRASF Portal Nacional NFSe  
**Descrição:** Implementação inicial da biblioteca seguindo o padrão ABRASF do Portal Nacional de NFSe.  
**Schemas:** 
- `EnviarDpsEnvio.xsd` (versão 1.00)
- `EnviarLoteRpsEnvio.xsd` (versão 1.00)

---

## Referências

- [Portal Nacional de NFSe](https://www.gov.br/nfse/pt-br)
- [ABRASF - Associação Brasileira das Secretarias de Finanças das Capitais](http://www.abrasf.org.br)
- [Documentação Técnica NFSe Nacional](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica)

---

## Estrutura da Informação

Cada entrada de NT deve conter:

- **Data:** Data de publicação/implementação da NT
- **Versão do Layout:** Versão do schema/layout (ex: 1.00, 2.00)
- **Nota Técnica:** Identificação completa da NT (ex: SE/CGNFS-e nº 004/2025)
- **Descrição:** Resumo das alterações
- **Schemas:** Lista de schemas XSD atualizados
- **Breaking Changes:** Se houver, lista de mudanças que quebram compatibilidade

---

**Última atualização:** 2025-11-14

