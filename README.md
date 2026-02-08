# Laravel 10 + Nginx + Redis Queue Workers (Docker)

For full implementation history, baseline-vs-current diff, and architecture notes, see:
`docs/IMPLEMENTATION_PROGRESS.md`

For chronological change history, see:
`CHANGELOG.md`

This project is configured to run Laravel 10 with:

- `nginx` for HTTP serving
- `php-fpm` for app runtime
- `redis` as queue broker
- scalable queue workers via one `worker` service (replicas via `--scale`)

## Stack

- App: `php:8.2-fpm-alpine`
- Web: `nginx:1.27-alpine`
- Queue broker: `redis:7-alpine`
- Queue driver: Laravel `redis`

## Files Added For Docker

- `docker-compose.yml`
- `docker/php/Dockerfile`
- `docker/php/local.ini`
- `docker/nginx/default.conf`
- `.dockerignore`

## Queue Demo Implementation

- Job: `app/Jobs/DemoQueueJob.php`
- Controller: `app/Http/Controllers/QueueDemoController.php`
- API routes: `routes/api.php`
- Queue request migration: `database/migrations/2026_02_08_103700_create_queue_requests_table.php`
- Feature test: `tests/Feature/QueueDemoControllerTest.php`

## 1) First-Time Setup

1. Copy env file (PowerShell):

```bash
Copy-Item .env.example .env
```

2. Install PHP dependencies (inside container):

```bash
docker compose run --rm app composer install
```

3. Generate app key:

```bash
docker compose run --rm app php artisan key:generate
```

4. Create SQLite database file:

```bash
New-Item -Path .\database\database.sqlite -ItemType File -Force
```

5. Run migrations:

```bash
docker compose run --rm app php artisan migrate
```

6. Start all services:

```bash
docker compose up -d --build --scale worker=2
```

## 2) Verify Services

- App URL: `http://localhost:8080`
- Check running containers:

```bash
docker compose ps
```

- Watch worker logs:

```bash
docker compose logs -f worker
```

## 3) Queue Usage Examples

### Queue a single job

```bash
curl.exe -X POST http://localhost:8080/api/queue/demo -H "Content-Type: application/json" -d "{\"message\":\"Process this task\"}"
```

Expected response (`202`):

```json
{
  "status": "queued",
  "request_id": "2f5f73b3-a852-4194-9b20-6a76d6364852",
  "message": "Process this task",
  "queue_connection": "redis"
}
```

### Queue multiple jobs

```bash
curl.exe -X POST http://localhost:8080/api/queue/demo/batch -H "Content-Type: application/json" -d "{\"total\":10,\"prefix\":\"bulk\"}"
```

Expected response (`202`):

```json
{
  "status": "queued",
  "queued_jobs": 10,
  "request_ids": [
    "fc8e4f9f-1a3a-4cf3-ab16-3fb7d9e945f4",
    "8f03d78a-df09-4c44-a70a-9a7f847e89fe"
  ],
  "queue_connection": "redis"
}
```

### Check queue request status

```bash
curl.exe http://localhost:8080/api/queue/demo/{request_id}/status
```

Example response:

```json
{
  "request_id": "2f5f73b3-a852-4194-9b20-6a76d6364852",
  "message": "Process this task",
  "status": "completed",
  "error_message": null,
  "queued_at": "2026-02-08 10:40:15",
  "started_at": "2026-02-08 10:40:15",
  "finished_at": "2026-02-08 10:40:16"
}
```

### List all queue requests

```bash
curl.exe "http://localhost:8080/api/queue/demo/requests"
```

Optional status filter:

```bash
curl.exe "http://localhost:8080/api/queue/demo/requests?status=processing"
```

## 4) How Worker Processing Works

- Requests enqueue jobs into Redis.
- `worker` runs `php artisan queue:work redis`.
- Scale workers with `docker compose up -d --scale worker=4`.
- Each job waits `QUEUE_DEMO_PROCESSING_SECONDS` in `DemoQueueJob::handle()` before finishing.
- Each worker processes one job at a time. A worker only pulls its next job after completing the current one.

To inspect app logs:

```bash
docker compose logs -f app worker
```

## 5) Implement Your Own Queue Job

1. Create a job class:

```bash
docker compose exec app php artisan make:job SendReminderJob
```

2. In the job's `handle()` method, add your background logic.

3. Dispatch it from controller/service code:

```php
SendReminderJob::dispatch($payload);
```

4. Worker replicas (`worker`) will automatically consume it from Redis.

## 6) Useful Commands

- Restart workers:

```bash
docker compose restart worker
```

- Scale worker count:

```bash
docker compose up -d --scale worker=6
```

- Run tests:

```bash
docker compose run --rm app php artisan test
```

- Stop and remove stack:

```bash
docker compose down
```

- Stop and remove stack + Redis data:

```bash
docker compose down -v
```

## Notes

- `.env.example` is configured for Docker queue usage:
  - `QUEUE_CONNECTION=redis`
  - `REDIS_HOST=redis`
  - `QUEUE_DEMO_PROCESSING_SECONDS=30`
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=/var/www/database/database.sqlite`
- `worker` is horizontally scalable. Each replica consumes from the same Redis-backed queue.
