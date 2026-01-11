# Contexto: Implementação do Adapter USFM

Este documento descreve a implementação completa do adapter USFM para importação de versões bíblicas no formato USFM (Unified Standard Format Markers).

## Visão Geral

Foi implementado um sistema completo para importar versões bíblicas no formato USFM, onde cada arquivo `.usfm` representa um livro da Bíblia. O sistema inclui:

- Parser de arquivos USFM
- Extração e processamento de referências de versículos
- Validação de arquivos e nomes
- Testes unitários completos
- Documentação para implementação no frontend

## Arquivos Criados/Modificados

### 1. Adapter USFM
**Arquivo:** `app/Services/Version/Adapters/UsfmAdapter.php`

Adapter que implementa `VersionAdapterInterface` para processar arquivos USFM.

**Funcionalidades principais:**
- Validação de extensão `.usfm`
- Validação de nome do arquivo contra `BookAbbreviationEnum`
- Parsing de marcadores USFM:
  - `\h` - Nome do livro
  - `\c` - Capítulo
  - `\p` - Parágrafo (adiciona `\n` no último versículo)
  - `\v` - Versículo
- Extração de referências no formato `\f + \fr 1:1 \ft texto \f*`
- Substituição de referências por `{{slug}}` no texto do versículo

### 2. DTOs Criados/Modificados

#### VerseReferenceDTO
**Arquivo:** `app/Services/Version/DTOs/VerseReferenceDTO.php`

DTO para representar referências de versículos:
```php
class VerseReferenceDTO
{
    public readonly string $slug;
    public readonly string $referenceText;
    public readonly ?BookAbbreviationEnum $targetBookAbbreviation;
    public readonly ?int $targetChapter;
    public readonly ?int $targetVerse;
}
```

#### VerseDTO (Modificado)
**Arquivo:** `app/Services/Version/DTOs/VerseDTO.php`

Adicionado campo `references` (Collection de `VerseReferenceDTO`):
```php
class VerseDTO
{
    public readonly int $number;
    public readonly string $text;
    public readonly Collection $references; // Nova propriedade
}
```

### 3. VersionImporter (Modificado)
**Arquivo:** `app/Services/Version/Importers/VersionImporter.php`

Modificado para importar referências no banco de dados:
- Método `importReferences()` atualizado para processar referências
- Filtragem de versículos com referências antes do loop
- Inserção em lote de referências

### 4. VersionAdapterFactory (Modificado)
**Arquivo:** `app/Services/Version/Factories/VersionAdapterFactory.php`

Registrado o novo adapter:
```php
private static array $adapters = [
    'json_thiago_bodruk' => JsonThiagoBodrukAdapter::class,
    'usfm' => UsfmAdapter::class, // Novo adapter
];
```

## Funcionalidades Implementadas

### 1. Validação de Arquivos

#### Validação de Extensão
- Verifica se o arquivo tem extensão `.usfm`
- Case-insensitive
- Lança `VersionImportException` se inválido

#### Validação de Nome do Arquivo
- O nome do arquivo (sem extensão) deve corresponder a uma abreviação do `BookAbbreviationEnum`
- Exemplos válidos: `mat.usfm`, `MAT.usfm`, `gen.usfm`
- Case-insensitive
- Lança `VersionImportException` se não corresponder

### 2. Parsing de Marcadores USFM

#### `\h` - Nome do Livro
- Extrai o nome do livro que aparece após `\h`
- Deve estar presente no arquivo
- Exemplo: `\h Mateus`

#### `\c` - Capítulo
- Extrai o número do capítulo
- Inicia um novo capítulo
- Exemplo: `\c 1`

#### `\p` - Parágrafo
- Marcador estrutural
- Adiciona `\n` no último versículo do parágrafo anterior
- Exemplo: `\p`

#### `\v` - Versículo
- Extrai número e conteúdo do versículo
- Processa referências dentro do texto
- Exemplo: `\v 1 Este é o texto do versículo`

### 3. Sistema de Referências

#### Formato USFM de Referências
```
\f + \fr 1:1 \ft texto da referência \f*
```

#### Processamento
1. **Extração**: Identifica todas as referências no texto do versículo
2. **Geração de Slug**: Cria slugs sequenciais (1, 2, 3, ...) para cada referência
3. **Substituição**: Substitui a referência no texto por `{{slug}}`
4. **Armazenamento**: Cria `VerseReferenceDTO` com o texto da referência

#### Exemplo de Transformação

**Antes (USFM):**
```
\v 1 Este livro é o registro\f + \fr 1:1 \ft Ou "Cristo." Messias é a palavra em hebraico para Cristo em grego.\f* da genealogia
```

**Depois (Texto processado):**
- `text`: `"Este livro é o registro{{1}} da genealogia"`
- `references[0].slug`: `"1"`
- `references[0].reference_text`: `"Ou \"Cristo.\" Messias é a palavra em hebraico para Cristo em grego."`

### 4. Limpeza de Texto

O adapter remove:
- Marcadores USFM de referências (`\f`, `\fr`, `\ft`, `\f*`)
- Espaços extras
- Mantém apenas o texto limpo com os marcadores `{{slug}}`

## Estrutura de Dados

### Versículo com Referências

```json
{
  "id": 1,
  "number": 1,
  "text": "Este livro é o registro{{1}} da genealogia de Jesus Cristo{{2}}",
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
      "reference_text": "Segunda referência",
      "target_book_abbreviation": null,
      "target_chapter": null,
      "target_verse": null
    }
  ]
}
```

### Slug das Referências

- **Formato**: Número sequencial como string (`"1"`, `"2"`, `"3"`, ...)
- **Unicidade**: Único por versículo (cada versículo começa do 1)
- **Marcador no texto**: `{{slug}}` (com chaves duplas)
- **Geração**: Automática durante o processamento, na ordem de aparição

## Fluxo de Importação

1. **Validação**: Verifica extensão e nome do arquivo
2. **Parsing**: Processa linhas do arquivo USFM
3. **Extração**: Identifica marcadores (`\h`, `\c`, `\p`, `\v`)
4. **Processamento de Referências**: 
   - Extrai referências do texto
   - Gera slugs
   - Substitui no texto
5. **Criação de DTOs**: Constrói `BookDTO`, `ChapterDTO`, `VerseDTO`, `VerseReferenceDTO`
6. **Validação**: Valida estrutura através do `VersionValidator`
7. **Importação**: Salva no banco através do `VersionImporter`

## Testes Implementados

**Arquivo:** `tests/Unit/Services/Version/Adapters/UsfmAdapterTest.php`

### Cobertura de Testes

✅ Adaptação de arquivo USFM válido  
✅ Extração do nome do livro (`\h`)  
✅ Extração de capítulos (`\c`)  
✅ Extração de versículos (`\v`)  
✅ Extração e processamento de referências  
✅ Múltiplas referências no mesmo versículo  
✅ Adição de `\n` quando encontra marcador `\p`  
✅ Validação de extensão de arquivo  
✅ Validação de nome de arquivo  
✅ Exceção quando nome do livro está ausente  
✅ Processamento de múltiplos arquivos  
✅ Limpeza do texto (remoção de marcadores)  
✅ Suporte a nomes de arquivo case-insensitive  

## Documentação

### 1. Documentação de Referências
**Arquivo:** `docs/verse-references.md`

Documentação completa para implementação no frontend, incluindo:
- Visão geral do sistema
- Estrutura de dados
- Exemplos de código JavaScript/React
- Sugestões de UX
- Guia de implementação passo a passo

### 2. Contexto (Este Arquivo)
**Arquivo:** `docs/context-usfm-adapter.md`

Documentação técnica completa da implementação.

## Exemplo de Uso

### Arquivo USFM de Exemplo

```
\id MAT - Bíblia Livre Para Todos 
\h Mateus 
\c 1  
\p  
\v 1 Este livro é o registro da genealogia de Jesus Cristo\f + \fr 1:1 \ft Ou "Cristo." Messias é a palavra em hebraico para Cristo em grego.\f*, filho de Davi, filho de Abraão: 
\p  
\v 2 Abraão gerou\f + \fr 1:2 \ft "Gerou": ou "era o pai de."\f* Isaque; Isaque gerou Jacó;
```

### Como Usar na API

```php
$file = new FileDTO(
    content: $usfmContent,
    fileName: 'mat.usfm',
    extension: 'usfm'
);

$adapter = VersionAdapterFactory::make('usfm');
$versionDTO = $adapter->adapt([$file]);
```

### Endpoint de Importação

```http
POST /api/admin/versions
Content-Type: multipart/form-data

{
  "files": [arquivo1.usfm, arquivo2.usfm, ...],
  "adapter": "usfm",
  "abbreviation": "NVI",
  "name": "Nova Versão Internacional",
  "language": "pt",
  "copyright": "Copyright info"
}
```

## Decisões de Design

### 1. Slug Simples
- **Decisão**: Usar números sequenciais (`"1"`, `"2"`, ...) em vez de hash MD5
- **Motivo**: Mais legível e fácil de trabalhar no frontend
- **Formato no texto**: `{{slug}}` com chaves duplas

### 2. Processamento Único
- **Decisão**: Processar referências e substituir no texto em uma única passagem
- **Motivo**: Garantir consistência entre slug no DTO e no texto
- **Implementação**: Método `processReferences()` que faz tudo de uma vez

### 3. Validação de Nome do Arquivo
- **Decisão**: Validar nome do arquivo contra `BookAbbreviationEnum`
- **Motivo**: Garantir que cada arquivo corresponde a um livro válido
- **Benefício**: Identificação automática do livro sem precisar parsear `\id`

### 4. Referências Sem Parsing
- **Decisão**: Não parsear `target_book_abbreviation`, `target_chapter`, `target_verse`
- **Motivo**: Simplificar implementação inicial
- **Futuro**: Pode ser implementado posteriormente se necessário

## Melhorias Futuras Possíveis

1. **Parsing de Referências**: Extrair livro, capítulo e versículo das referências
2. **Suporte a Mais Marcadores USFM**: `\q`, `\s`, `\d`, etc.
3. **Validação de Estrutura**: Verificar se todos os capítulos/versículos estão presentes
4. **Otimização**: Processamento em lote para múltiplos arquivos grandes
5. **Tratamento de Erros**: Mensagens de erro mais específicas para debugging

## Dependências

- Laravel Framework
- `BookAbbreviationEnum` - Enum com abreviações dos livros
- `VersionImportException` - Exceção customizada
- Collections do Laravel

## Notas Técnicas

- O adapter processa cada arquivo independentemente
- Cada arquivo deve representar um único livro
- O conteúdo do versículo sempre está na mesma linha (não há continuação)
- Marcadores USFM são case-sensitive (`\c` não é `\C`)
- Espaços em branco são preservados, mas espaços extras são removidos
