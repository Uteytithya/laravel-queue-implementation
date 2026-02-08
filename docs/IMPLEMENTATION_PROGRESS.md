# Implementation Progress: Laravel 10 Queue + Workers + Docker

## 1. Goal

Implement a Laravel 10 project that runs in Docker with:

- `nginx` for web traffic
- `php-fpm` for Laravel
- `redis` as queue broker
- scalable queue workers
- queue request tracking APIs (submit, status, list)

## 2. Base Project (Before Changes)

The project started as a standard Laravel 10 skeleton:

- no Docker runtime for this app
- default queue mode was synchronous (`QUEUE_CONNECTION=sync`)
- only default Laravel routes
- no queue request tracking table
- no queue demo APIs

## 3. What Changed From Base Project

### 3.1 Dockerized runtime

Added:

- `docker-compose.yml`
- `docker/php/Dockerfile`
- `docker/php/local.ini`
- `docker/nginx/default.conf`
- `.dockerignore`

Current services:

- `app` (`php-fpm`)
- `nginx`
- `redis`
- `worker` (scalable with `--scale`)

### 3.2 Environment and queue defaults

Updated `/.env.example` to Docker + queue defaults:

- `APP_URL=http://localhost:8080`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=/var/www/database/database.sqlite`
- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST=redis`
- `QUEUE_DEMO_PROCESSING_SECONDS=30`

### 3.3 Queue request tracking schema

Added migration:

- `database/migrations/2026_02_08_103700_create_queue_requests_table.php`

Table: `queue_requests`

- `id` (uuid, PK)
- `message`
- `status` (`queued`, `processing`, `completed`, `failed`)
- `error_message`
- `queued_at`, `started_at`, `finished_at`
- `created_at`, `updated_at`

### 3.4 Queue job implementation

Added job:

- `app/Jobs/DemoQueueJob.php`

Behavior:

- receives `requestId` and `message`
- marks request as `processing`
- sleeps `QUEUE_DEMO_PROCESSING_SECONDS`
- marks request `completed`
- on failure marks request `failed` and stores error

### 3.5 Queue APIs

Implemented in:

- `app/Http/Controllers/QueueDemoController.php`
- `routes/api.php`

Endpoints:

- `POST /api/queue/demo`
- `POST /api/queue/demo/batch`
- `GET /api/queue/demo/{requestId}/status`
- `GET /api/queue/demo/requests`

### 3.6 Tests

Added/updated:

- `tests/Feature/QueueDemoControllerTest.php`
- `phpunit.xml` (testing DB uses sqlite memory)

Coverage includes:

- single dispatch
- batch dispatch
- list requests
- status lookup

### 3.7 Operational fixes made during implementation

- Fixed log permission issue by running `app` and `worker` as `www-data` in `docker-compose.yml`
- Fixed runtime queue mode by updating `/.env` to redis-based settings
- Refactored from fixed `worker-1`/`worker-2` to scalable `worker` service

## 4. Ordered Implementation Steps (How To Build This From Scratch)

1. Configure Docker services (`app`, `nginx`, `redis`, `worker`) in `docker-compose.yml`.
2. Build PHP image with redis and DB extensions in `docker/php/Dockerfile`.
3. Configure nginx fastcgi bridge to app container in `docker/nginx/default.conf`.
4. Set queue and redis env defaults in `/.env.example`.
5. Add queue request tracking migration.
6. Create queue job class that updates lifecycle status.
7. Create controller endpoints for dispatch, status, and list.
8. Register API routes.
9. Add feature tests and run them.
10. Start stack and scale workers with `--scale worker=N`.
11. Validate via API calls and `docker compose logs -f worker`.

## 5. How It Works End-to-End

1. Client calls `POST /api/queue/demo` (or batch endpoint).
2. API creates row(s) in `queue_requests` with `status=queued`.
3. API dispatches `DemoQueueJob` to Redis queue.
4. Worker picks job, updates status to `processing`.
5. Job runs (includes configured delay), then marks `completed`.
6. Client polls status endpoint or lists all requests.

## 6. How To Use (Current Project)

### 6.1 Initial setup

```powershell
Copy-Item .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
New-Item -Path .\database\database.sqlite -ItemType File -Force
docker compose run --rm app php artisan migrate
docker compose up -d --build --scale worker=2
```

### 6.2 Queue APIs

Queue one:

```powershell
curl.exe -X POST http://localhost:8080/api/queue/demo -H "Content-Type: application/json" -d "{\"message\":\"hello\"}"
```

Queue batch:

```powershell
curl.exe -X POST http://localhost:8080/api/queue/demo/batch -H "Content-Type: application/json" -d "{\"total\":5,\"prefix\":\"demo\"}"
```

Check one request:

```powershell
curl.exe http://localhost:8080/api/queue/demo/{request_id}/status
```

List all requests:

```powershell
curl.exe http://localhost:8080/api/queue/demo/requests
```

Filter by status:

```powershell
curl.exe "http://localhost:8080/api/queue/demo/requests?status=processing"
```

### 6.3 Worker scaling

Scale workers up:

```powershell
docker compose up -d --scale worker=6
```

Check:

```powershell
docker compose ps
docker compose logs -f worker
```

## 7. Important Notes

- Each worker replica processes one job at a time.
- Total parallelism = number of worker replicas.
- Redis is used for broker/transport.
- `queue_requests` table is used for persistent business status APIs.

## 8. Known Troubleshooting

If queue responses show `"queue_connection":"sync"`:

- verify `/.env` has `QUEUE_CONNECTION=redis`
- restart app/worker containers

If migrations fail with missing table errors:

- run `docker compose exec app php artisan migrate`

If Docker reports old renamed services:

- run `docker compose up -d --remove-orphans`
