# Bible Backend

API backend para leitura bíblica com suporte a múltiplas versões, anotações e marcações de versículos.

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
