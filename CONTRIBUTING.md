# Contributing to Filament Security Alerts

Thanks for helping! Bug reports, docs fixes and features are all welcome.

## Before you start

- **Bugs:** [open an issue](https://github.com/Ruvelo/filament-security-alerts/issues/new/choose) with the steps to reproduce and your Filament version, or go straight to a pull request with a failing test.
- **Features:** open an issue first if it's more than a small change, so we can agree on the shape before you spend time on it.
- **The checks themselves** (lockfiles, advisory sources, the alerts table, notifications) live in [ruvelo/laravel-security-alerts](https://github.com/Ruvelo/laravel-security-alerts). Changes there come first; this plugin only builds on them.
- **Security issues:** don't open an issue. See [SECURITY.md](https://github.com/Ruvelo/.github/blob/main/SECURITY.md).

## Setup

You need PHP 8.3+ and Composer. No database server: tests run on in-memory SQLite.

`ruvelo/laravel-security-alerts` isn't on Packagist yet, so Composer needs to be told where it is. Add it to your own checkout (don't commit this) before installing:

```bash
composer config repositories.lsa vcs https://github.com/Ruvelo/laravel-security-alerts
```

CI does the same. To develop against a local copy of the package, use a `path` repository instead.

```bash
git clone https://github.com/<you>/filament-security-alerts && cd filament-security-alerts
composer install
composer check
```

`composer check` runs exactly what CI runs:

| Command | What it does |
|---|---|
| `composer test` | PHPUnit with Filament's Livewire testing helpers, through Orchestra Testbench |
| `composer lint` | Code style check (Laravel Pint) |
| `composer format` | Fix code style |
| `composer analyse` | Static analysis (PHPStan level 8 with Larastan) |
| `composer demo` | Build the screenshot gallery into `build/` |

CI runs the tests on PHP 8.3 and 8.4, Laravel 12 and 13, and Filament 4 and 5, with both the lowest and the latest dependencies. To try the lowest locally, copy the repo somewhere else and run `composer update --prefer-lowest` after requiring the versions you want.

## Seeing your change

`demo/screenshots.sh` seeds a Halyard admin panel by running a real check against the responses recorded in `tests/fixtures`, serves it with `php -S` on ports 8962 and 8963 and takes the screenshots in `art/`. To click around instead:

```bash
php demo/app.php seed
php -S 127.0.0.1:8962 -t build/demo/public demo/server.php
```

Then open http://127.0.0.1:8962/admin/security-alerts. You're signed in as Maya. The demo pretends it is 12 October 2026, 10:30, three and a half hours after the check that found these; `DEMO_CLEAR=1` serves the same panel after everything was patched.

## Making a change

1. Branch from `main`.
2. Write a test that fails without your change. Feature tests live in `tests/Feature`, unit tests in `tests/Unit`.
3. Keep the public API stable: `SecurityAlertsPlugin` and its options, the page and widget class names, the action name (`check`), the table filter names (`severity`, `ecosystem`, `exploited`) and the view names. If you must change one, say so in the PR.
4. Run `composer format` and `composer check`.
5. Add a line under **Unreleased** in [CHANGELOG.md](CHANGELOG.md).
6. Open the pull request. Screenshots help for anything visual: run `demo/screenshots.sh`.

## Where things live

```
src/SecurityAlertsPlugin.php             The plugin and its options
src/Pages/SecurityAlertsPage.php         The table, filters, Check now and the navigation badge
src/Widgets/SecurityAlertsOverview.php   The dashboard widget
src/Support/Summary.php                  Counts and staleness, shared by all three
resources/views/                         The status line, the "All clear" state and the few styles Filament has no class for
resources/boost/guidelines/              Laravel Boost guidelines for coding agents; keep them in step
tests/fixtures/                          Responses recorded for Halyard's lockfiles, copied from the package
demo/                                    The Halyard panel, screenshots, banner and gallery site
```

## Style

- `declare(strict_types=1)` everywhere, typed properties and return types.
- No `@phpstan-ignore` or baseline entries: fix the cause.
- Comments explain *why*, not *what*.
- UI is Filament's own: use Filament components, schemas and colour variables. Don't add Tailwind classes (they aren't in Filament's compiled CSS) or styles that fight the theme. Anything that must be styled goes in `resources/views/styles.blade.php`, scoped to `fi-sa-*`.
- The plugin never writes to the package's table: its one write is `SecurityAlerts::check()`. Pages and widgets read the table and the cache, never a feed.

By contributing you agree that your work is released under the [MIT license](LICENSE) and that you'll follow the [code of conduct](https://github.com/Ruvelo/.github/blob/main/CODE_OF_CONDUCT.md).
