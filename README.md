# php-docker
![Views](https://hits.sh/github.com/RasimAghayev/php-docker.svg?label=views&color=0e75b6)


A small "Translate This" demo: pick a language, enter a phrase, get a
translation back from a MySQL-backed lookup table, cached in Redis. Built
as a personal Docker Compose exercise (nginx + PHP-FPM + MySQL + Redis,
multi-stage Dockerfile, dev/prod compose overlay) rather than a real
product.

## Stack

| Component | Version (as built) |
|---|---|
| PHP | 8.2.12-fpm (Alpine), extensions: intl, zip, apcu, opcache, pdo_mysql |
| Web | nginx (`latest`), fastcgi to `app:9000` |
| DB | MySQL (`latest`) |
| Cache | Redis (`latest`), via `symfony/cache`'s `RedisAdapter` + `predis/predis` |
| Composer | 2.10, multi-stage copy into the PHP image |
| Dev extras | Xdebug 3.5.3, Symfony CLI (only in the `app_dev` build target) |

PHP floor: `^8.4` in `app/composer.json`. Runtime packages:
`symfony/cache ^8.0`, `predis/predis ^3.0`. Dev packages:
`phpunit/phpunit ^13.0`, `nunomaduro/collision ^8.0`.

Images `rasimaghayev/php-nginx` and `rasimaghayev/php-composer` are this
repo's own images, built and pushed by `.github/workflows/deploy.yaml`
from `nginx/Dockerfile` and `php/Dockerfile` on every push to `main` —
not third-party images. Image tags use the commit SHA (`github.sha`).

## Architecture style

Single front controller (`app/public/index.php`) handles both `GET`
(render the language dropdown) and `POST` (look up a translation) in one
file — no router, no framework. Below it: a `Repository` base class +
two repositories (`LanguageRepository`, `TranslationRepository`) wrapping
a PDO singleton (`App\Database\Connection`), and one cache decorator
(`App\Cache\TranslationCache`) wrapping Symfony Cache's `RedisAdapter`
around `TranslationRepository`. That's the whole app — a flat
repository/cache layering, not DDD, CQRS, or hexagonal. See
`docs/ARCHITECTURE.md` for the full module map and the explicit KISS/YAGNI
decline.

## Running it

```sh
# 1. Build + start (dev overlay adds live-mount + Xdebug + builds locally
#    instead of pulling rasimaghayev/*; see bin/dev-mode.sh)
docker compose -f docker-compose.yaml -f docker-compose.dev.yaml --env-file=.env.local up -d

# 2. Install PHP deps into the bind-mounted app/ dir — REQUIRED,
#    dev mode fails without vendor/ (bind-mount shadows image vendor/)
docker run --rm -v "$(pwd)/app:/var/www/html" -w /var/www/html \
  rasimaghayev/php-composer composer install --no-dev

# 3. Seed the database — nothing loads sql/docker-php.sql automatically
docker exec -i <db-container> mysql -uuser -psecret docker-php < sql/docker-php.sql
```

After both steps, `http://localhost/` renders the language dropdown
(French/German/Spanish, from the seed data) and posting a phrase returns
a real cached translation.

## Security notes

- `phpinfo()` was removed (Phase 9) — it previously leaked `$_ENV`
  (incl. `MYSQL_PASSWORD`) to any visitor of `/`.
- POST `language` (int) and `phrase` (non-empty, max 255) are validated;
  all output is escaped with `htmlspecialchars`.
- `GET /health` returns `{"status":"ok"}` for health checks.
- `.env`/`.env.local` contain placeholder secrets only and are gitignored;
  never committed. `.env.example` has `<change-me>` placeholders.

## Tests

PHPUnit 13.4 (`app/phpunit.xml`, schema `https://schema.phpunit.de/13.4/phpunit.xsd`):

- `app/tests/LanguageTest.php` — `Language::TABLE` constant, `getId()`/`getName()`
  reflection, private visibility.
- `app/tests/TranslationCacheTest.php` — cache miss delegates to repository,
  cache hit skips repository, independent keys, null translation cached
  (Symfony `ArrayAdapter`, no Redis required).
- `app/tests/TranslationLookupTest.php` — MySQL-backed integration test
  against `TranslationRepository` (skips gracefully when no DB available).

Run with `composer run-phpunit` inside the app container.

## CI

`.github/workflows/deploy.yaml` (triggered on push/PR to `main`):

- `test` job: PHP 8.4, `composer install --no-interaction --no-progress`,
  `composer run-phpunit` (15 min timeout)
- `build` job (main only, needs `test`): `docker/login-action@v3`,
  `docker/build-push-action@v6` with GitHub Actions cache, tags
  `rasimaghayev/*:${{ github.sha }}`
- `permissions: contents: read` (top-level), least-privilege per job,
  `concurrency` group with `cancel-in-progress: true`

## Known limitations (documented, not fixed)

- Dev mode is broken out of the box: `docker-compose.dev.yaml` bind-mounts
  `./app:/var/www/html`, shadowing the image's `vendor/`. Run the manual
  `composer install` step above.
- Database is never auto-seeded: `sql/docker-php.sql` must be piped into
  the `db` container by hand.
- Base image tags float (`mysql:latest`, `redis:latest`, `nginx:latest`);
  real digests (`sha256:placeholder`) pending Docker availability on this host.
- No `composer.lock` tracked; builds resolve from `composer.json` at build time.
- No `LICENSE` file (MIT declared in `app/composer.json` only).
