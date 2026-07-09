# WPFluent Dev Toolkit

CLI tools, static analysis, testing, and code quality for WPFluent plugin development.

Powered by [wpfluent/devtools](https://github.com/wpfluent/devtools) Composer package.

## Quick Start

```bash
# From your plugin root directory:

# List all available commands
./wpf

# Run the full validation pipeline
./wpf check

# Review code for issues
./wpf review
```

## CLI Commands

Run `./wpf` to see all available commands. Key ones:

```bash
# Code generation
./wpf make:controller UserController
./wpf make:model User
./wpf make:migration create_users_table
./wpf make:test Feature/OrderFlowTest

# Database
./wpf migrate
./wpf migrate:refresh --seed
./wpf db:show

# Code quality
./wpf check              # Full pipeline (7 stages)
./wpf check --staged     # Only staged files
./wpf check --skip-tests # Skip PHPUnit
./wpf review             # Review against framework rules
./wpf review --staged    # Only staged files

# Testing
./wpf test               # Run PHPUnit tests (MySQL)
./wpf test --sqlite      # Run tests (SQLite, faster)
./wpf test --testsuite=unit  # Run only unit tests
./wpf coverage           # Check for missing test files

# E2E browser testing
./wpf make:e2e           # Scaffold Playwright + system Chrome
./wpf make:e2e Checkout  # Create a new E2E test file
./wpf test:e2e           # Run E2E tests
./wpf test:e2e --headed  # Run with visible browser
./wpf test:e2e --debug   # Step-through debugger

# CI/CD
./wpf ci:init            # Set up GitHub Actions workflow

# Vendor isolation
./wpf scope              # Requires PHP 8.1+

# Plugin management
./wpf app:about
./wpf app:status
```

## Validation Pipeline

`./wpf check` runs 7 stages in sequence:

1. **Syntax** — All PHP files parse correctly
2. **Boot** — Plugin structure is valid (config, entry point, routes)
3. **Review** — Framework rules + security patterns (review-rules.json)
4. **PHPStan** — Static analysis (L5) with 10 custom security rules
5. **Plugin Check** — WordPress.org readiness via WP-CLI
6. **Tests** — PHPUnit test suite
7. **Coverage** — Every testable class in `app/` has a matching test file

## Testing

### Unit / Integration / Feature tests (PHPUnit)

```bash
# Run all tests
./wpf test

# Run with SQLite (no MySQL needed)
./wpf test --sqlite

# Run specific testsuite
./wpf test --testsuite=unit
./wpf test --testsuite=feature

# Run specific test
./wpf test --filter=PostControllerTest
```

Tests live in `dev/test/tests/` organized by type:

```
dev/test/tests/
├── Unit/           # Pure logic — no DB, no HTTP
├── Integration/    # DB-dependent — services, handlers
└── Feature/        # Full HTTP cycle — controllers, REST
```

### E2E browser tests (Playwright)

Uses system Chrome — zero app size increase, no browser download.

```bash
# First-time setup (scaffolds config + sample test, installs @playwright/test)
./wpf make:e2e

# Run all E2E tests
./wpf test:e2e

# Run with visible browser
./wpf test:e2e --headed

# Create a new test
./wpf make:e2e AdminSettings
```

E2E tests live in `dev/test/e2e/tests/`.

### Test coverage check

```bash
# Standalone — list classes missing tests
./wpf coverage

# Also runs as stage 7 of ./wpf check
```

Scans `app/` for classes, checks for a matching `{Class}Test.php` under `dev/test/tests/`. Skips interfaces, traits, data-only classes, and directories listed in `composer.json` under `extra.wpfluent.test-coverage.exclude`. Per-class opt-out via `@no-test-required` docblock (requires a `Reason:` line).

### Test configuration

`dev/test/config.php` auto-detects your site URL and provides credential defaults:

- `site_url` — resolved from `WP_URL` env var → `wp-config.php` → directory name convention
- `admin_user` / `admin_pass` — from `WP_USER`/`WP_PASS` env vars, defaults to `admin`/`admin`

Shared by PHPUnit (`TestCase::setUp`) and E2E (`test:e2e` command). No manual configuration needed in most setups.

## Debug Helpers

Available when `dev/globals.php` is loaded:

- `dd($value)` — Dump and die
- `ddd($value)` — Dump without dying
- `wpf_log($data, $label)` — Log to `debug.log` with file/line info
- `debug($data)` — Alias for `wpf_log`

## Directory Structure

```
dev/
├── vendor/wpfluent/devtools/  # CLI, PHPStan rules, test base classes (Composer package)
├── test/
│   ├── config.php             # Auto-detected site URL + credentials
│   ├── tests/                 # PHPUnit tests (Unit/, Integration/, Feature/)
│   └── e2e/                   # Playwright browser tests (opt-in via make:e2e)
├── factories/                 # Test data factories
├── seeders/                   # Database seeders
├── tools/                     # PHP-Scoper PHAR + config
├── ComposerScript.php         # Namespace rewriting (auto-runs on composer install)
├── globals.php                # Debug helpers (dd, wpf_log, etc.)
├── phpunit.xml.dist           # PHPUnit config (MySQL)
├── phpunit_sqlite.xml         # PHPUnit config (SQLite)
├── phpcs.xml                  # PHPCS configuration
├── review-rules.json          # Customizable review rules
└── composer.json              # Requires wpfluent/devtools
```

## Updating Dev Tools

```bash
cd dev && composer update wpfluent/devtools
```

For full documentation, see the [WPFluent Docs](https://developers.wpfluent.com).
