# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Symfony 8.0 skeleton on FrankenPHP 1 / Caddy, PHP ≥ 8.5.5 (enforced in `composer.json`). Doctrine ORM 3 + Migrations over PostgreSQL 16. Mercure (bundled as a Caddy module, exposed at `/.well-known/mercure`) and Vulcain for real-time and preloading. Symfony UX Turbo + Stimulus on the frontend, served by Asset Mapper + `importmap.php` — there is **no webpack/npm build step for app assets**. Mailpit runs as the `mailer` service in dev. PHPUnit 13 is configured in `phpunit.dist.xml` with `failOnDeprecation`, `failOnNotice`, and `failOnWarning` all `true` — new code must be clean on all three.

The repo is a fresh scaffold: `src/Controller`, `src/Entity`, `src/Repository`, `migrations/`, and `tests/` are empty aside from `tests/bootstrap.php` and `src/Kernel.php`.

## Development workflow

Everything runs through Docker Compose (`compose.yaml` + `compose.override.yaml`). Service names: `php`, `database`, `mailer`.

```sh
docker compose up -d              # start (builds on first run)
docker compose build --pull       # rebuild images
docker compose logs -f            # tail logs
docker compose exec php bash      # shell into FrankenPHP container
docker compose down --remove-orphans
```

App entry: `https://localhost` (self-signed — Caddyfile sets `skip_install_trust`). Mailpit UI is on port `8025` of the `mailer` service.

### Running code inside the container

```sh
docker compose exec php bin/console <cmd>                             # Symfony CLI
docker compose exec php composer <cmd>                                # Composer (provided via install-php-extensions @composer)
docker compose exec -e APP_ENV=test php bin/phpunit                   # full test suite
docker compose exec -e APP_ENV=test php bin/phpunit tests/Path/FooTest.php
docker compose exec -e APP_ENV=test php bin/phpunit --filter=methodName
docker compose exec -e APP_ENV=test php bin/phpunit --group=<name>
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console make:migration
docker compose exec -T php bin/console -e test doctrine:database:create
```

`phpunit.dist.xml` already forces `APP_ENV=test`, but the CI examples also pass `-e APP_ENV=test` explicitly — keep both habits.

There is **no Makefile** at the repo root (only a template in `docs/makefile.md`). All commands are raw `docker compose` invocations.

## Architecture

- **MicroKernel.** `src/Kernel.php` uses `MicroKernelTrait`. Bundles come from `config/bundles.php`, routes from `config/routes.yaml` and `config/routes/`, service wiring from `config/services.yaml`. No `AppBundle`.
- **PSR-4.** `App\\` → `src/`, `App\Tests\\` → `tests/` (`composer.json`). Follow the existing folder split: HTTP → `src/Controller`, entities → `src/Entity`, repositories → `src/Repository`.
- **Frontend.** Stimulus controllers live under `assets/controllers/` and are registered via `assets/controllers.json` + `stimulus_bootstrap.js`. Turbo ships via `symfony/ux-turbo`. Assets are served by Asset Mapper; entries are declared in `importmap.php`.
- **Worker mode.** FrankenPHP runs PHP as a long-lived worker (`worker` block in `frankenphp/Caddyfile`). The dev container sets `FRANKENPHP_WORKER_CONFIG=watch` and `FRANKENPHP_SITE_CONFIG=hot_reload`, so source edits are picked up without a restart.
- **Mercure.** JWT publisher/subscriber keys come from env vars; the dev defaults in `compose.yaml` are insecure placeholders (`!ChangeThisMercureHubJWTSecretKey!`) and must be overridden for any non-local use.
- **Prod build.** `Dockerfile` is multi-stage: `frankenphp_dev` (xdebug, watch) and `frankenphp_prod` (classmap-authoritative autoload, `composer dump-env prod`, setuid bits stripped, runs as `www-data`). `compose.prod.yaml` targets the prod stage.

## CI

`.github/workflows/ci.yaml` has two jobs:

- **tests** — builds the compose stack via `docker/bake-action`, brings it up with `--wait`, and curls HTTP + Mercure for reachability. Steps for `doctrine:database:create`, `doctrine:migrations:migrate`, `bin/phpunit`, and `doctrine:schema:validate` are **present but commented out**. Uncomment them once entities and tests exist.
- **lint** — runs `super-linter/super-linter/slim@v8` with Biome, Checkov, and Trivy disabled. Linter configs: `.github/linters/actionlint.yaml`, `.github/linters/zizmor.yaml`.

## Conventions

- `.editorconfig` is authoritative for indentation — notably, Makefiles must use tabs (the rest of the tree uses spaces).
- PHPUnit is strict (`failOnDeprecation/Notice/Warning`); do not leave deprecation noise in new tests or library code exercised by tests.
- Do not commit the dev Mercure/APP_SECRET placeholders into production overrides — they are intentionally insecure.
