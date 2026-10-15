# Barangay Management System

Barangay administration system: residents, certificates and clearances, the blotter, finance, and SMS for Philippine barangays. One central server hosts many barangays; each barangay is an isolated tenant.

Stack: Laravel 13, PHP 8.4+, PostgreSQL 18, Inertia 3 with Vue 3 and TypeScript, Tailwind 4.

## Setup

1. Install PHP 8.4+ with `pdo_pgsql`, `intl`, `gd`, `bcmath`, `sodium`; Composer 2; Node 22+; PostgreSQL 16+; `qpdf` (needed to print fillable-PDF form templates; set `BMS_PDF_QPDF_BINARY`).
2. Create the role and databases:
    ```sh
    createuser -P bms
    createdb -O bms bms
    createdb -O bms bms_test
    ```
3. Copy `.env.example` to `.env` and set `DB_PASSWORD`.
4. Run `composer setup`. It installs dependencies, migrates, seeds roles and lookup values, and builds assets.
5. Create the first central administrator: `php artisan bms:central-admin:create`.
6. Import the PSGC geography (CSV with `psgc_code,name,level`): `php artisan bms:geography:import path/to/psgc.csv`.

In the `local` environment, `php artisan db:seed` also creates a sample barangay and three accounts (`central`, `captain`, `secretary`) and prints their generated passwords.

## DDEV

Requires Docker and [DDEV](https://ddev.com) 1.25 or newer.

```sh
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
ddev artisan migrate --seed
ddev npm install
ddev npm run build
ddev restart
```

Open `https://bms.ddev.site`. The `.ddev/config.yaml` environment overrides the database settings in `.env`, so the same `.env` works inside and outside DDEV.

| Service  | Details                                                                     |
| -------- | --------------------------------------------------------------------------- |
| Web      | PHP 8.4, nginx, `qpdf` installed, queue worker and scheduler run as daemons |
| Database | PostgreSQL 18 (`db`/`db`); tests use `bms_test`, created on start           |
| Vite     | `ddev npm run dev`, served on port 5173                                     |

Run checks with `ddev composer test`. The first `ddev restart` after migrating starts the queue worker, which exits while its tables are missing.

## Run

```sh
composer dev
```

Open `http://localhost:8000` and sign in with a username.

## Test

```sh
composer test        # Pint, PHPStan, contract check, PHPUnit
npm run check        # frontend format and lint
npm run types:check  # vue-tsc
```

Tests use the `bms_test` PostgreSQL database.

## Deploy

1. `composer install --no-dev --optimize-autoloader`
2. `php artisan migrate --force`
3. `php artisan bms:roles:sync`
4. `npm ci && npm run build`
5. `php artisan config:cache && php artisan route:cache`
6. Install `qpdf` and set `BMS_PDF_QPDF_BINARY` (for example `/usr/bin/qpdf`); without it, PDF form printing answers 503.
7. Run a queue worker (`php artisan queue:work`) and the scheduler (`php artisan schedule:work` or cron). SMS needs both: the worker sends messages, the scheduler requeues stuck ones and prunes old ones.
8. Set `BMS_SMS_DRIVER` (`log` by default, nothing leaves the server; `http` with `BMS_SMS_HTTP_URL` and `BMS_SMS_HTTP_TOKEN` for a gateway; `null` to refuse every message).

Health: `GET /up` (liveness) and `GET /health/ready` (database and cache).

## Operations

| Task                                                | Command                                                                                                                |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| List feature flags and why each is on or off        | `php artisan bms:features:list [--barangay=PSGC]`                                                                      |
| Turn a flag on, off, or back to default             | `php artisan bms:features:set <flag> on\|off\|default [--barangay=PSGC] [--reason=...]`                                |
| Emergency kill switch without touching the database | `BMS_FEATURES_FORCED_OFF=sms,biometric-capture` then `php artisan config:cache`                                        |
| Re-apply built-in role permissions after an upgrade | `php artisan bms:roles:sync`                                                                                           |
| Regenerate frontend contract constants              | `php artisan bms:contracts:generate`                                                                                   |
| Send queued text messages (must always run)         | `php artisan queue:work`                                                                                               |
| Requeue text messages stuck in "sending"            | `php artisan bms:sms:dispatch-stuck` (scheduled every 5 minutes)                                                       |
| Erase text and numbers of old finished messages     | `php artisan bms:sms:prune` (scheduled daily; needs `BMS_SMS_RETENTION_DAYS` above 0)                                  |
| Pick up a changed SMS driver or gateway token       | `php artisan config:cache && php artisan queue:restart`                                                                |
| Import a data bundle (dry run first)                | `php artisan bms:import:bundle <path> --barangay=PSGC --dry-run`; see [docs/modules/import.md](docs/modules/import.md) |

See [docs/README.md](docs/README.md) for how the system works and [AGENTS.md](AGENTS.md) for working rules.
