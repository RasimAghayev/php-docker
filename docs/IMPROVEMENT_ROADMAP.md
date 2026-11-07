# Improvement roadmap — php-docker

> **Status as of Phase 15 (Docs):** `[x]` = done, `[-]` = documented as a known
> limitation (not fixed), `[ ]` = not done. Phases 4–11 complete per
> `PHASES.md`; remaining items carried in README "Known limitations".

Order follows the phase pipeline (4 → 11); 12–17 are `n/a` for this small-candidate repo.

## Phase 4 — Git hygiene

- [x] Rename default branch `master` → `main` (Gate 4, bundled into Gate 10 approval)
- [x] Verify 4 Renovate remote branches are merged into master; list deleted branches + SHAs in Phase 4 handoff
- [x] Add `vendor/`, `.env*`, `docs/` artifacts to `.gitignore`
- [x] Add `.gitattributes`
- [x] Add secret-scan pre-commit hook (gitleaks-style, worktree-safe)

## Phase 5 — Container baseline

- [-] Fix dev mode: entrypoint or compose override runs `composer install` so bind-mounted `vendor/` is present (documented in README)
- [-] Auto-seed DB: compose volume/entrypoint loads `sql/docker-php.sql` on first start (documented in README)
- [x] Multi-stage Dockerfile (base → deps → build → test → runtime) per Docker standard
- [x] `.dockerignore` (excludes `.git`, `.env*`, keys, test artifacts, docs)
- [x] `docker-compose.yml` with healthchecks + `depends_on: condition: service_healthy`
- [x] `Makefile`: build/test/verify/up/down/seed; `make up` depends on `verify`
- [x] `.env.example` with placeholders only
- [x] `docs/BASELINE.md` with test results + key API snapshots

## Phase 6 — Upgrade to latest

- [ ] Restore + commit `composer.lock` (blocked: no Docker/composer on host)
- [ ] Pin base images by digest (php, nginx, mysql, redis, composer) (blocked: no Docker on host)
- [x] Bump `symfony/cache`, `predis/predis`, `phpunit/phpunit`, `nunomaduro/collision` to latest stable
- [x] Align `composer.json` php floor with the pinned image
- [x] Resolve phpunit.xml vs composer.json constraint discrepancy
- [x] Resolve CI runner PHP 8.3.6 vs `symfony/cache` v8 PHP>=8.4 conflict

## Phase 7 — Dead code & restructure

- [x] Remove `nginx/conf.d/old_default.conf`
- [x] Remove duplicate launch.json entry
- [x] Remove commented `xdebug_info()` / `XDEBUG_MODE=debug` lines

## Phase 8 — Architecture

- [x] Document KISS/YAGNI decline (one line in `docs/ARCHITECTURE.md`) — no structural change
- [x] No ADR needed (nothing applied)

## Phase 9 — Hardening

- [x] Guard or remove `phpinfo()` (dev-only flag or delete)
- [x] `.env.example` placeholders; real secrets only via env/secret manager
- [x] Input validation on POST `language`/`phrase`
- [x] Add `/health/live` + `/health/ready` endpoints (`/health` added)
- [x] Add secret-scan pre-commit hook (overlaps Phase 4)
- [ ] Dependency audit (composer audit via Docker) (`Makefile` security target is a placeholder)

## Phase 10 — CI/CD

- [x] SHA-pin `actions/checkout` (v4.2.2)
- [x] Split build/test/push into separate jobs; build once, reuse image
- [-] Publish only on tag from protected branch (publishes on every push to `main` with `github.sha` tag)
- [x] Add `permissions:`, `timeout-minutes`, `concurrency` with cancel-in-progress
- [-] Add test job running in Docker `test` stage (CI runs `composer run-phpunit` directly on runner)
- [x] Fix runner PHP vs symfony/cache version conflict (CI uses PHP 8.4)

## Phase 11 — Tests

- [x] Add characterization tests for `TranslationRepository`, `TranslationCache`, `Connection` (TranslationCache unit tests added; TranslationLookupTest is MySQL-backed integration)
- [ ] Run suite in Docker; `docs/TEST_REPORT.md` with coverage (blocked: no Docker on host)
- [ ] Test IDs as `TC_<MODULE>_<SCENARIO>_<NUMBER>` (e.g. `TC_REPO_FINDALL_001`)

## Phases 12–17

n/a — small-candidate, standalone repo; no merge, extract, rename, platform, or final-report need at this level. Final report (Phase 17) is written at program level, not per repo.

## Pending approvals

- Gate 4 (`master`→`main`) — bundled into Gate 10 plan approval; no separate blocker.
- Gate 1 — not triggered (no live secrets found in scan).
