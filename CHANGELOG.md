# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-10-13

Requires `ruvelo/laravel-security-alerts` ^2.0, which moved its scanning to the shared core `ruvelo/security-advisories`. Nothing to do when upgrading: the stored alerts and the table are unchanged.

### Added

- The page's status line says where the last "Check now" got its answers: "via Valtry, signed" or "via public feeds". It is only shown while that check is the latest on record.

### Changed

- Requires `ruvelo/laravel-security-alerts` ^2.0 (was ^1.0), which asks Valtry's Advisory API by default and falls back to the public feeds. "Check now" does the same.
- Unrated severities use the core's `Severity::NotRated` (was `Unknown`); they still show as low.

## [1.0.0] - 2026-10-12

### Added

- `SecurityAlertsPlugin` for Filament 4 and 5 panels, with fluent options: navigation group, label, icon, sort and badge; the URL slug; who can see the page (`canAccess()`, the `view-security-alerts` gate by default); who can run a check (`canCheck()`, the `check-security-alerts` gate); whether a manual check sends alerts (`notifyOnCheck()`); how long before a missing check is flagged (`staleAfterHours()`); and the widget.
- A Security alerts page: the package's open findings in a table, exploited first, with severity and KEV badges, CISA's listing and due dates, ecosystem, installed and fixed-in versions, first seen and a link to the advisory. Filters for severity, ecosystem and exploited in the wild, and search over package, title, CVE and advisory ID. Filters can be set from the address.
- A Check now action that runs the check with fresh feeds and reports what it found: new and fixed alerts, exploited flaws, an incomplete check, or "All clear". Alerts are not emailed unless `notifyOnCheck()` is on.
- A status line (when the last check ran, and a warning when it is stale, missing or incomplete) and an empty state that says "All clear".
- A dashboard widget: counts by severity and exploited in the wild, red when anything is exploited, and the last-checked time with a warning after 48 hours. Each count links to the filtered page.
- A navigation badge counting exploited and critical alerts: red when any is exploited, amber otherwise.
- A page that explains itself, instead of an error, when the package's migration hasn't run.
- Laravel Boost guidelines (`resources/boost/guidelines/core.blade.php`).
- Screenshots of a Halyard admin panel in light and dark, and a gallery site on GitHub Pages.

[Unreleased]: https://github.com/Ruvelo/filament-security-alerts/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/Ruvelo/filament-security-alerts/releases/tag/v1.1.0
[1.0.0]: https://github.com/Ruvelo/filament-security-alerts/releases/tag/v1.0.0
