# Changelog

All notable changes to this project are documented in this file.

## 2026-02-08

### Added

- Dockerized Laravel runtime:
  - `docker-compose.yml` with `app`, `nginx`, `redis`, and scalable `worker` service
  - `docker/php/Dockerfile`
  - `docker/php/local.ini`
  - `docker/nginx/default.conf`
  - `.dockerignore`
- Queue demo job:
  - `app/Jobs/DemoQueueJob.php`
- Queue tracking schema:
  - `database/migrations/2026_02_08_103700_create_queue_requests_table.php`
- Queue API features:
  - `POST /api/queue/demo`
  - `POST /api/queue/demo/batch`
  - `GET /api/queue/demo/{requestId}/status`
  - `GET /api/queue/demo/requests`
- Feature tests:
  - `tests/Feature/QueueDemoControllerTest.php`
- Progress and implementation documentation:
  - `docs/IMPLEMENTATION_PROGRESS.md`

### Changed

- Queue and Docker defaults in `.env.example`:
  - switched to `redis` queue connection
  - switched Redis host to service name (`redis`)
  - switched DB default to SQLite path in container
  - added `QUEUE_DEMO_PROCESSING_SECONDS`
- Controller logic in `app/Http/Controllers/QueueDemoController.php`:
  - now creates tracked queue request IDs
  - supports list/status endpoints
- Job behavior in `app/Jobs/DemoQueueJob.php`:
  - updates tracked statuses (`processing`, `completed`, `failed`)
  - configurable processing delay for observability
- `docker-compose.yml` worker architecture:
  - refactored from fixed `worker-1`/`worker-2` to scalable `worker` service
  - configured app/worker containers to run as `www-data`
- `README.md` updated for setup, API usage, scaling, and links to implementation docs

### Fixed

- Log write permission issue for `storage/logs/laravel.log` by aligning runtime user (`www-data`) across app/worker containers.
- Runtime queue mode mismatch (`sync` vs `redis`) by updating environment configuration and restarting services.
