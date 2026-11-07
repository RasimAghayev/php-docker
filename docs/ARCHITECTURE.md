# Architecture — php-docker

## Purpose

"Translate This" — a Docker Compose demo (nginx + PHP-FPM + MySQL + Redis). Pick a language, enter a phrase, get a translation back from a MySQL-backed lookup table cached in Redis. Personal exercise, not a production product.

## Stack

| Layer | Component | Version / source |
|---|---|---|
| Language/runtime | PHP | 8.5.11-fpm-alpine (image); `^8.4` floor in `app/composer.json` |
| Framework | none | flat repository/cache layering, no router, no framework |
| Web server | nginx | `nginx:latest` base; fastcgi to `app:9000` |
| Database | MySQL | `mysql:latest`; schema + seed in `sql/docker-php.sql` |
| Cache | Redis | `redis:latest` via `symfony/cache` RedisAdapter + `predis/predis` |
| PHP packages (runtime) | symfony/cache ^8.0, predis/predis ^3.0 | Packagist |
| PHP packages (dev) | phpunit/phpunit ^13.0, nunomaduro/collision ^8.0 | Packagist |
| Build | Docker multi-stage | `php:8.5.11-fpm-alpine` → `composer:2.10` → `app` / `app_dev` |
| CI | GitHub Actions | `.github/workflows/deploy.yaml`; `actions/checkout@v4.2.2` |

## Module map

```
app/public/index.php     -- single front controller (GET dropdown / POST lookup)
app/src/
  Repository/
    Repository.php          -- abstract base, PDO singleton via Connection::getInstance()
    LanguageRepository.php  -- SELECT * FROM language
    TranslationRepository.php -- prepared SELECT translation WHERE language_id + phrase
  Database/
    Connection.php          -- PDO singleton, dsn from $_ENV, ERRMODE_EXCEPTION
  Cache/
    TranslationCache.php    -- RedisAdapter decorator around TranslationRepository
  Model/
    Language.php, Translation.php -- plain data holders
app/tests/
  LanguageTest.php          -- 1 trivial PHPUnit test
sql/docker-php.sql          -- language + translation tables, 3 languages × 1 row (never auto-loaded)
nginx/Dockerfile, conf.d/default.conf   -- nginx + fastcgi config
php/Dockerfile, conf.d/xdebug.ini       -- multi-stage PHP-FPM image (app / app_dev)
docker-compose.yaml, docker-compose.dev.yaml -- prod + dev compose stacks
.github/workflows/deploy.yaml           -- build + push rasimaghayev/* images
```

## Data flow

1. `GET /` → `LanguageRepository::findAll()` renders the language dropdown (French/German/Spanish from seed rows).
2. `POST /` (language + phrase) → `TranslationCache::findForLanguage()`:
   - Cache key `translation;<id>;<phrase>` via Symfony Cache RedisAdapter.
   - Cache miss → `TranslationRepository::findForLanguage()` → PDO prepared statement against `translation` table → result cached and returned.
3. No migrations, no transactions, no background jobs, no async.

## Boundaries / external systems

- MySQL (`db` service) and Redis (`cache` service) are the only external dependencies, reached via env vars (`MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`, `REDIS_HOST`, `REDIS_PORT`).
- No other services, no message broker, no object storage, no third-party API calls.
- Frontend assets: Bootstrap 5.2.2 from CDN (jsdelivr), no build step.

## Design principles applied / declined

- **KISS / YAGNI**: applied. Single file front controller, flat repository/cache layering, no DDD/CQRS/hexagonal boundaries — none warranted for a demo. No architectural pattern earns its place here; this is the explicit Phase 8 decline.
- **SOLID**: not applied — tiny script-sized codebase, no classes with several reasons to change.
- **DRY**: partially — `Repository` base + two concrete repositories is a small repetition win; `TranslationCache` decorator is the one real abstraction.
- **DDD / EDA / modular monolith**: declined — no real domain complexity, no external-system swap need, single deployable demo.

## Decision log

None yet (first architecture pass). Future ADRs go in `docs/adr/`.
