<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Ruvelo\FilamentSecurityAlerts\Pages\SecurityAlertsPage;
use Ruvelo\FilamentSecurityAlerts\Tests\TestCase;
use Ruvelo\SecurityAlerts\Ecosystem;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;
use Ruvelo\SecurityAlerts\Severity;

class PageTest extends TestCase
{
    private function halyard(): object
    {
        return (object) [
            'livewire' => SecurityAlert::factory()->exploited('2026-03-20', '2026-04-03')->forPackage('livewire/livewire', '3.6.2')->create(['title' => 'Remote command execution in Livewire', 'cve' => 'CVE-2025-54068', 'fixed_in' => '3.6.4', 'affected_versions' => '<3.6.4']),
            'critical' => SecurityAlert::factory()->severity(Severity::Critical)->forPackage('acme/pay', '2.0.0')->create(['title' => 'Auth bypass']),
            'high' => SecurityAlert::factory()->severity(Severity::High)->forPackage('axios', '1.7.0', Ecosystem::Npm)->create(['title' => 'SSRF in axios', 'fixed_in' => '1.8.2']),
            'medium' => SecurityAlert::factory()->forPackage('acme/ui', '1.0.0')->create(['title' => 'XSS in the admin']),
            'low' => SecurityAlert::factory()->severity(Severity::Low)->forPackage('acme/log', '1.0.0')->create(['title' => 'Info leak', 'fixed_in' => null]),
            'unknown' => SecurityAlert::factory()->severity(Severity::Unknown)->forPackage('acme/old', '0.1.0')->create(),
            'fixed' => SecurityAlert::factory()->resolved()->forPackage('acme/fixed', '1.0.0')->create(),
        ];
    }

    public function test_it_lists_open_alerts_exploited_first_then_by_severity(): void
    {
        $this->signIn();
        $a = $this->halyard();

        Livewire::test(SecurityAlertsPage::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$a->livewire, $a->critical, $a->high, $a->medium, $a->unknown, $a->low], inOrder: true)
            ->assertCanNotSeeTableRecords([$a->fixed]);
    }

    public function test_an_exploited_flaw_leads_even_when_a_critical_one_is_newer(): void
    {
        $this->signIn();
        $critical = SecurityAlert::factory()->severity(Severity::Critical)->create();
        $exploited = SecurityAlert::factory()->exploited()->create(['severity' => Severity::Medium]);

        Livewire::test(SecurityAlertsPage::class)->assertCanSeeTableRecords([$exploited, $critical], inOrder: true);
    }

    public function test_rows_show_the_kev_badge_dates_versions_and_fix(): void
    {
        $this->signIn();
        $a = $this->halyard();

        Livewire::test(SecurityAlertsPage::class)
            ->assertSee('Exploited')
            ->assertSee('Listed 20 Mar · due 3 Apr')
            ->assertSee('livewire/livewire')
            ->assertSee('CVE-2025-54068')
            ->assertSee('3.6.2')
            ->assertSee('3.6.4')
            ->assertSee('No fix yet')
            ->assertSee('Composer')
            ->assertSee('npm')
            ->assertTableColumnStateSet('version', '3.6.2', $a->livewire)
            ->assertTableColumnStateSet('fixed_in', '3.6.4', $a->livewire)
            ->assertTableColumnFormattedStateSet('severity', 'Critical', $a->livewire)
            ->assertTableColumnStateSet('exploited_at', 'Exploited', $a->livewire)
            ->assertTableColumnStateSet('exploited_at', null, $a->critical);
    }

    public function test_ransomware_use_is_flagged(): void
    {
        $this->signIn();
        SecurityAlert::factory()->exploited()->create(['ransomware' => true]);

        Livewire::test(SecurityAlertsPage::class)->assertSee('ransomware');
    }

    public function test_the_exploit_note_copes_without_a_due_date(): void
    {
        $this->signIn();
        SecurityAlert::factory()->exploited('2026-09-30', null)->create();

        Livewire::test(SecurityAlertsPage::class)->assertSee('Listed 30 Sep')->assertDontSee('due ');
    }

    public function test_dates_from_earlier_years_carry_the_year(): void
    {
        $this->signIn();
        SecurityAlert::factory()->exploited('2025-12-01', '2025-12-22')->create();

        Livewire::test(SecurityAlertsPage::class)->assertSee('Listed 1 Dec 2025 · due 22 Dec 2025');
    }

    public function test_titles_link_to_the_advisory_in_a_new_tab(): void
    {
        $this->signIn();
        $alert = SecurityAlert::factory()->create(['link' => 'https://github.com/advisories/GHSA-29cq-5w36-x7w3']);

        $page = Livewire::test(SecurityAlertsPage::class);
        $column = $page->instance()->getTable()->getColumn('title');

        $this->assertNotNull($column);
        $column->record($alert);
        $this->assertSame('https://github.com/advisories/GHSA-29cq-5w36-x7w3', $column->getUrl());
        $this->assertTrue($column->shouldOpenUrlInNewTab());
    }

    public function test_only_web_links_become_links(): void
    {
        $this->assertSame('https://example.com/a', SecurityAlertsPage::safeUrl('https://example.com/a'));
        $this->assertSame('HTTP://example.com', SecurityAlertsPage::safeUrl('HTTP://example.com'));
        $this->assertNull(SecurityAlertsPage::safeUrl('javascript:alert(1)'));
        $this->assertNull(SecurityAlertsPage::safeUrl('data:text/html,<script>'));
        $this->assertNull(SecurityAlertsPage::safeUrl('//evil.test'));
        $this->assertNull(SecurityAlertsPage::safeUrl(null));
    }

    public function test_titles_are_escaped(): void
    {
        $this->signIn();
        SecurityAlert::factory()->create(['title' => '<script>alert("pwned")</script>', 'link' => 'javascript:alert(1)']);

        Livewire::test(SecurityAlertsPage::class)
            ->assertDontSeeHtml('<script>alert("pwned")</script>')
            ->assertDontSeeHtml('javascript:alert(1)')
            ->assertSee('alert("pwned")');
    }

    public function test_it_filters_by_severity(): void
    {
        $this->signIn();
        $a = $this->halyard();

        Livewire::test(SecurityAlertsPage::class)
            ->filterTable('severity', ['critical'])
            ->assertCanSeeTableRecords([$a->livewire, $a->critical])
            ->assertCanNotSeeTableRecords([$a->high, $a->medium, $a->low])
            ->filterTable('severity', ['high', 'low'])
            ->assertCanSeeTableRecords([$a->high, $a->low])
            ->assertCanNotSeeTableRecords([$a->livewire, $a->critical, $a->medium]);
    }

    public function test_it_filters_by_ecosystem(): void
    {
        $this->signIn();
        $a = $this->halyard();

        Livewire::test(SecurityAlertsPage::class)
            ->filterTable('ecosystem', 'npm')
            ->assertCanSeeTableRecords([$a->high])
            ->assertCanNotSeeTableRecords([$a->livewire, $a->critical, $a->medium])
            ->filterTable('ecosystem', 'composer')
            ->assertCanNotSeeTableRecords([$a->high])
            ->assertCanSeeTableRecords([$a->livewire, $a->medium]);
    }

    public function test_it_filters_to_exploited(): void
    {
        $this->signIn();
        $a = $this->halyard();

        Livewire::test(SecurityAlertsPage::class)
            ->filterTable('exploited')
            ->assertCanSeeTableRecords([$a->livewire])
            ->assertCanNotSeeTableRecords([$a->critical, $a->high, $a->medium, $a->low]);
    }

    public function test_filters_can_come_from_the_address(): void
    {
        $this->signIn();
        $a = $this->halyard();

        Livewire::withQueryParams(['tableFilters' => ['exploited' => ['isActive' => true]]])
            ->test(SecurityAlertsPage::class)
            ->assertCanSeeTableRecords([$a->livewire])
            ->assertCanNotSeeTableRecords([$a->critical, $a->high]);
    }

    public function test_it_searches_packages_titles_and_cves(): void
    {
        $this->signIn();
        $a = $this->halyard();

        Livewire::test(SecurityAlertsPage::class)
            ->searchTable('livewire')
            ->assertCanSeeTableRecords([$a->livewire])
            ->assertCanNotSeeTableRecords([$a->critical, $a->high])
            ->searchTable('CVE-2025-54068')
            ->assertCanSeeTableRecords([$a->livewire])
            ->assertCanNotSeeTableRecords([$a->critical])
            ->searchTable('SSRF')
            ->assertCanSeeTableRecords([$a->high])
            ->assertCanNotSeeTableRecords([$a->livewire])
            ->searchTable($a->critical->advisory_id)
            ->assertCanSeeTableRecords([$a->critical])
            ->assertCanNotSeeTableRecords([$a->high]);
    }

    public function test_an_empty_table_says_all_clear(): void
    {
        $this->signIn();
        SecurityAlert::factory()->resolved()->create();
        cache()->forever('security-alerts:last-check', ['checked_at' => now()->subHours(3)->toIso8601String(), 'packages' => 105, 'findings' => 0, 'exploited' => 0, 'complete' => true]);

        Livewire::test(SecurityAlertsPage::class)
            ->assertSee('All clear')
            ->assertSee('None of the 105 packages this app runs has a known vulnerability.')
            ->assertSee('Checked 3 hours ago.');
    }

    public function test_an_all_clear_without_a_package_count_still_reads_well(): void
    {
        $this->signIn();
        cache()->forever('security-alerts:last-check', ['checked_at' => now()->subHour()->toIso8601String()]);

        Livewire::test(SecurityAlertsPage::class)->assertSee('None of the packages this app runs has a known vulnerability.');
    }

    public function test_before_any_check_it_says_nothing_has_been_found_yet(): void
    {
        $this->signIn();

        Livewire::test(SecurityAlertsPage::class)
            ->assertSee('Nothing found yet')
            ->assertDontSee('All clear')
            ->assertSee('Not checked yet');
    }

    public function test_filters_that_match_nothing_are_not_all_clear(): void
    {
        $this->signIn();
        $this->halyard();

        Livewire::test(SecurityAlertsPage::class)
            ->filterTable('ecosystem', 'npm')
            ->searchTable('does-not-exist')
            ->assertSee('Nothing matches')
            ->assertDontSee('All clear');
    }

    public function test_the_status_line_says_when_the_last_check_ran(): void
    {
        $this->signIn();
        cache()->forever('security-alerts:last-check', ['checked_at' => now()->subHours(5)->toIso8601String(), 'packages' => 105, 'findings' => 0, 'exploited' => 0, 'complete' => true]);

        Livewire::test(SecurityAlertsPage::class)
            ->assertSee('5 hours ago')
            ->assertSee('105 packages looked at')
            ->assertDontSee('Out of date');
    }

    public function test_the_status_line_warns_when_the_check_is_old(): void
    {
        $this->signIn();
        cache()->forever('security-alerts:last-check', ['checked_at' => now()->subHours(60)->toIso8601String(), 'complete' => true]);

        Livewire::test(SecurityAlertsPage::class)->assertSee('Out of date')->assertSee('Is the Laravel scheduler running?');
    }

    public function test_the_status_line_warns_about_an_incomplete_check(): void
    {
        $this->signIn();
        cache()->forever('security-alerts:last-check', ['checked_at' => now()->subHour()->toIso8601String(), 'complete' => false]);

        Livewire::test(SecurityAlertsPage::class)->assertSee('Incomplete')->assertSee('Nothing was marked as fixed.');
    }

    public function test_without_the_migration_the_page_explains_instead_of_crashing(): void
    {
        $this->signIn();
        Schema::drop('security_alerts');

        $this->get(SecurityAlertsPage::getUrl())->assertOk()->assertSee('The alerts table is missing');
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get(SecurityAlertsPage::getUrl())->assertRedirect();
    }

    public function test_the_page_is_at_the_configured_slug(): void
    {
        $this->assertStringEndsWith('/admin/security-alerts', SecurityAlertsPage::getUrl());
    }
}
