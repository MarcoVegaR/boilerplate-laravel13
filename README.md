# Boilerplate Laravel 13

Laravel 13 local starter based on the official React starter kit, using Inertia, Fortify, Wayfinder, PostgreSQL, Redis, Mailpit, MinIO, and the Laravel AI SDK.

## Stack

- Laravel 13
- React 19 + Inertia v2
- Tailwind CSS v4
- PostgreSQL
- Redis
- Mailpit
- MinIO
- Laravel Boost + Laravel AI SDK + Gentleman ecosystem workflows

## Starter Kit Notes

This project starts from the official `laravel/react-starter-kit` and already includes authentication through Fortify plus Inertia-based React pages.

## Local Services

The intended local baseline is:

- PostgreSQL on `127.0.0.1:5432`
- Redis on `127.0.0.1:6379`
- Mailpit SMTP on `127.0.0.1:1025`
- Mailpit web UI on `http://127.0.0.1:8025`
- MinIO S3 API on `http://127.0.0.1:9000`
- MinIO console on `http://127.0.0.1:9001`

Environment defaults are configured for:

- locale: `es` with `en` fallback
- faker locale: `es_VE`
- timezone: `America/Caracas`
- cache/session/queue: Redis
- object storage: MinIO through Laravel's `s3` disk

## First-Time Setup

1. Install PHP and Node dependencies:

```bash
composer install
npm install
```

2. Prepare the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

3. Make sure PostgreSQL, Redis, Mailpit, and MinIO are running.

4. Run the database migrations:

```bash
php artisan migrate
```

5. Start the application:

```bash
composer run dev
```

This runs the Laravel server, queue listener, log tailing, and Vite together.

For dedicated local worker processes:

```bash
composer run local:queue
composer run local:schedule
```

## Daily Commands

```bash
composer run dev
composer run local:queue
composer run local:schedule
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

Use `npm run build` when you want a production asset build. Use `npm run dev` if you only want Vite.

## Mailpit

Mail is configured for Mailpit via SMTP:

- `MAIL_MAILER=smtp`
- `MAIL_HOST=127.0.0.1`
- `MAIL_PORT=1025`

Open the inbox UI at `http://127.0.0.1:8025`.

## Redis

Redis is the default local backing service for:

- cache
- sessions
- queues

This keeps local development closer to a Laravel Cloud-style runtime without pretending cloud infrastructure is present.

## MinIO

Local object storage is configured through Laravel's `s3` disk and points to MinIO by default:

- endpoint: `http://127.0.0.1:9000`
- console: `http://127.0.0.1:9001`
- bucket: `boilerplate-laravel13-local`

The MinIO credentials are defined in `.env` through `MINIO_ROOT_USER` and `MINIO_ROOT_PASSWORD`, then reused by the `AWS_*` variables Laravel expects.

## Laravel AI SDK

The official Laravel AI SDK is installed and published.

- Package: `laravel/ai`
- Config: `config/ai.php`
- Conversation tables: published and migrated

Add whichever provider key you want to use in `.env`, for example:

- `OPENAI_API_KEY`
- `GEMINI_API_KEY`
- `ANTHROPIC_API_KEY`
- `OLLAMA_BASE_URL` for local Ollama usage

The default AI config currently points text generation at OpenAI and image generation at Gemini until you choose otherwise.

## AI Tooling In This Repo

- Laravel Boost is available for Laravel-aware docs, logs, database inspection, and framework tooling.
- Laravel AI SDK is available for application-level agents, tools, embeddings, and conversation storage.
- Gentle ecosystem coordination is documented in `AGENTS.md` for memory, SDD workflow, and human-in-the-loop practices.

## Verification

Useful focused checks:

```bash
php artisan test --compact tests/Feature/ExampleTest.php tests/Feature/DashboardTest.php tests/Feature/Auth/AuthenticationTest.php
php artisan config:show filesystems.disks.s3
php artisan tinker --execute="dump(Storage::disk('s3')->allFiles())"
vendor/bin/pint --dirty --format agent
php artisan config:show app
```

## Notes

- If frontend changes are not visible, run `npm run build`, `npm run dev`, or `composer run dev`.
- If Redis, Mailpit, or MinIO are not running, queue, session, cache, mail, or object-storage behavior will fail in expected ways until those local services are available.
