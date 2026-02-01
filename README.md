# 📖 Bibleasy Backend

<p align="center">
  <img src="public/logo.png" alt="Bibleasy Logo" width="70" style="border-radius: 10px" />
</p>

<p align="center">
  <strong>
    🌐 <a href="https://bibleasy.com" target="_blank" rel="noreferrer">Ver aplicação no ar</a> &nbsp;•&nbsp;
    🔧 <a href="https://github.com/viniciusrvcruz/bibleasy-frontend" target="_blank" rel="noreferrer">Repositório do Frontend</a>
  </strong>
</p>

**API REST para leitura bíblica com múltiplas versões.** Backend da Bibleasy: listagem de versões, livros e capítulos, comparação entre traduções e sistema de importação de Bíblias em formatos USFM e JSON.

<p align="center">
  <a href="https://laravel.com/" target="_blank" rel="noreferrer"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel" /></a>
  <a href="https://www.php.net/" target="_blank" rel="noreferrer"><img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" /></a>
  <a href="https://www.postgresql.org/" target="_blank" rel="noreferrer"><img src="https://img.shields.io/badge/PostgreSQL-15+-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL" /></a>
  <a href="https://www.docker.com/" target="_blank" rel="noreferrer"><img src="https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker" /></a>
  <a href="LICENSE" target="_blank" rel="noreferrer"><img src="https://img.shields.io/badge/License-MIT-22C55E?style=for-the-badge" alt="License" /></a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker&logoColor=white" alt="Docker" />
</p>

---

## 📋 Índice

- [Visão Geral](#-visão-geral)
- [Funcionalidades](#-funcionalidades)
- [Importação de versões](#-importação-de-versões)
- [Arquitetura](#-arquitetura)
- [Tech Stack](#-tech-stack)
- [Pré-requisitos](#-pré-requisitos)
- [Como Executar](#-como-executar)
- [Endpoints da API](#-endpoints-da-api)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [Comandos Úteis](#-comandos-úteis)
- [Como Contribuir](#-como-contribuir)
- [Contribuidores](#-contribuidores)
- [Licença](#-licença)

---

## 🎯 Visão Geral

O **Bibleasy Backend** é a API do projeto Bibleasy. Expõe versões bíblicas, livros, capítulos e versículos em formato REST, além de comparação entre traduções e importação de novas Bíblias. O frontend consome esta API em <a href="https://bibleasy.com" target="_blank" rel="noreferrer">bibleasy.com</a>.

**Acesse em produção:** <a href="https://bibleasy.com" target="_blank" rel="noreferrer">https://bibleasy.com</a>

---

## ✨ Funcionalidades

| Recurso | Descrição |
| -------- | --------- |
| 📚 **Versões e livros** | Listagem de versões disponíveis e livros por versão |
| 📖 **Capítulos e versículos** | Leitura de capítulos completos com versículos e referências |
| 🔄 **Comparação** | Endpoint para comparar o mesmo capítulo em múltiplas versões |
| 📥 **Importação** | Sistema de importação de Bíblias em USFM e JSON (detalhes abaixo) |
| 🔐 **Autenticação** | Guards separados para usuários e admins (Laravel Sanctum) |
| 🛠️ **CRUD de versões** | Criação, edição e remoção de versões pelo admin |

---

## 📥 Importação de versões

O backend permite importar Bíblias completas a partir de arquivos em formatos suportados. O fluxo usa **Factory + Strategy**: um adapter por formato converte os arquivos em DTOs, um validador garante a integridade dos dados e um importer persiste tudo em transação no PostgreSQL.

### Formatos suportados

| Adapter | Formato | Uso |
| ------- | ------- | --- |
| `usfm` | USFM (Unified Standard Format Markers) | Arquivos `.usfm` por livro; marcadores são limpos e referências processadas |
| `json_thiago_bodruk` | JSON (estrutura Thiago Bodruk) | JSON com livros, capítulos e versículos no formato esperado pelo adapter |

Novos formatos podem ser adicionados implementando `VersionAdapterInterface` e registrando o adapter na `VersionAdapterFactory`.

### Fluxo da importação

1. **Upload / envio** — Os arquivos da versão são enviados para o endpoint de import (admin).
2. **Adaptação** — O adapter escolhido (`usfm` ou `json_thiago_bodruk`) lê os arquivos e gera um `VersionDTO` (lista de `BookDTO` → `ChapterDTO` → `VerseDTO` e `VerseReferenceDTO`).
3. **Validação** — O `VersionValidator` verifica: livros com capítulos, capítulos com versículos, texto dos versículos não vazio, referências com slug e texto válidos, ausência de marcadores USFM residuais e placeholders `{{slug}}` corretos no texto.
4. **Persistência** — Em uma única transação: criação do registro da versão, depois livros, capítulos, versículos e referências cruzadas (`verse_references`).

### Estrutura USFM (resumo)

O adapter USFM utiliza parsers e helpers para:

- **Livros** — Um ou mais arquivos USFM por livro.
- **Marcadores** — Limpeza de marcadores USFM no texto (ex.: `\qt`, `\add`, etc.) via `UsfmMarkerCleaner` e `UsfmMarkers`.
- **Referências** — Extração e normalização de referências (ex.: “cf. Jo 3.16”) em `VerseReferenceDTO` com `slug` e `text`, e substituição no texto do versículo por placeholders `{{slug}}`.

Assim, uma Bíblia importada fica pronta para ser servida pela API com livros, capítulos, versículos e referências clicáveis.

---

## 🗄️ Arquitetura

### Banco de Dados (PostgreSQL)

**Dados bíblicos**

| Tabela | Descrição |
|--------|-----------|
| `versions` | Traduções bíblicas disponíveis |
| `books` | Livros da Bíblia por versão |
| `chapters` | Capítulos por livro |
| `verses` | Versículos com texto completo |
| `verse_references` | Referências de versículos |

**Autenticação e sistema**

| Tabela | Descrição |
|--------|-----------|
| `users` | Usuários da aplicação |
| `admins` | Administradores da plataforma |
| `personal_access_tokens` | Tokens de API (Laravel Sanctum) |

---

## 🛠️ Tech Stack

| Tecnologia | Descrição |
| --------- | --------- |
| <a href="https://laravel.com/" target="_blank" rel="noreferrer">Laravel 12</a> | Framework PHP |
| <a href="https://www.postgresql.org/" target="_blank" rel="noreferrer">PostgreSQL</a> | Banco de dados relacional |
| <a href="https://laravel.com/docs/sanctum" target="_blank" rel="noreferrer">Laravel Sanctum</a> | Autenticação por API tokens |
| <a href="https://www.docker.com/" target="_blank" rel="noreferrer">Docker</a> + Docker Compose | Containerização |

---

## 📋 Pré-requisitos

Antes de começar, certifique-se de ter instalado:

- **Docker**
- **Git**

---

## 🚀 Como Executar

```bash
# 1. Clone o repositório
git clone https://github.com/viniciusrvcruz/bibleasy-backend.git
cd bibleasy-backend

# 2. Copie o arquivo de ambiente
cp .env.example .env

# 3. Suba os containers (setup automático)
docker compose up -d

# 4. Execute o seeder (versão e livros de desenvolvimento)
docker compose exec bible_api php artisan db:seed --class=VersionSeeder

# 5. Crie um usuário admin (apenas em ambiente local/testing)
docker compose exec bible_api php artisan admin:create admin@example.com
```

> **Nota:** Para dados reais de produção, é necessário importar uma versão bíblica via endpoint admin (upload no formato USFM ou JSON suportado).

---


## 📡 Endpoints da API

### Públicos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/versions` | Lista versões disponíveis |
| `GET` | `/api/versions/{version}/books` | Lista livros de uma versão |
| `GET` | `/api/versions/{version}/books/{abbreviation}/chapters/{number}` | Capítulo completo |
| `GET` | `/api/books/{abbreviation}/chapters/{number}/comparison` | Comparação de capítulo entre versões |

### Autenticados (Admin)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/admin/me` | Perfil do admin |
| `POST` | `/api/admin/versions` | Criar versão (incl. importação) |
| `PUT` | `/api/admin/versions/{version}` | Atualizar versão |
| `DELETE` | `/api/admin/versions/{version}` | Remover versão |

### Autenticados (Usuário)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/user` | Perfil do usuário |

---

## 📁 Estrutura do Projeto

```
bibleasy-backend/
├── app/
│   ├── Actions/               # Casos de uso (Book, Chapter)
│   ├── Enums/                 # BookAbbreviation, VersionLanguage, Auth
│   ├── Http/Controllers/      # API e Auth
│   ├── Models/                # Eloquent (Version, Book, Chapter, Verse, User, Admin)
│   └── Services/Version/      # Importação de versões
│       ├── Adapters/          # USFM, JsonThiagoBodruk
│       ├── DTOs/              # VersionDTO, BookDTO, ChapterDTO, VerseDTO, VerseReferenceDTO
│       ├── Factories/         # VersionAdapterFactory
│       ├── Importers/         # VersionImporter
│       ├── Validators/        # VersionValidator
│       └── VersionImportService.php
├── database/
│   ├── migrations/
│   ├── seeders/               # VersionSeeder (versão + livros dev)
│   └── factories/
├── routes/api.php
├── docker-compose.yml
└── docker/php/                # Dockerfile e configs PHP
```

---

## ⚡ Comandos Úteis

| Comando | Descrição |
|---------|-----------|
| `docker compose up -d` | Sobe os containers |
| `docker compose exec bible_api php artisan migrate` | Executa migrations |
| `docker compose exec bible_api php artisan db:seed --class=VersionSeeder` | Popula dados de desenvolvimento |
| `docker compose exec bible_api php artisan admin:create {email}` | Cria admin |
| `docker compose exec bible_api php artisan test` | Executa testes |

---

## 🤝 Como Contribuir

Contribuições são bem-vindas.

### Passos rápidos

1. **Fork** o projeto e **clone** seu fork
2. Crie uma **branch** (`git checkout -b feature/minha-feature` ou `fix/correcao`)
3. Faça suas alterações (código limpo, comentários em **inglês**)
4. **Commit** com Conventional Commits (`feat: add X`, `fix: resolve Y`)
5. **Push** para a branch e abra um **Pull Request**

### Convenção de commits

| Tipo | Uso |
| ---- | --- |
| `feat` | Nova funcionalidade |
| `fix` | Correção de bug |
| `docs` | Documentação |
| `style` | Formatação (sem mudança de lógica) |
| `refactor` | Refatoração |
| `test` | Testes |
| `chore` | Tarefas de manutenção |

---

## 🤝 Contribuidores

<table>
  <tr>
    <td align="center">
      <a href="https://github.com/viniciusrvcruz" target="_blank" rel="noopener noreferrer">
        <img src="https://github.com/viniciusrvcruz.png" width="80px;" alt="Vinicius Cruz"/><br>
        <sub><b>Vinicius Cruz (autor)</b></sub>
      </a><br>
      <a href="https://github.com/viniciusrvcruz" title="GitHub" target="_blank" rel="noopener noreferrer">
        <img src="https://skillicons.dev/icons?i=github" width="25px" />
      </a>
      <a href="https://www.linkedin.com/in/viniciuscruz7" title="LinkedIn" target="_blank" rel="noopener noreferrer">
        <img src="https://skillicons.dev/icons?i=linkedin" width="25px" />
      </a>
    </td>
  </tr>
</table>

---

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo <a href="LICENSE" target="_blank" rel="noreferrer">LICENSE</a> para mais detalhes.

---

<div align="center">
  <p><strong>Backend da Bibleasy</strong></p>
  <p>
    <a href="https://bibleasy.com" target="_blank" rel="noreferrer">🌐 Ver aplicação no ar</a> •
    <a href="https://github.com/viniciusrvcruz/bibleasy-frontend" target="_blank" rel="noreferrer">🔧 Repositório do Frontend</a>
  </p>
</div>
