<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Ruvelo\FilamentSecurityAlerts\Pages\SecurityAlertsPage;
use Ruvelo\FilamentSecurityAlerts\SecurityAlertsPlugin;
use Ruvelo\FilamentSecurityAlerts\Tests\TestCase;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;
use Ruvelo\SecurityAlerts\Severity;

class PluginTest extends TestCase
{
    public function test_it_registers_the_page_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('ruvelo-security-alerts'));
        $this->assertContains(SecurityAlertsPage::class, $panel->getPages());
        $this->assertSame($panel->getPlugin('ruvelo-security-alerts'), SecurityAlertsPlugin::get());
    }

    public function test_it_has_sensible_navigation_defaults(): void
    {
        $this->assertSame('Security alerts', SecurityAlertsPage::getNavigationLabel());
        $this->assertSame(Heroicon::OutlinedShieldExclamation, SecurityAlertsPage::getNavigationIcon());
        $this->assertNull(SecurityAlertsPage::getNavigationGroup());
        $this->assertNull(SecurityAlertsPage::getNavigationSort());
        $this->assertSame('security-alerts', SecurityAlertsPage::getSlug());
    }

    public function test_navigation_is_configurable(): void
    {
        $this->plugin()
            ->navigationGroup('System')
            ->navigationLabel(fn () => 'Vulnerabilities')
            ->navigationIcon('heroicon-o-bug-ant')
            ->navigationSort(9);

        $this->assertSame('System', SecurityAlertsPage::getNavigationGroup());
        $this->assertSame('Vulnerabilities', SecurityAlertsPage::getNavigationLabel());
        $this->assertSame('heroicon-o-bug-ant', SecurityAlertsPage::getNavigationIcon());
        $this->assertSame(9, SecurityAlertsPage::getNavigationSort());

        $this->signIn();
        $this->get(SecurityAlertsPage::getUrl())->assertOk()->assertSee('Vulnerabilities')->assertSee('System');
    }

    public function test_the_url_segment_is_configurable(): void
    {
        $panel = Panel::make()->id('ops')->path('ops')->plugin(SecurityAlertsPlugin::make()->slug('/vulnerabilities/'));

        $this->assertSame('vulnerabilities', SecurityAlertsPage::getSlug($panel));
        $this->assertSame('security-alerts', SecurityAlertsPage::getSlug(Panel::make()->id('other')));
    }

    public function test_everyone_in_the_panel_may_see_it_until_a_gate_says_otherwise(): void
    {
        $maya = $this->user('Maya Okafor');
        $tom = $this->user('Tom Reyes');

        $this->assertTrue($this->plugin()->userCanAccess($maya));
        $this->assertFalse($this->plugin()->userCanAccess());

        Gate::define('view-security-alerts', fn (Authenticatable $user) => $user->getAuthIdentifier() === $maya->id);

        $this->assertTrue($this->plugin()->userCanAccess($maya));
        $this->assertFalse($this->plugin()->userCanAccess($tom));

        $this->actingAs($tom);
        $this->assertFalse(SecurityAlertsPage::canAccess());
        $this->get(SecurityAlertsPage::getUrl())->assertForbidden();

        $this->actingAs($maya);
        $this->assertTrue(SecurityAlertsPage::canAccess());
        $this->get(SecurityAlertsPage::getUrl())->assertOk();
    }

    public function test_the_navigation_item_follows_access(): void
    {
        $this->signIn('Tom Reyes');
        Gate::define('view-security-alerts', fn () => false);

        $this->get('/admin')->assertOk()->assertDontSee('/admin/security-alerts');
    }

    public function test_the_navigation_item_is_there_for_those_with_access(): void
    {
        $this->signIn();

        $this->get('/admin')->assertOk()->assertSee('/admin/security-alerts');
    }

    public function test_access_can_be_decided_by_a_closure(): void
    {
        $maya = $this->user('Maya Okafor');
        $this->plugin()->canAccess(fn ($user): bool => $user->email === 'maya@halyard.test');

        $this->assertTrue($this->plugin()->userCanAccess($maya));
        $this->assertFalse($this->plugin()->userCanAccess($this->user('Tom Reyes')));
    }

    public function test_a_closure_overrides_the_gate(): void
    {
        $maya = $this->user('Maya Okafor');
        Gate::define('view-security-alerts', fn () => false);
        $this->plugin()->canAccess(true);

        $this->assertTrue($this->plugin()->userCanAccess($maya));
    }

    public function test_a_closure_can_ask_for_the_user_by_type(): void
    {
        $maya = $this->user('Maya Okafor');
        $this->plugin()->canAccess(fn (Authenticatable $admin): bool => $admin->getAuthIdentifier() === $maya->id);

        $this->assertTrue($this->plugin()->userCanAccess($maya));
        $this->assertFalse($this->plugin()->userCanAccess($this->user('Tom Reyes')));
    }

    public function test_guests_never_have_access(): void
    {
        $this->plugin()->canAccess(true)->canCheck(true);

        $this->assertFalse($this->plugin()->userCanAccess());
        $this->assertFalse($this->plugin()->userCanCheck());
    }

    public function test_checking_follows_access_unless_told_otherwise(): void
    {
        $maya = $this->user('Maya Okafor');
        $tom = $this->user('Tom Reyes');

        $this->assertTrue($this->plugin()->userCanCheck($maya));

        Gate::define('check-security-alerts', fn (Authenticatable $user) => $user->getAuthIdentifier() === $maya->id);
        $this->assertTrue($this->plugin()->userCanCheck($maya));
        $this->assertFalse($this->plugin()->userCanCheck($tom));

        $this->plugin()->canCheck(fn (): bool => true);
        $this->assertTrue($this->plugin()->userCanCheck($tom));
    }

    public function test_checking_never_outruns_access(): void
    {
        $maya = $this->user('Maya Okafor');
        $this->plugin()->canAccess(false)->canCheck(true);

        $this->assertFalse($this->plugin()->userCanCheck($maya));
    }

    public function test_the_signed_in_user_is_the_default(): void
    {
        $this->signIn();
        $this->plugin()->canCheck(false);

        $this->assertTrue($this->plugin()->userCanAccess());
        $this->assertFalse($this->plugin()->userCanCheck());
    }

    public function test_stale_after_hours_never_goes_below_one(): void
    {
        $this->assertSame(48, $this->plugin()->getStaleAfterHours());
        $this->assertSame(1, $this->plugin()->staleAfterHours(0)->getStaleAfterHours());
        $this->assertSame(72, $this->plugin()->staleAfterHours(72)->getStaleAfterHours());
    }

    public function test_notifying_on_check_is_off_by_default(): void
    {
        $this->assertFalse($this->plugin()->notifiesOnCheck());
        $this->assertTrue($this->plugin()->notifyOnCheck()->notifiesOnCheck());
        $this->assertFalse($this->plugin()->notifyOnCheck(false)->notifiesOnCheck());
    }

    public function test_it_adds_its_few_styles_to_the_panel(): void
    {
        $this->signIn();

        $this->get(SecurityAlertsPage::getUrl())->assertSee('.fi-sa-exploited', escape: false);
    }

    public function test_get_falls_back_to_defaults_outside_a_panel_with_the_plugin(): void
    {
        Filament::setCurrentPanel(Panel::make()->id('bare')->path('bare'));

        $this->assertNotSame(Filament::getPanel('admin')->getPlugin('ruvelo-security-alerts'), SecurityAlertsPlugin::get());
        $this->assertSame('Security alerts', SecurityAlertsPlugin::get()->getNavigationLabel());
    }

    public function test_the_boost_guidelines_render(): void
    {
        $guidelines = Blade::render((string) file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php'));

        $this->assertStringContainsString('## ruvelo/filament-security-alerts', $guidelines);
        $this->assertStringContainsString('SecurityAlertsPlugin::make()', $guidelines);
    }

    public function test_the_navigation_shows_the_urgent_count_with_a_colour(): void
    {
        $this->signIn();
        SecurityAlert::factory()->exploited()->create();
        SecurityAlert::factory()->severity(Severity::Critical)->count(2)->create();
        SecurityAlert::factory()->severity(Severity::High)->count(3)->create();

        $this->assertSame('3', SecurityAlertsPage::getNavigationBadge());
        $this->assertSame('danger', SecurityAlertsPage::getNavigationBadgeColor());
        $this->assertSame('3 critical, 1 exploited in the wild', SecurityAlertsPage::getNavigationBadgeTooltip());
        $this->get('/admin')->assertOk()->assertSee('fi-badge', escape: false);
    }

    public function test_an_exploited_alert_that_is_critical_counts_once(): void
    {
        SecurityAlert::factory()->exploited()->create();

        $this->assertSame('1', SecurityAlertsPage::getNavigationBadge());
    }

    public function test_critical_without_exploited_is_amber(): void
    {
        SecurityAlert::factory()->severity(Severity::Critical)->create();

        $this->assertSame('1', SecurityAlertsPage::getNavigationBadge());
        $this->assertSame('warning', SecurityAlertsPage::getNavigationBadgeColor());
        $this->assertSame('1 critical', SecurityAlertsPage::getNavigationBadgeTooltip());
    }

    public function test_an_exploited_flaw_with_a_lower_severity_still_counts(): void
    {
        SecurityAlert::factory()->exploited()->create(['severity' => Severity::Medium]);

        $this->assertSame('1', SecurityAlertsPage::getNavigationBadge());
        $this->assertSame('1 exploited in the wild', SecurityAlertsPage::getNavigationBadgeTooltip());
    }

    public function test_there_is_no_badge_without_urgent_alerts(): void
    {
        SecurityAlert::factory()->severity(Severity::High)->create();
        SecurityAlert::factory()->exploited()->resolved()->create();

        $this->assertNull(SecurityAlertsPage::getNavigationBadge());
        $this->assertNull(SecurityAlertsPage::getNavigationBadgeTooltip());
    }

    public function test_the_badge_can_be_turned_off(): void
    {
        SecurityAlert::factory()->exploited()->create();
        $this->plugin()->navigationBadge(false);

        $this->assertNull(SecurityAlertsPage::getNavigationBadge());

        $this->plugin()->navigationBadge(fn (): bool => true);
        $this->assertSame('1', SecurityAlertsPage::getNavigationBadge());
    }

    public function test_the_badge_does_not_break_the_panel_before_the_migration(): void
    {
        $this->signIn();
        Schema::drop('security_alerts');

        $this->assertNull(SecurityAlertsPage::getNavigationBadge());
        $this->get('/admin')->assertOk();
    }
}
