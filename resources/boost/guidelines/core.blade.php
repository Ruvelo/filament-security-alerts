## ruvelo/filament-security-alerts

A Filament panel plugin for `ruvelo/laravel-security-alerts`: a "Security alerts" page (the open findings, exploited first, with filters and a "Check now" button), a dashboard widget (counts by severity and exploited, plus when the last check ran) and a navigation badge (exploited plus critical). It works with Filament 4 and 5. It adds no tables and no models: everything it shows is the package's own `security_alerts` table, and its only write is `SecurityAlerts::check()`.

### Registering it

Add the plugin to a panel provider. Every option is optional.

@verbatim
<code-snippet name="Register the plugin on a panel" lang="php">
use Ruvelo\FilamentSecurityAlerts\SecurityAlertsPlugin;

return $panel
    ->plugin(
        SecurityAlertsPlugin::make()
            ->navigationGroup('System')       // string, enum or closure
            ->navigationLabel('Vulnerabilities') // default "Security alerts"
            ->navigationIcon('heroicon-o-shield-exclamation')
            ->navigationSort(9)
            ->navigationBadge(true)           // exploited + critical count; false to hide
            ->slug('vulnerabilities')         // URL: /admin/vulnerabilities (default "security-alerts")
            ->canAccess(fn ($user) => $user->is_admin) // default: the `view-security-alerts` gate if defined
            ->canCheck(fn ($user) => $user->is_admin)  // default: the `check-security-alerts` gate if defined
            ->notifyOnCheck(false)            // true: a manual check also sends the alert email/Slack
            ->staleAfterHours(48)             // warn when no check ran for this long
            ->widget(true)                    // the dashboard widget
    );
</code-snippet>
@endverbatim

- Read the panel's configuration with `SecurityAlertsPlugin::get()`, e.g. `SecurityAlertsPlugin::get()->userCanAccess($user)`.
- Who may see it: by default everyone who can use the panel, unless the app defines the `view-security-alerts` gate. Define the gate or pass `canAccess()`; the page lists package names and versions of an app, which is worth restricting in a panel with non-admin users.
- Who may run a check: the `check-security-alerts` gate if defined, otherwise whoever can see the page.

### Do not

- Do not write to the `security_alerts` table or the `SecurityAlert` model from your own code: the package's `SecurityAlerts::check()` is the only write path, and writing around it breaks "send each alert once".
- Do not call the advisory feeds from the page or widget. They read the table and the cache; only "Check now" (`SecurityAlerts::check()`) uses the network.
- Do not add Tailwind classes to customise the plugin's views: they won't exist in Filament's compiled CSS. Use a custom Filament theme, or publish the views (`php artisan vendor:publish --tag=filament-security-alerts-views`).

### Tests

Create alerts with the package's factory and test the page as a Livewire component. Fake the feeds with `Http::fake()` when testing a check, so nothing reaches the network:

@verbatim
<code-snippet name="Test the plugin's page" lang="php">
use Ruvelo\FilamentSecurityAlerts\Pages\SecurityAlertsPage;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;

$exploited = SecurityAlert::factory()->exploited()->forPackage('livewire/livewire', '3.6.2')->create();

Livewire::actingAs($admin)
    ->test(SecurityAlertsPage::class)
    ->assertCanSeeTableRecords([$exploited])
    ->filterTable('exploited')
    ->assertCanSeeTableRecords([$exploited]);
</code-snippet>
@endverbatim
