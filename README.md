# Nutria

Nutria é uma aplicação Laravel 13 + Vue 3 para planejamento alimentar, cadastro de pacientes, alimentos, receitas e análise nutricional.

## Arquitetura de produção

- **Aplicação:** Laravel 13 + Vue 3/Vite
- **Deploy:** Vercel Container Function com FrankenPHP
- **Banco:** PostgreSQL do Supabase
- **Sessões:** tabela `sessions` no Supabase
- **Cache:** tabelas `cache` / `cache_locks` no Supabase
- **Filas:** `sync` no ambiente Vercel
- **Logs:** `stderr`, visíveis nos logs da Vercel

O container é stateless. Não use SQLite nem arquivos locais do container como armazenamento persistente em produção.

## 1. Desenvolvimento local

O projeto continua compatível com SQLite local:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

## 2. Criar o projeto no Supabase

1. Crie o projeto no Supabase.
2. Abra o **SQL Editor** e execute `database/supabase/init.sql`.
3. Em **Connect**, copie a string do **Session Pooler** na porta `5432`.
4. Se a senha tiver caracteres especiais, aplique URL encoding antes de colocá-la em `DB_URL`.
5. Use SSL obrigatório (`DB_SSLMODE=require`).

A aplicação usa o schema `nutria`, separado do schema `public`, para não expor automaticamente as tabelas internas pela Data API do Supabase.

## 3. Variáveis da Vercel

Use `.env.vercel.example` como referência e cadastre os valores em **Project Settings > Environment Variables**.

Variáveis essenciais:

```dotenv
APP_NAME=Nutria
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://seu-projeto.vercel.app

DB_CONNECTION=pgsql
DB_URL=postgres://postgres.PROJECT_REF:SENHA@POOLER_HOST:5432/postgres
DB_SCHEMA=nutria
DB_SSLMODE=require

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

NUTRIA_ADMIN_NAME="Administrador Nutria"
NUTRIA_ADMIN_EMAIL=admin@exemplo.com
NUTRIA_ADMIN_SETUP_TOKEN=um-segredo-longo-e-aleatorio
```

Gere `APP_KEY` com:

```bash
php artisan key:generate --show
```

Use uma chave estável em produção; não gere uma nova chave a cada deploy.

## 4. Criar as tabelas no Supabase

As migrations devem ser executadas fora do startup do container:

```bash
php artisan migrate --force
```

Para uma instalação nova com os dados nutricionais de referência:

```bash
php artisan db:seed --force
```

O primeiro acesso do administrador usa `NUTRIA_ADMIN_EMAIL` e `NUTRIA_ADMIN_SETUP_TOKEN`.

## 5. Migrar os dados atuais do SQLite (opcional)

Se quiser levar os usuários, pacientes, planos, alimentos personalizados e demais dados persistentes do `database/database.sqlite` atual para o Supabase, configure temporariamente no terminal as variáveis PostgreSQL do Supabase, execute as migrations e depois rode:

```bash
php artisan nutria:migrate-sqlite-to-supabase --dry-run
php artisan nutria:migrate-sqlite-to-supabase
```

O comando faz `upsert` das tabelas persistentes e reajusta as sequências de IDs do PostgreSQL. Sessões, cache e filas locais não são migrados.

## 6. Deploy na Vercel

Arquivos de produção já incluídos:

- `Dockerfile.vercel`
- `Caddyfile`
- `vercel.json`
- `.dockerignore`
- `.env.vercel.example`

Via CLI:

```bash
npm install -g vercel
vercel login
vercel deploy
vercel deploy --prod
```

Também é possível importar o repositório Git diretamente na Vercel. O `Dockerfile.vercel` será detectado e o tráfego será encaminhado para o serviço Laravel.

## 7. Checklist antes de produção

- `APP_DEBUG=false`
- `APP_KEY` definida e estável
- `DB_URL` usando o Session Pooler do Supabase
- `DB_SCHEMA=nutria`
- `DB_SSLMODE=require`
- `SESSION_SECURE_COOKIE=true`
- migrations executadas
- administrador inicial configurado
- login, cadastro, aprovação de usuários e CRUD de pacientes/planos testados no domínio final
