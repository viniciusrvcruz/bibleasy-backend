# Importação de versões

Fluxo **admin** que cria uma tradução a partir de arquivos (USFM ou JSON), valida a estrutura e grava livros, capítulos, versículos e referências no PostgreSQL.

A importação acontece só na **criação**. Atualizar uma versão não reprocessa arquivos.

Rotas (Sanctum `auth:admins`):

- `POST /api/admin/versions` — criar e importar
- `PUT /api/admin/versions/{version}` — só metadados
- `DELETE /api/admin/versions/{version}` — remove a versão e, em cascata, livros, capítulos, versículos e referências

Listagem pública: `GET /api/versions` (sem auth). Sem token de admin (inclusive com token de usuário comum, `auth:users`), as rotas `/api/admin/*` respondem 401.

## O que o cliente envia

`POST /api/admin/versions` é `multipart/form-data`. `VersionRequest` valida os campos; `VersionImportDTOFactory` monta o `VersionImportDTO`.

| Campo | Criação | Atualização | Regras |
|-------|---------|-------------|--------|
| `files` | obrigatório | proibido | array com pelo menos um arquivo |
| `files.*` | obrigatório | proibido | cada item é um arquivo |
| `adapter` | obrigatório | proibido | `usfm`, `json_thiago_bodruk` ou `json_youversion` |
| `abbreviation` | obrigatório | obrigatório | string, máx. 20; único por `language` |
| `name` | obrigatório | obrigatório | string, máx. 255 |
| `language` | obrigatório | obrigatório | `pt_br` ou `en` (`VersionLanguageEnum`) |
| `copyright` | opcional | opcional | string, máx. 2000 |
| `text_source` | obrigatório | opcional | `database` ou `api_bible` |
| `external_version_id` | obrigatório se a fonte não for `database` | obrigatório, a menos que `text_source=database` seja enviado no mesmo request | ID da tradução na Api.Bible |
| `cache_ttl` | obrigatório se a fonte não for `database` | obrigatório, a menos que `text_source=database` seja enviado no mesmo request | inteiro ≥ 1, em segundos |

Metadados (`abbreviation`, `name`, `language`, `copyright`, `text_source`, `external_version_id`, `cache_ttl`) vão para a tabela `versions`. O conteúdo dos arquivos vira livros, capítulos, versículos e referências.

Resposta `201` (`VersionResource`, sem wrapper `data`):

```json
{
  "id": 1,
  "abbreviation": "ARA",
  "name": "Almeida Revista e Atualizada",
  "language": "pt_br",
  "copyright": "..."
}
```

## Pipeline

```mermaid
flowchart LR
    A[VersionRequest] --> B[VersionImportDTOFactory]
    B --> C[VersionImportService]
    C --> D[VersionAdapterFactory]
    D --> E[adapt files → VersionDTO]
    E --> F[VersionValidator]
    F --> G[DB transaction]
    G --> H[Version::create]
    H --> I[VersionImporter]
```

1. **Validação HTTP** — campos, adapter conhecido, unicidade de abreviação por idioma.
2. **DTO de entrada** — cada upload vira `FileDTO` (`content`, `fileName`, `extension`). `cache_ttl` só entra no DTO quando `text_source` não é `database` (nesse caso fica `null`).
3. **Adaptação** — o adapter do campo `adapter` lê os arquivos e devolve um `VersionDTO`.
4. **Validação de domínio** — `VersionValidator` confere a árvore e as regras do `text_source`.
5. **Persistência** — numa única transação: cria a versão e insere o conteúdo. Qualquer falha desfaz os dois.

Core do serviço:

```19:41:app/Services/Version/VersionImportService.php
    public function import(VersionImportDTO $dto): Version
    {
        $adapter = VersionAdapterFactory::make($dto->adapterName);

        $versionData = $adapter->adapt($dto->files);

        $this->validator->validate($versionData, $dto->textSource);

        return DB::transaction(function () use ($dto, $versionData) {
            $version = Version::create([
                'abbreviation' => $dto->versionAbbreviation,
                'name' => $dto->versionName,
                'language' => $dto->language,
                'copyright' => $dto->copyright,
                'text_source' => $dto->textSource,
                'external_version_id' => $dto->externalVersionId,
                'cache_ttl' => $dto->cacheTtl,
            ]);

            $this->importer->import($versionData, $version->id);

            return $version;
        });
    }
```

### Arquivos centrais

| Arquivo | Papel |
|---------|-------|
| `app/Http/Controllers/VersionController.php` | `store` chama o serviço; `update` e `destroy` não reimportam |
| `app/Http/Requests/VersionRequest.php` | Regras HTTP |
| `app/Services/Version/Factories/VersionImportDTOFactory.php` | Request → `VersionImportDTO` |
| `app/Services/Version/VersionImportService.php` | Orquestra adapt → validar → transação |
| `app/Services/Version/Factories/VersionAdapterFactory.php` | Registry de formatos de arquivo |
| `app/Services/Version/Validators/VersionValidator.php` | Integridade estrutural e de conteúdo |
| `app/Services/Version/Importers/VersionImporter.php` | Insere books, chapters, verses, verse_references |

Árvore de DTOs depois da adaptação: `VersionDTO` → `BookDTO` → `ChapterDTO` → `VerseDTO` + `VerseReferenceDTO`.

## `text_source`

O campo decide **onde o texto do versículo mora depois da importação**. A leitura de capítulo usa outro adapter; ver [Capítulos e fontes de texto](../chapters/text-sources.md).

| Valor | Texto gravado nos versículos | Leitura posterior |
|-------|------------------------------|-------------------|
| `database` | Obrigatório e não vazio | `DatabaseChapterAdapter` lê o PostgreSQL |
| `api_bible` | Deve ser string vazia | `ApiBibleChapterAdapter` busca o texto na Api.Bible usando `external_version_id` e cacheia por `cache_ttl` segundos |

Escolha o adapter de arquivo de acordo com a fonte:

- texto completo no arquivo → `usfm` ou `json_thiago_bodruk` com `text_source=database`
- só a estrutura (livro / capítulo / versículo), texto vem da Api.Bible → `json_youversion` com `text_source=api_bible`

`usfm` e `json_thiago_bodruk` preenchem o texto. Usá-los com `api_bible` falha na validação (`non_empty_verse_for_external_source`). `json_youversion` grava texto vazio. Usá-lo com `database` falha com `empty_verse`.

## Adapters de formato

Registrados em `VersionAdapterFactory::$adapters`. Cada um implementa `VersionAdapterInterface::adapt(files): VersionDTO`.

| Nome no request | Classe | Arquivos | Texto do versículo |
|-----------------|--------|----------|--------------------|
| `usfm` | `UsfmAdapter` | um `.usfm` por livro | completo, com referências virando `{{slug}}` |
| `json_thiago_bodruk` | `JsonThiagoBodrukAdapter` | um `.json` | strings do JSON |
| `json_youversion` | `JsonYouVersionAdapter` | um `.json` | sempre `''` |

Nome desconhecido na factory lança `VersionImportException` (`adapter_not_found`). Na borda HTTP, um nome fora da lista já falha na validação do `VersionRequest` antes de chegar no serviço.

### `json_thiago_bodruk`

Um único JSON: array de livros na **ordem canônica** de `BookAbbreviationEnum` (Gênesis → Apocalipse). A abreviação do livro é o case do enum na mesma posição do array (`cases()[$index]`), não um campo do JSON.

```json
[
  {
    "name": "Gênesis",
    "chapters": [
      ["No princípio criou Deus os céus e a terra.", "E a terra era sem forma e vazia."]
    ]
  }
]
```

- `chapters[i]` é o capítulo `i + 1`
- cada string é o versículo `j + 1`
- não há referências cruzadas
- extensão precisa ser `.json`

Fonte do formato: [thiagobodruk/bible](https://github.com/thiagobodruk/bible/tree/master/json).

### `json_youversion`

Um único JSON com a chave `books`. O `id` do livro (em minúsculas) precisa existir em `BookAbbreviationEnum` (`gen`, `exo`, `1sa`, …). Capítulos e versículos usam `id` numérico. O texto é ignorado: cada verso é persistido com string vazia.

```json
{
  "books": [
    {
      "id": "gen",
      "title": "Gênesis",
      "chapters": [
        {
          "id": "1",
          "verses": [{ "id": "1" }, { "id": "2" }]
        }
      ]
    }
  ]
}
```

Use com `text_source=api_bible`, `external_version_id` e `cache_ttl`.

### `usfm`

Um arquivo `.usfm` por livro. A primeira linha precisa começar com `\id ` seguido de três letras que, em minúsculas, existam em `BookAbbreviationEnum` (ex.: `\id GEN`). O nome do livro vem da linha `\h `.

```text
\id GEN
\h Gênesis
\c 1
\v 1 No princípio criou Deus os céus e a terra.
```

Pacote `app/Services/Version/Adapters/Usfm/`:

| Classe | O que faz |
|--------|-----------|
| `UsfmLineParser` | Lê `\id`, `\h`, `\c`, `\v` e marcadores de parágrafo |
| `UsfmBookParser` | Monta capítulos e versículos; texto depois de um marcador de parágrafo (`\p`, `\q`, …) continua o versículo anterior, com quebra de linha |
| `UsfmReferenceProcessor` | Extrai notas e as troca por `{{slug}}` |
| `UsfmMarkerCleaner` + `UsfmMarkers` | Remove formatação (`\add`, `\wj`, …) e registra marcador desconhecido no log |

Notas viram `VerseReferenceDTO`. O `slug` é um índice numérico em string, começando em `1` dentro do versículo (`{{1}}`, `{{2}}`, …). Tipos reconhecidos (`UsfmMarkers::NOTE_MARKERS`, mais `\rq`, tratado à parte no `UsfmReferenceProcessor`):

| Marcador | Conteúdo | Exemplo de forma |
|----------|----------|------------------|
| `\f` … `\f*` | nota de rodapé (`\fr` + `\ft`) | `\f + \fr 1.1 \ft cf. Jo 3.16 \f*` |
| `\fe` … `\fe*` | nota final | mesma forma com `\fe` |
| `\x` … `\x*` | referência cruzada (`\xo` + `\xt`) | `\x + \xo 1.1 \xt Jo 1.1 \x*` |
| `\ef` / `\ex` | notas e referências estendidas | mesma forma dos pares acima |
| `\rq` … `\rq*` | citação inline | `\rq Jo 1.1 \rq*` |

O texto da nota (já sem marcadores de formatação) vai para `verse_references.text`. No versículo fica só o placeholder.

Depois da adaptação, `VersionImporter` reordena os livros pela posição em `BookAbbreviationEnum`, então a ordem dos arquivos no upload não define `books.order`.

## Validação (`VersionValidator`)

Roda depois do adapter e antes da transação. Sempre exige a árvore: cada livro é `BookDTO` com capítulos; cada capítulo é `ChapterDTO` com versículos; cada versículo é `VerseDTO`.

Para `database`:

- texto do versículo não vazio (depois de `trim`)
- referências com `slug` e `text` preenchidos
- o texto do versículo contém `{{slug}}` de cada referência
- sem marcadores USFM residuais (`\add`, `\+add`, …) e sem chaves soltas que não sejam um placeholder válido

Para `api_bible` (qualquer fonte que não seja `database`):

- texto do versículo precisa ser exatamente `''`
- referências e marcadores residuais não são checados

## Persistência (`VersionImporter`)

Dentro da mesma transação do `Version::create`:

1. Ordena os livros por `BookAbbreviationEnum` e grava `books` (`name`, `abbreviation`, `order`).
2. Grava `chapters` (`number`, `book_id`).
3. Insere `verses` em lote (`number`, `text`).
4. Se o versículo tiver referências, insere `verse_references` (`slug`, `text`) também em lote.

`PUT` não chama o importer. `DELETE` apaga a linha em `versions`; as FKs usam `cascadeOnDelete` até `verse_references`. O model `Version` não usa soft delete.

## Erros

Falhas de domínio lançam `VersionImportException` (HTTP 422):

```json
{ "error": "empty_verse", "message": "..." }
```

Códigos usados com frequência: `no_files`, `invalid_file_extension`, `invalid_format`, `missing_books`, `missing_chapters`, `missing_verses`, `empty_verse`, `non_empty_verse_for_external_source`, `missing_slug_in_verse_text`, `adapter_not_found`. O adapter USFM e os JSON acrescentam códigos próprios (`missing_id_marker`, `invalid_book_abbreviation`, `missing_book_name`, `missing_book_id`, …).

Erro de campo do `VersionRequest` (adapter inválido, arquivo ausente, abreviação duplicada) segue a validação padrão do Laravel, também 422, com outro formato de corpo.

Contrato geral: [api/exceptions-and-responses.md](../../api/exceptions-and-responses.md).

## Adicionar um novo formato de arquivo

1. Implementar `VersionAdapterInterface` e devolver um `VersionDTO` válido para o `text_source` pretendido.
2. Registrar a classe em `VersionAdapterFactory::$adapters` com um nome estável. `VersionRequest` já aceita o que `getAvailableAdapterNames()` listar.
3. Testes: copie o padrão em `tests/Unit/Services/Version/` e `tests/Feature/Version/` (ver [testing/overview.md](../../testing/overview.md)).

Documentação a atualizar na mesma mudança:

| Onde | O que incluir |
|------|----------------|
| Este arquivo, tabela do campo `adapter` | Nome aceito no request |
| Este arquivo, tabela **Adapters de formato** | Classe, arquivos esperados e se o texto do versículo vem preenchido |
| Este arquivo, nova subseção do adapter | Exemplo do arquivo, como livros/capítulos/versículos são lidos e com qual `text_source` usar |
| Este arquivo, seção **Erros** | Códigos novos de `VersionImportException`, se houver |
| [README.md](../../../README.md), tabela **Formatos suportados** | Linha do adapter |
| [README.md](../../../README.md), passo **Adaptação** do fluxo | Nome do adapter na lista |

## Adicionar novo `text_source`

1. Case em `VersionTextSourceEnum`.
2. Regras em `VersionValidator` e `VersionRequest` (`external_version_id` / `cache_ttl`).
3. Case em `ChapterSourceAdapterFactory` (ver [Capítulos](../chapters/text-sources.md)).
4. Adapter de import compatível: texto cheio para fonte local, texto vazio para fonte externa.

Documentação a atualizar na mesma mudança:

| Onde | O que incluir |
|------|----------------|
| Este arquivo, tabela do campo `text_source` e seção **`text_source`** | Valor, se o texto do versículo é obrigatório ou vazio, e qual adapter de arquivo combina |
| [README.md](../../../README.md), tabela **Fonte do texto da versão** | Valor e de onde o texto é lido |
| [chapters/text-sources.md](../chapters/text-sources.md), tabela **Fonte do texto** | Valor, adapter de capítulo e o que fica no banco |

## Relacionado

- [Capítulos e fontes de texto](../chapters/text-sources.md)
- [Autenticação](../auth/authentication.md) — token de admin para importar
