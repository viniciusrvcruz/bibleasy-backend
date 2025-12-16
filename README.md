# Bible Backend

API REST para uma aplicação completa de leitura bíblica com suporte a múltiplas versões, anotações pessoais, marcações coloridas de versículos e comparação entre versões.

## Sobre o Projeto

Este é o backend de uma plataforma de leitura/estudo bíblico que permite aos usuários:

- **Leitura Bíblica**: Acesso completo aos 66 livros da Bíblia em múltiplas versões e idiomas
- **Múltiplas Versões**: Suporte para importação e gerenciamento de diferentes traduções bíblicas
- **Anotações**: Criação de anotações pessoais em versículos específicos para estudos e reflexões
- **Marcações Coloridas**: Sistema de marcação de versículos com cores personalizáveis para organização visual
- **Comparação de Versões**: Visualização lado a lado de diferentes traduções do mesmo texto
- **Compartilhamento**: Possibilidade de copiar e compartilhar versículos
- **Registros de Leitura**: Histórico de leitura e progresso do usuário

## Arquitetura

### Estrutura de Banco de Dados

O sistema utiliza PostgreSQL com as seguintes tabelas principais:

**Dados Bíblicos:**
- `versions` - Traduções bíblicas disponíveis
- `books` - 66 livros canônicos da Bíblia
- `chapters` - Capítulos de cada livro por versão
- `verses` - Versículos individuais com texto completo

**Dados do Usuário:**
- `users` - Usuários da aplicação (UUID)
- `highlights` - Marcações coloridas de versículos
- `notes` - Anotações pessoais dos usuários
- `note_verse` - Relacionamento N:N entre notas e versículos

**Autenticação e Sistema:**
- `admins` - Administradores da plataforma
- `personal_access_tokens` - Tokens de API (Laravel Sanctum)
- Tabelas padrão do Laravel: `cache`, `jobs`, `sessions`, `password_reset_tokens`

### Funcionalidades do Backend

- **Sistema de Importação**: Factory + Strategy pattern para importar diferentes formatos de Bíblias com validação automática (1.189 capítulos, ~31.100 versículos)
- **API RESTful**: Endpoints para leitura, busca e navegação pelos textos bíblicos
- **Autenticação Dual**: Guards separados para usuários (auth:users) e administradores (auth:admin) via Laravel Sanctum
- **Gestão de Conteúdo**: Painel administrativo para CRUD de versões bíblicas
- **Relacionamentos Complexos**: Suporte a anotações multi-versículos e marcações por usuário

### Stack Tecnológica

- **Framework**: Laravel (PHP)
- **Banco de Dados**: PostgreSQL
- **Autenticação**: Laravel Sanctum (API tokens, e futuramente com cookies http-only)
- **Containerização**: Docker + Docker Compose

## Instalação

```bash
# Clone o repositório
git clone <repository-url>
cd bible-backend

# Copie o arquivo de ambiente
cp .env.example .env

# Suba os containers (o setup é automático)
docker compose up -d

# Execute a seeder dos livros bíblicos
docker compose exec bible_api php artisan db:seed --class=BookSeeder

# Crie um usuário admin (apenas em ambiente local/testing)
docker compose exec bible_api php artisan admin:create admin@example.com
```
