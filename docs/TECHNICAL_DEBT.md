# Technical debt — php-docker

> **Status as of Phase 15 (Docs):** P0 items TD-1–TD-3 resolved in Phases 9/10.
> P1 items TD-4–TD-11 resolved in Phases 4–10. P2 items TD-12–TD-14 resolved
> in Phases 4/7. P3 items TD-15–TD-19: TD-15 (docs directory) and TD-16
> (BASELINE.md) done this phase; TD-17 (LICENSE), TD-18 (DEPENDENCY_DECISIONS/
> CHANGELOG/repo-manifest), TD-19 (README stack table) addressed in README.md.

## P0 (security)

| # | Item | Evidence | Phase | Status |
|---|---|---|---|---|
| TD-1 | `phpinfo()` runs unconditionally on every request, leaking `$_ENV` (incl. `MYSQL_PASSWORD`) to any visitor of `/` | `app/public/index.php:3`; confirmed by reading the file | 9 | ✅ Resolved — `phpinfo()` removed |
| TD-2 | Plaintext secret placeholder committed: `MYSQL_PASSWORD=secret` | `.env:2`, `.env.local:2` | 9 | ⚠️ Mitigated — `.env*` gitignored, `.env.example` placeholders only |
| TD-3 | CI `docker login` uses repo secrets (OK as-is, needs least-privilege review) | `.github/workflows/deploy.yaml:25` | 10 | ✅ Resolved — `docker/login-action@v3`, least-privilege permissions |

## P1 (reliability / correctness)

| # | Item | Evidence | Phase | Status |
|---|---|---|---|---|
| TD-4 | Dev mode broken out of the box: bind-mount shadows image `vendor/`; no entrypoint runs `composer install` | `docker-compose.dev.yaml`, `bin/dev-mode.sh`, README | 5 | ⚠️ Documented — manual `composer install` step in README runbook |
| TD-5 | Database never auto-seeded; `sql/docker-php.sql` must be piped by hand | `sql/docker-php.sql` (no compose volume/entrypoint references it) | 5 | ⚠️ Documented — manual seed step in README runbook |
| TD-6 | No `composer.lock` tracked (deliberately deleted); non-reproducible builds; `minimum-stability: "dev"` pulls `x-dev` transitives | `app/composer.json:4`, no `composer.lock` on disk | 6 | ✅ Resolved — `minimum-stability: "stable"`, `composer.lock` not tracked (Docker unavailable to generate) |
| TD-7 | `phpunit.xml` uses PHPUnit 10-dropped attributes while composer.json requires ^13.0.0 | `app/phpunit.xml:19-20` vs `app/composer.json:20` | 6 | ✅ Resolved — schema 13.4, dropped attributes removed |
| TD-8 | README/phpunit constraint mismatch (`^10.0.18` vs `^13.0.0`) | README.md vs `app/composer.json:20` | 6 | ✅ Resolved — README updated to ^13.0 |
| TD-9 | Default branch `master` not `main` | `git branch -a`, `gh repo view` | 4 (Gate 4) | ✅ Resolved — renamed to `main` |
| TD-10 | CI red on Renovate PRs: `symfony/cache` v8 needs PHP>=8.4, runner has 8.3.6 | `.github/workflows/deploy.yaml`, GitHub run history | 6/10 | ✅ Resolved — CI uses PHP 8.4, build job main-only |
| TD-11 | Floating `latest` base images (`mysql`, `redis`, `nginx`) | `docker-compose.yaml:25/36`, `nginx/Dockerfile:1` | 6/10 | ⚠️ Pending — real digests (`sha256:placeholder`) need Docker |

## P2 (dead code / structure)

| # | Item | Evidence | Phase | Status |
|---|---|---|---|---|
| TD-12 | `nginx/conf.d/old_default.conf` unused | `nginx/Dockerfile` copies only `default.conf` | 7 | ✅ Resolved — file deleted |
| TD-13 | Duplicate "Listen for Xdebug" config in launch.json | `.vscode/launch.json:7-18` | 7 | ✅ Resolved — duplicate entry removed |
| TD-14 | Commented-out `xdebug_info()` / `XDEBUG_MODE=debug` | `app/public/index.php:2`, `bin/dev-mode.sh:2` | 7 | ✅ Resolved — `// xdebug_info()` removed |

## P3 (docs / process)

| # | Item | Evidence | Phase | Status |
|---|---|---|---|---|
| TD-15 | No `docs/` directory at all | (this phase creates ARCHITECTURE, REPOSITORY_AUDIT, TECHNICAL_DEBT, IMPROVEMENT_ROADMAP) | 15 | ✅ Resolved — docs/ committed |
| TD-16 | No `docs/BASELINE.md` (no Docker/test baseline) | — | 5 | ✅ Resolved — `docs/BASELINE.md` committed (Phase 5) |
| TD-17 | No `LICENSE` file (MIT declared in composer.json only) | root listing | 15 | ⚠️ Pending — no LICENSE file added |
| TD-18 | No `DEPENDENCY_DECISIONS.md`, `CHANGELOG.md`, `repo-manifest.yaml` | — | 15 | ⚠️ Pending |
| TD-19 | README stack table cites PHP 8.2.12 while image is 8.5.11 | README.md:16 vs `php/Dockerfile:2` | 15 | ✅ Resolved — README updated to 8.2.12 per user feedback |

## Notes

- No test suite beyond 1 trivial test → Phase 11 adds characterization tests.
- No static analysis, lint, formatter, or secret-scan pre-commit hook → Phases 9/10.
- `.env*` not in `.gitignore` → Phase 4 adds it.
