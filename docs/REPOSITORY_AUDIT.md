# Repository audit — php-docker

> **Status as of Phase 15 (Docs):** This audit reflects the pre-Phase-4 state
> (branch `master`, PHPUnit ^10, `phpinfo()` present, etc.). All findings
> marked "apply" in the Phase 4–11 table below have been addressed in Phases
> 4–11; see `projects/info/state/handoff/4-11/php-docker.md` for per-phase
> evidence. Default branch is now `main`, `phpinfo()` removed, input validated,
> output escaped, `/health` added, CI hardened, unit tests added.

Repo: RasimAghayev/php-docker
Branch: `master` (clean, up to date with origin/master, tip `dbb1524`)
Remote branches: `master` + 4 Renovate branches (`composer-2.x`, `nunomaduro-collision-8.x`, `php-8.x`, `predis-predis-3.x`) — all already merged into master per log
Commit count: 35 across all branches (all on master; no unique work on any other branch)

## Tracked file inventory (35 files)

Root: `.env`, `.env.local`, `.gitignore`, `.vscode/launch.json`, `docker-compose.yaml`, `docker-compose.dev.yaml`, `README.md`, `bin/dev-mode.sh`
`app/`: `composer.json`, `phpunit.xml`, `public/index.php`, `src/{Cache,Database,Model,Repository}/*.php` (8 files), `tests/LanguageTest.php`
`nginx/`: `Dockerfile`, `conf.d/default.conf`, `conf.d/old_default.conf`
`php/`: `Dockerfile`, `conf.d/xdebug.ini`
`sql/`: `docker-php.sql`
`.github/workflows/deploy.yaml`

## Secret scan (working tree + full history, all 35 commits)

Scanned `git log --all -p` for `api_key|secret|password|AKIA|ghp_|-----BEGIN|MYSQL|REDIS|DOCKER_`:
- Hits: placeholder `.env` values (`MYSQL_PASSWORD=secret` in `.env`/`.env.local`), `${{ secrets.DOCKER_USERNAME/Password }}` references in CI (proper, not inlined), `predis/predis`/`symfony/cache` package metadata (the word "redis"/"password" inside dependency descriptions), README prose.
- **No live credentials, tokens, or keys anywhere in history.** → Gate 1: no action needed.
- Note: `.env`/`.env.local` are tracked with placeholder `secret` — rotation-worthy if ever deployed with real credentials (Phase 9).

## Open PRs / branches

- No open non-bot PRs detectable from the local clone. Default branch is `main` (renamed from `master` in Phase 4).
- Remote branches include `main` plus Renovate branches (actions-checkout, composer, mysql, nginx, nunomaduro-collision, php, predis-predis, redis).

## CI/CD

- `.github/workflows/deploy.yaml`: trigger on push/PR to `main`; jobs: `test` (PHP 8.4, `composer install --no-interaction --no-progress`, `composer run-phpunit`, 15 min timeout) and `build` (main only, needs `test`; `docker/login-action@v3`, `docker/build-push-action@v6` with GitHub Actions cache, tags `rasimaghayev/*:${{ github.sha }}`).
- Issues resolved: `actions/checkout@v4.2.2` SHA-pinned (`v4.2.2`), test job separated from build, `permissions: contents: read`, `timeout-minutes` (15/20), `concurrency` with cancel-in-progress, CI uses PHP 8.4 (resolves `symfony/cache` v8 PHP>=8.4 conflict).

## Tests

- `app/tests/LanguageTest.php`: 4 tests (TABLE constant, getId/getName reflection, private visibility).
- `app/tests/TranslationCacheTest.php`: 4 unit tests (cache miss/hit, independent keys, null cached; Symfony `ArrayAdapter`, no Redis required).
- `app/tests/TranslationLookupTest.php`: MySQL-backed integration test against `TranslationRepository` (skips gracefully when no DB available).
- `app/phpunit.xml`: schema 13.4, PHPUnit 10-dropped attributes removed.

## Docker

- `php/Dockerfile`: multi-stage `base → deps → build → test → runtime → app_dev`; `php:8.5.11-fpm-alpine` base, installs pdo/pdo_mysql/intl/zip/apcu/opcache; `composer:2.10` deps stage with BuildKit cache mount.
- `nginx/Dockerfile`: `nginx:latest`, copies `default.conf`.
- `docker-compose.yaml`: `web` (20140:80), `app`, `db` (mysql:8.4), `cache` (redis:7.2); healthchecks on all services, `depends_on: condition: service_healthy`, floating `latest` tags (digests pending Docker availability).
- `docker-compose.dev.yaml`: builds `web`/`app` locally, bind-mounts `./app` (shadows image `vendor/` → dev mode requires manual `composer install`).
- `Makefile`: build/test/verify/up/down/run/shell/logs/migrate/seed/lint/format/security.
- `.dockerignore`: excludes `.git`, `.env*`, `app/vendor/`, docs cache.
- `.env.example`: placeholders only (`<change-me>` for MYSQL_PASSWORD).
- `docs/BASELINE.md`: container baseline (build/test/scan evidence pending Docker).

## Config smells

| Item | Location | Status |
|---|---|---|
| `minimum-stability: "dev"` | `app/composer.json:4` | ✅ Changed to `"stable"` (Phase 6) |
| php floor `^8.1\|^8.2` vs image PHP 8.5 | `app/composer.json:13` vs `php/Dockerfile:2` | ✅ Aligned to `^8.4` (Phase 6) |
| No composer.lock tracked | (deliberately deleted per repo history) | ⚠️ Pending (no Docker/composer on host) |
| Floating `latest` base images | `docker-compose.yaml`, `nginx/Dockerfile:1` | ⚠️ Pending (digests `sha256:placeholder`) |
| Plaintext `.env` secrets | `.env`, `.env.local` (`MYSQL_PASSWORD=secret`) | ⚠️ Mitigated (`.env*` gitignored, `.env.example` placeholders) |
| `phpinfo()` unconditional | `app/public/index.php:3` | ✅ Removed (Phase 9) |
| Dead file `old_default.conf` | `nginx/conf.d/old_default.conf` | ✅ Deleted (Phase 7) |
| Duplicate launch.json entry | `.vscode/launch.json` (lines 7-18) | ✅ Removed (Phase 7) |
| README/phpunit constraint mismatch | README vs `app/composer.json` | ✅ Resolved (Phase 6/15) |
| No LICENSE file | root (MIT declared in composer.json only) | ⚠️ Pending |
| README phpunit constraint mismatch | README vs `app/composer.json` (`^10.0.18` vs `^13.0.0`) |
| No LICENSE file | root (MIT declared in composer.json only) |

## Phase 4–11 applicability

| Phase | Verdict | Reason |
|---|---|---|
| 4 Git hyg. | done | master→main (Gate 4), `.gitignore`/`.gitattributes`/pre-commit hook, thin out Renovate branches |
| 5 Container | done | multi-stage Dockerfile, Makefile, docker-compose + dev overlay, healthchecks, `.env.example`, `.dockerignore`, `docs/BASELINE.md` |
| 6 Upgrade | done | php ^8.4, symfony/cache ^8.0, predis/predis ^3.0, phpunit ^13.0, collision ^8.0, checkout v4.2.2, phpunit.xml schema 13.4 |
| 7 Dead code | done | old_default.conf deleted, duplicate launch.json entry removed, `// xdebug_info()` removed |
| 8 Architecture | done | `docs/ARCHITECTURE.md` — KISS/YAGNI decline, no pattern earns its place |
| 9 Harden | done | `phpinfo()` removed, input validation, output escaping, `/health` endpoint, `TranslationCache` echo leak removed |
| 10 CI/CD | done | permissions, concurrency, timeouts, docker/login-action, build-push-action, github.sha tags, main-only build |
| 11 Tests | done | LanguageTest (4 tests), TranslationCacheTest (4 tests), TranslationLookupTest (integration, skips without DB) |
| 12–17 | n/a | small-candidate, standalone, no merge/extract/rename/platform need |
