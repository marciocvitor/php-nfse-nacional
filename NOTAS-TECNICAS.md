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

### NT 009 v1.01 - Layout 1.01 (Reforma Tributária do Consumo)
**Data:** 2026-11-26 (schemas oficiais de produção restrita publicados em 24/11/2026)  
**Versão do Layout:** 1.01  
**Nota Técnica:** NT 009 v1.01 - NFS-e Nacional  
**Descrição:** Adequação ao leiaute oficial da NT 009. Emissão individual de DPS via API REST. Remoção total do padrão ABRASF e do envio em lote.  
**Schemas:**
- `DPS_v1.01.xsd` (raiz DPS, namespace `http://www.sped.fazenda.gov.br/nfse`)
- `NFSe_v1.01.xsd`
- `pedRegEvento_v1.01.xsd` / `evento_v1.01.xsd` / `tiposEventos_v1.01.xsd` (eventos, ex.: cancelamento `e101101`)
- `tiposComplexos_v1.01.xsd`, `tiposSimples_v1.01.xsd`, `CNC_v1.00.xsd`, `tiposCnc_v1.00.xsd`, `xmldsig-core-schema.xsd`

**Principais mudanças de leiaute:**
- CNPJ do prestador/tomador aceita formato alfanumérico (`[0-9A-Z]{14}`)
- Série numérica (`TSSerieDPS`)
- Código de tributação nacional `cTribNac` (6 dígitos) obrigatório
- Regime tributário do prestador (`opSimpNac`, `regApTribSN`, `regEspTrib`)
- Cancelamento com `cMotivo` (1 = Erro na emissão, 2 = Serviço não prestado, 9 = Outros)

**Breaking changes:** ver `CHANGELOG.md` (versão 2.0.0).

### NT Inicial - Layout 1.00 (descontinuada)
**Versão do Layout:** 1.00  
**Nota Técnica:** NT inicial - Layout 1.00 - Padrão ABRASF Portal Nacional NFSe  
**Descrição:** Implementação inicial da biblioteca seguindo o padrão ABRASF (lote RPS/EnviarLoteRpsEnvio). Removida na versão 2.0.0, pois o Portal Nacional passou a usar o leiaute NT 009.  
**Schemas:** `EnviarDpsEnvio.xsd` e `EnviarLoteRpsEnvio.xsd` (1.00) — removidos.

---

## Referências

- [Portal Nacional de NFSe](https://www.gov.br/nfse/pt-br)
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

