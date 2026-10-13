<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Ruvelo\FilamentSecurityAlerts\Pages\SecurityAlertsPage;
use Ruvelo\FilamentSecurityAlerts\SecurityAlertsPlugin;
use Ruvelo\FilamentSecurityAlerts\Tests\TestCase;
use Ruvelo\FilamentSecurityAlerts\Widgets\SecurityAlertsOverview;
use Ruvelo\SecurityAdvisories\Severity;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;

class WidgetTest extends TestCase
{
    private function checked(int $hoursAgo = 3, bool $complete = true): void
    {
        cache()->forever('security-alerts:last-check', ['checked_at' => now()->subHours($hoursAgo)->toIso8601String(), 'packages' => 105, 'findings' => 0, 'exploited' => 0, 'complete' => $complete]);
    }

    public function test_it_counts_alerts_by_severity_and_exploited(): void
    {
        $this->signIn();
        $this->checked();
        SecurityAlert::factory()->exploited()->forPackage('livewire/livewire', '3.6.2')->create();
        SecurityAlert::factory()->severity(Severity::Critical)->count(2)->create();
        SecurityAlert::factory()->severity(Severity::High)->count(4)->create();
        SecurityAlert::factory()->count(5)->create();
        SecurityAlert::factory()->severity(Severity::Low)->create();
        SecurityAlert::factory()->severity(Severity::NotRated)->create();
        SecurityAlert::factory()->resolved()->severity(Severity::Critical)->create();

        $stats = Livewire::test(SecurityAlertsOverview::class)->assertOk()->assertSee('Security alerts')->instance();
        $values = collect($this->stats($stats))->mapWithKeys(fn ($stat): array => [(string) $stat->getLabel() => $stat->getValue()]);

        // The exploited alert is critical too, so it is in both counts.
        $this->assertSame([
            'Exploited in the wild' => 1,
            'Critical' => 3,
            'High' => 4,
            'Medium' => 5,
            'Low' => 2,
            'Last checked' => '3h ago',
        ], $values->all());
    }

    public function test_it_is_red_and_names_the_package_when_anything_is_exploited(): void
    {
        $this->signIn();
        $this->checked();
        SecurityAlert::factory()->exploited()->forPackage('livewire/livewire', '3.6.2')->create();

        $stats = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()));
        $exploited = $stats->first();

        $this->assertSame('danger', $exploited->getColor());
        $this->assertSame('livewire/livewire 3.6.2 · patch today', $exploited->getDescription());
        $this->assertSame('fi-sa-exploited', $exploited->getExtraAttributes()['class']);

        Livewire::test(SecurityAlertsOverview::class)->assertSee('livewire/livewire 3.6.2 · patch today')->assertSeeHtml('fi-sa-exploited');
    }

    public function test_it_is_calm_when_nothing_is_exploited(): void
    {
        $this->signIn();
        $this->checked();
        SecurityAlert::factory()->severity(Severity::High)->create();

        $exploited = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()))->first();

        $this->assertSame('success', $exploited->getColor());
        $this->assertSame('Nothing under attack', $exploited->getDescription());
        Livewire::test(SecurityAlertsOverview::class)->assertDontSeeHtml('fi-sa-exploited');
    }

    public function test_it_says_all_clear_when_nothing_is_open(): void
    {
        $this->signIn();
        $this->checked();

        $stats = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()));

        $this->assertSame('All clear', $stats->first()->getDescription());
        $this->assertSame('105 packages checked', $stats->last()->getDescription());
        $this->assertSame('gray', $stats->last()->getColor());
    }

    public function test_it_warns_when_no_check_ran_for_48_hours(): void
    {
        $this->signIn();
        $this->checked(hoursAgo: 49);

        $last = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()))->last();

        $this->assertSame('warning', $last->getColor());
        $this->assertSame('2d ago', $last->getValue());
        $this->assertStringContainsString('Over 48 hours ago', (string) $last->getDescription());
    }

    public function test_47_hours_is_still_fine(): void
    {
        $this->signIn();
        $this->checked(hoursAgo: 47);

        $last = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()))->last();

        $this->assertSame('gray', $last->getColor());
    }

    public function test_the_limit_is_configurable(): void
    {
        $this->signIn();
        $this->plugin()->staleAfterHours(6);
        $this->checked(hoursAgo: 7);

        $last = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()))->last();

        $this->assertSame('warning', $last->getColor());
        $this->assertStringContainsString('Over 6 hours ago', (string) $last->getDescription());
    }

    public function test_it_warns_when_no_check_has_ever_run(): void
    {
        $this->signIn();

        $last = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()))->last();

        $this->assertSame('Never', $last->getValue());
        $this->assertSame('warning', $last->getColor());
        $this->assertSame('Run a check, or start the scheduler', $last->getDescription());
    }

    public function test_it_warns_about_an_incomplete_check(): void
    {
        $this->signIn();
        $this->checked(complete: false);

        $last = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()))->last();

        $this->assertSame('warning', $last->getColor());
    }

    public function test_stats_link_to_the_filtered_page(): void
    {
        $this->signIn();
        $stats = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()));
        $page = SecurityAlertsPage::getUrl();

        $this->assertSame($page.'?tableFilters%5Bexploited%5D%5BisActive%5D=1', $stats[0]->getUrl());
        $this->assertSame($page.'?tableFilters%5Bseverity%5D%5Bvalues%5D%5B0%5D=critical', $stats[1]->getUrl());
        $this->assertSame($page.'?tableFilters%5Bseverity%5D%5Bvalues%5D%5B0%5D=low&tableFilters%5Bseverity%5D%5Bvalues%5D%5B1%5D=unknown', $stats[4]->getUrl());
        $this->assertSame($page, $stats[5]->getUrl());
    }

    public function test_a_link_from_the_widget_opens_the_matching_rows(): void
    {
        $this->signIn();
        $livewire = SecurityAlert::factory()->exploited()->create();
        $medium = SecurityAlert::factory()->create();
        $stats = collect($this->stats(Livewire::test(SecurityAlertsOverview::class)->instance()));

        parse_str((string) parse_url((string) $stats[0]->getUrl(), PHP_URL_QUERY), $query);

        Livewire::withQueryParams($query)->test(SecurityAlertsPage::class)
            ->assertCanSeeTableRecords([$livewire])
            ->assertCanNotSeeTableRecords([$medium]);
    }

    public function test_it_is_on_the_dashboard_by_default(): void
    {
        $this->signIn();

        $this->assertContains(SecurityAlertsOverview::class, Filament::getPanel('admin')->getWidgets());
        $this->get('/admin')->assertOk()->assertSeeLivewire(SecurityAlertsOverview::class);
    }

    public function test_it_can_be_left_off(): void
    {
        $panel = Panel::make()->id('docs')->path('docs');
        SecurityAlertsPlugin::make()->widget(false)->register($panel);
        $this->assertNotContains(SecurityAlertsOverview::class, $panel->getWidgets());

        $panel = Panel::make()->id('docs')->path('docs');
        SecurityAlertsPlugin::make()->register($panel);
        $this->assertContains(SecurityAlertsOverview::class, $panel->getWidgets());
    }

    public function test_it_is_hidden_from_people_who_cannot_see_the_page(): void
    {
        $this->signIn();
        $this->assertTrue(SecurityAlertsOverview::canView());

        Gate::define('view-security-alerts', fn () => false);
        $this->assertFalse(SecurityAlertsOverview::canView());
    }

    public function test_it_does_not_break_the_dashboard_before_the_migration(): void
    {
        $this->signIn();
        Schema::drop('security_alerts');

        Livewire::test(SecurityAlertsOverview::class)->assertOk()->assertSee('Never');
    }

    /**
     * @return array<Stat>
     */
    private function stats(SecurityAlertsOverview $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getStats');

        /** @var array<Stat> */
        return $method->invoke($widget);
    }
}
