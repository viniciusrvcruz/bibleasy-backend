# Sistema de Referências de Versículos

Este documento explica como funciona o sistema de referências de versículos na API, para que uma LLM possa implementar a funcionalidade no frontend.

## Visão Geral

As referências são notas explicativas ou comentários que aparecem dentro do texto dos versículos. Elas são extraídas do formato USFM durante a importação e armazenadas separadamente, sendo referenciadas no texto do versículo através de slugs no formato `{{slug}}`.

## Estrutura de Dados

### Versículo com Referências

Quando um versículo contém referências, o texto do versículo terá marcadores no formato `{{slug}}` onde as referências aparecem:

```json
{
  "id": 1,
  "number": 1,
  "text": "Este livro é o registro{{1}} da genealogia de Jesus Cristo{{2}}, filho de Davi, filho de Abraão:",
  "references": [
    {
      "id": 1,
      "slug": "1",
      "reference_text": "Ou \"Cristo.\" Messias é a palavra em hebraico para Cristo em grego.",
      "target_book_abbreviation": null,
      "target_chapter": null,
      "target_verse": null
    },
    {
      "id": 2,
      "slug": "2",
      "reference_text": "Referência adicional sobre genealogia",
      "target_book_abbreviation": null,
      "target_chapter": null,
      "target_verse": null
    }
  ]
}
```

### Formato do Slug

- O slug é sempre um número sequencial (1, 2, 3, ...)
- Cada referência no mesmo versículo recebe um slug único
- O slug aparece no texto do versículo no formato `{{slug}}`

## Como Funciona

### 1. Importação (Backend)

Durante a importação de arquivos USFM, o sistema:

1. **Extrai referências** do formato `\f + \fr 1:1 \ft texto da referência \f*`
2. **Gera slugs** sequenciais (1, 2, 3, ...) para cada referência
3. **Substitui a referência no texto** pelo slug no formato `{{slug}}`
4. **Armazena as referências** em uma tabela separada (`verse_references`)

### 2. API Response

Quando um versículo é retornado pela API, ele inclui:

- `text`: O texto do versículo com os marcadores `{{slug}}`
- `references`: Array com todas as referências do versículo, cada uma com seu `slug` correspondente

### 3. Implementação no Frontend

Para exibir as referências no frontend, você precisa:

#### Passo 1: Parsear o Texto

Divida o texto do versículo pelos marcadores `{{slug}}`:

```javascript
function parseVerseText(text, references) {
  const parts = text.split(/(\{\{\d+\}\})/);
  return parts.map(part => {
    const match = part.match(/\{\{(\d+)\}\}/);
    if (match) {
      const slug = match[1];
      const reference = references.find(ref => ref.slug === slug);
      return {
        type: 'reference',
        slug,
        reference
      };
    }
    return {
      type: 'text',
      content: part
    };
  });
}
```

#### Passo 2: Renderizar o Texto

Renderize cada parte do texto, substituindo os marcadores `{{slug}}` por elementos clicáveis ou tooltips:

```jsx
function VerseText({ text, references }) {
  const parts = parseVerseText(text, references);
  
  return (
    <p>
      {parts.map((part, index) => {
        if (part.type === 'reference') {
          return (
            <Tooltip key={index} content={part.reference.reference_text}>
              <sup className="reference-marker">
                [{part.slug}]
              </sup>
            </Tooltip>
          );
        }
        return <span key={index}>{part.content}</span>;
      })}
    </p>
  );
}
```

#### Exemplo Completo (React)

```jsx
import React from 'react';

function Verse({ verse }) {
  const renderText = () => {
    const parts = verse.text.split(/(\{\{\d+\}\})/);
    
    return parts.map((part, index) => {
      const match = part.match(/\{\{(\d+)\}\}/);
      
      if (match) {
        const slug = match[1];
        const reference = verse.references.find(ref => ref.slug === slug);
        
        return (
          <React.Fragment key={index}>
            <sup 
              className="reference-marker"
              title={reference?.reference_text}
              onClick={() => showReferenceModal(reference)}
            >
              [{slug}]
            </sup>
          </React.Fragment>
        );
      }
      
      return <span key={index}>{part}</span>;
    });
  };

  return (
    <div className="verse">
      <span className="verse-number">{verse.number}</span>
      <span className="verse-text">{renderText()}</span>
    </div>
  );
}
```

## Exemplos de Uso

### Exemplo 1: Versículo Simples

**Texto original no USFM:**
```
\v 1 Este livro é o registro da genealogia de Jesus Cristo\f + \fr 1:1 \ft Ou "Cristo." Messias é a palavra em hebraico para Cristo em grego.\f*, filho de Davi, filho de Abraão:
```

**Após processamento:**
- `text`: `"Este livro é o registro{{1}} da genealogia de Jesus Cristo, filho de Davi, filho de Abraão:"`
- `references[0].slug`: `"1"`
- `references[0].reference_text`: `"Ou \"Cristo.\" Messias é a palavra em hebraico para Cristo em grego."`

### Exemplo 2: Múltiplas Referências

**Texto original:**
```
\v 1 Primeira referência\f + \fr 1:1 \ft Texto da primeira\f* e segunda\f + \fr 1:2 \ft Texto da segunda\f* no mesmo versículo
```

**Após processamento:**
- `text`: `"Primeira referência{{1}} e segunda{{2}} no mesmo versículo"`
- `references[0].slug`: `"1"`
- `references[1].slug`: `"2"`

## Endpoints da API

### Obter Versículo com Referências

```http
GET /api/versions/{versionId}/books/{bookId}/chapters/{chapterId}/verses/{verseId}
```

**Response:**
```json
{
  "id": 123,
  "number": 1,
  "text": "Este livro é o registro{{1}} da genealogia",
  "references": [
    {
      "id": 1,
      "slug": "1",
      "reference_text": "Ou \"Cristo.\" Messias é a palavra em hebraico para Cristo em grego.",
      "target_book_abbreviation": null,
      "target_chapter": null,
      "target_verse": null
    }
  ]
}
```

## Notas Importantes

1. **Slugs são únicos por versículo**: Cada versículo tem seus próprios slugs começando do 1
2. **Ordem das referências**: Os slugs são gerados na ordem em que as referências aparecem no texto
3. **Referências vazias**: Referências sem texto são ignoradas durante a importação
4. **Formato do marcador**: O marcador no texto é sempre `{{slug}}` (com chaves duplas)
5. **Campos opcionais**: `target_book_abbreviation`, `target_chapter` e `target_verse` podem ser `null` (não são parseados atualmente)

## Sugestões de UX

1. **Tooltip**: Mostrar o texto da referência ao passar o mouse sobre o marcador
2. **Modal/Popover**: Abrir um modal ao clicar no marcador para mostrar a referência completa
3. **Estilo visual**: Usar sobrescrito (superscript) ou números entre colchetes `[1]` para os marcadores
4. **Acessibilidade**: Adicionar `aria-label` com o texto da referência para leitores de tela
