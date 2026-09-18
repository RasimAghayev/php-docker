# php-docker

A small "Translate This" demo: pick a language, enter a phrase, get a
translation back from a MySQL-backed lookup table, cached in Redis. Built
as a personal Docker Compose exercise (nginx + PHP-FPM + MySQL + Redis,
multi-stage Dockerfile, dev/prod compose overlay) rather than a real
product — verified end-to-end below, including two things that don't
work out of the box.

## Stack

| Component | Version (as built) |
|---|---|
| PHP | 8.2.12-fpm (Alpine), extensions: intl, zip, apcu, opcache, pdo_mysql |
| Web | nginx (`latest`), fastcgi to `app:9000` |
| DB | MySQL (`latest`) |
| Cache | Redis (`latest`), via `symfony/cache`'s `RedisAdapter` + `predis/predis` |
| Composer | 2.6, multi-stage copy into the PHP image |
| Dev extras | Xdebug 3.5.3, Symfony CLI (only in the `app_dev` build target) |

Images `rasimaghayev/php-nginx:1.0` and `rasimaghayev/php-composer:1.2`
referenced in `docker-compose.yaml` are this repo's own images, built and
pushed by `.github/workflows/deploy.yaml` from `nginx/Dockerfile` and
`php/Dockerfile` on every push to `master` — not third-party images.

## Architecture style

Single front controller (`app/public/index.php`) handles both `GET`
(render the language dropdown) and `POST` (look up a translation) in one
file — no router, no framework. Below it: a `Repository` base class + two
repositories (`LanguageRepository`, `TranslationRepository`) wrapping a
PDO singleton (`App\Database\Connection`), and one cache decorator
(`App\Cache\TranslationCache`) wrapping Symfony Cache's `RedisAdapter`
around `TranslationRepository`. That's the whole app — a flat
repository/cache layering, not DDD, CQRS, or hexagonal.

## Running it (verified steps — the two after `up` are not automatic)

```sh
# 1. Build + start (dev overlay adds live-mount + Xdebug + builds locally
#    instead of pulling rasimaghayev/*; see bin/dev-mode.sh)
docker compose -f docker-compose.yaml -f docker-compose.dev.yaml --env-file=.env.local up -d

# 2. Install PHP deps into the bind-mounted app/ dir — REQUIRED, see
#    "Dev mode is broken out of the box" below
docker run --rm -v "$(pwd)/app:/var/www/html" -w /var/www/html \
  rasimaghayev/php-composer:1.2 composer install --no-dev

# 3. Seed the database — nothing loads sql/docker-php.sql automatically
docker exec -i <db-container> mysql -uuser -psecret docker-php < sql/docker-php.sql
```

After both steps, `http://localhost/` renders the language dropdown
(French/German/Spanish, from the seed data) and posting a phrase returns
a real cached translation. Verified directly: GET returns the dropdown
built from live DB rows; POST `language=1&phrase=hello` returns
`bonjour`, matching `sql/docker-php.sql`'s seed row.

## Findings (verified against real source/behavior, nothing here is guessed)

- **Dev mode is broken out of the box.** `docker-compose.dev.yaml`'s
  `app` service bind-mounts `./app:/var/www/html`, which shadows the
  `vendor/` that `php/Dockerfile` installs *inside the image* at build
  time. Running `bin/dev-mode.sh` (or the compose command above) without
  the manual `composer install` step in the runbook above produces a real
  `Fatal error: require_once(...vendor/autoload.php): Failed to open
  stream` on every request — reproduced directly, not inferred. Nothing
  in the repo (no entrypoint, no README before this one) documents the
  missing step.
- **`phpinfo()` runs unconditionally on every request**
  (`app/public/index.php` line 3, uncommented, outside any dev-only
  guard) and renders *before* the app's own output. Confirmed by a real
  request: the response includes `$_ENV['MYSQL_PASSWORD']` in plain text,
  along with the rest of the container's environment. In this repo the
  value is only the local placeholder (`secret`, from `.env`/`.env.local`
  — no external service behind it, same "local-Docker-only" shape as
  other repos in this portfolio, so this did not need a live-credential
  escalation) — but the *pattern* is a real vulnerability class: anyone
  who deploys this structure with real credentials in the environment
  leaks them to any unauthenticated visitor of `/`.
- **The database is never auto-seeded.** `sql/docker-php.sql` exists but
  no compose service, volume, or entrypoint loads it — it must be piped
  into the `db` container by hand (see runbook above).
- **Composer resolves many transitive deps to unpinned dev branches, not
  tagged releases.** `composer.json` sets `"minimum-stability": "dev"`;
  a real `composer install` (no lock file is tracked — deliberately, per
  the repo's own `composer lock delete` commit) pulled in
  `symfony/deprecation-contracts` (`dev-main`), `phpunit/phpunit`
  (`10.5.x-dev`), `symfony/string` (`7.4.x-dev`), `symfony/console`
  (`6.4.x-dev`), `nunomaduro/collision` (`v7.x-dev`), `nikic/php-parser`
  (`dev-master`), `phar-io/manifest` (`dev-master`), `myclabs/deep-copy`
  (`1.x-dev`), and several `sebastian/*` `x-dev` packages — a real
  reproducibility risk: the same constraint can resolve to a different
  actual commit on a different day. `composer audit` against the
  resulting lock file reported no known vulnerability advisories.
- **`phpunit.xml` targets an older PHPUnit schema than the version
  actually required.** It uses attributes PHPUnit 10 dropped
  (`convertDeprecationsToExceptions`, `beStrictAboutTodoAnnotatedTests`),
  while `composer.json` requires `phpunit/phpunit ^10.0.18`. Direct
  evidence: `.github/workflows/deploy.yaml` itself runs
  `vendor/bin/phpunit --migrate-configuration` before testing — CI only
  works because it silently rewrites the committed config on every run,
  not because the committed file is valid as-is. (A local run to
  independently reproduce the resulting warning was attempted but not
  completed this session — the Docker host this was verified on is
  shared with many other concurrent projects and the install stalled
  under resource contention; disclosed as unverified-by-direct-run
  rather than dropped, not treated as passing.)
- **CI is currently red on every open PR.** All 7 open PRs are
  Renovate-only (no human-authored PRs). Checked via real GitHub Actions
  run logs (not just status badges): the `symfony/cache` v8 bump fails
  its own `composer install` step because `symfony/cache` v8 requires PHP
  >=8.4 while the GitHub-hosted runner currently provides PHP 8.3.6 — a
  real, currently-unresolvable blocker for that branch, not a transient
  flake.
- **Same misleading-push-date pattern found across this portfolio.**
  `master`'s real last commit is 2023-11-03; GitHub reports
  `pushedAt: 2026-09-08`, which is the unmerged
  `renovate/symfony-cache-8.x` branch's own last-commit timestamp
  (04:41:21 vs. GitHub's 04:41:37 — 16s apart). Checked all 7 remote
  branches individually with `git merge-base --is-ancestor`; none are
  merged into `master`.
- **Minor dead file:** `nginx/conf.d/old_default.conf` is unused —
  `nginx/Dockerfile` only copies `default.conf`.
- No `LICENSE` file. Base image tags (`mysql:latest`, `redis:latest`,
  `nginx:latest` in the prod `docker-compose.yaml`) float rather than
  pin to a version.

## Scope of this pass

Documentation only — the findings above are disclosed, not fixed, same
treatment as the rest of this portfolio's README pass. No application or
Docker config file was modified; `vendor/`, the regenerated
`composer.lock`, and all containers/volumes/images created for
verification were removed afterward.
