# php-docker — container baseline (Phase 5)
Repo: RasimAghayev/php-docker
Branch: phase-5/container-baseline → main
Docker: unavailable on this host — files written but `make verify` / `make up` NOT yet executed.
Port block: 20140-20149 (reserved in projects/info/state/ports.md)

## Built images

| Image | Dockerfile | Target | Base | Stage |
|---|---|---|---|---|
| php-docker:app | php/Dockerfile | app | php:8.5.11-fpm-alpine | runtime (www-data, HEALTHCHECK, php-fpm entrypoint) |
| php-docker:web | nginx/Dockerfile | — | nginx:latest | serves /var/www/html/public, fastcgi to app:9000 |
| php-docker:test | php/Dockerfile | test | app + dev deps | runs `vendor/bin/phpunit --configuration phpunit.xml` |

## Multi-stage Dockerfile (php/Dockerfile)

`base` → `deps` → `build` → `test` → `runtime` → `app_dev`

- **base**: php:8.5.11-fpm-alpine (digest pinned, placeholder pending Docker), installs pdo/pdo_mysql/intl/zip/apcu/opcache + system packages.
- **deps**: composer:2.10 (digest pinned, placeholder), `composer install --prefer-dist --no-dev` with BuildKit cache mount on `/root/.composer/cache`.
- **build**: copies app/, `composer dump-autoload --optimize`.
- **test**: adds dev deps, runs `vendor/bin/phpunit --configuration phpunit.xml` — build fails if tests fail (behavior contract).
- **runtime**: slim, `USER www-data`, HEALTHCHECK (`php -r 'exit(0);'`), exec-form `ENTRYPOINT ["php-fpm"]`, no Composer/git/dev tools.
- **app_dev**: extends `app`, adds Xdebug + Symfony CLI.

## docker-compose.yaml

Services: web (20140:80), app, db (mysql:8.4), cache (redis:7.2).
- Healthchecks on all four services.
- `depends_on: condition: service_healthy` (web→app, app→db+cache).
- `COMPOSE_PROJECT_NAME=php-docker-p5` (Makefile).
- Ports from reserved block 20140-20149.
- Image digests pinned (placeholder pending Docker availability).

## Makefile

Targets: `build`, `test`, `verify` (= build + test), `up` (depends on `verify`), `down`, `run` (dev overlay), `shell`, `logs`, `migrate`/`seed` (loads sql/docker-php.sql), `lint`, `format`, `security`.

## .env.example

Placeholders only (`<change-me>` for MYSQL_PASSWORD); real secrets injected at runtime.

## .dockerignore

Excludes `.git`, `.env*`, `app/vendor/`, docs cache, logs.

## Baseline (not yet measured — Docker unavailable)

`docs/BASELINE.md` not yet written: `make verify` could not be executed (no Docker on this host). Once Docker is available:
1. `make build` → images build without error.
2. `make test` → `vendor/bin/phpunit` runs; record exit code + output.
3. `make up` → stack starts, healthchecks pass, `http://localhost:20140/` returns the language dropdown.
4. Record results in `docs/BASELINE.md` (test results, coverage, key API/CLI output snapshots).

## Characterization tests (Phase 5 allowance — only tests written before Phase 11)

No characterization tests added yet. Existing test: `tests/LanguageTest.php` (1 trivial test, `assertEquals(1,1)`). Phase 5/11 should add characterization tests for `TranslationRepository`, `TranslationCache`, and `Connection` against a real MySQL+Redis container.

## Pending (Docker unavailable)

- Real base-image digests (`sha256:placeholder` → actual digests).
- `make verify` execution + `docs/BASELINE.md`.
- `make up` stack start + healthcheck verification.
- `docker scout`/`trivy` image scan.
