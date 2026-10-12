<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Ruvelo\FilamentSecurityAlerts\Pages\SecurityAlertsPage;
use Ruvelo\FilamentSecurityAlerts\Tests\TestCase;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;
use Ruvelo\SecurityAlerts\Notifications\VulnerabilitiesFound;
use Ruvelo\SecurityAlerts\SecurityAlerts;

class CheckActionTest extends TestCase
{
    public function test_check_now_runs_the_check_and_fills_the_table(): void
    {
        $this->signIn();
        $this->fakeFeeds();

        $page = Livewire::test(SecurityAlertsPage::class)->assertSee('Nothing found yet');

        $page->callAction('check')
            ->assertNotified('Check complete: exploited in the wild')
            ->assertSee('livewire/livewire')
            ->assertDontSee('Nothing found yet');

        $this->assertSame(13, SecurityAlert::query()->open()->count());
        $this->assertSame(1, SecurityAlert::query()->exploited()->count());
        $this->assertNotNull(SecurityAlerts::lastCheck());
        $page->assertSee('packages looked at')->assertDontSee('Not checked yet')->assertDontSee('Out of date');
    }

    public function test_the_first_check_reports_new_alerts(): void
    {
        $this->signIn();
        $this->fakeFeeds();

        $notification = SecurityAlertsPage::announce(SecurityAlerts::check(notify: false));

        $this->assertSame('Check complete: exploited in the wild', $notification->getTitle());
        $this->assertSame('13 open alerts: 13 new.', $notification->getBody());
    }

    public function test_a_clean_check_says_all_clear(): void
    {
        $this->signIn();
        $this->fakeFeeds(['packagist.org/api/security-advisories/*' => fn () => Http::response(['advisories' => []]), 'api.osv.dev/v1/querybatch' => fn (Request $request) => Http::response(['results' => array_map(fn () => (object) [], $request['queries'])])]);

        Livewire::test(SecurityAlertsPage::class)
            ->callAction('check')
            ->assertNotified('All clear')
            ->assertSee('None of the')
            ->assertSee('has a known vulnerability');
    }

    public function test_fixed_alerts_are_counted_and_closed(): void
    {
        $this->signIn();
        $this->fakeFeeds();
        SecurityAlerts::check(notify: false);

        $this->fakeFeeds(['packagist.org/api/security-advisories/*' => fn () => Http::response(['advisories' => []]), 'api.osv.dev/v1/querybatch' => fn (Request $request) => Http::response(['results' => array_map(fn () => (object) [], $request['queries'])])]);

        $notification = SecurityAlertsPage::announce(SecurityAlerts::check(fresh: true, notify: false));

        $this->assertSame('All clear', $notification->getTitle());
        $this->assertStringContainsString('13 fixed', (string) $notification->getBody());
        $this->assertSame(0, SecurityAlert::query()->open()->count());
    }

    public function test_an_incomplete_check_warns_and_closes_nothing(): void
    {
        $this->signIn();
        $this->fakeFeeds();
        SecurityAlerts::check(notify: false);

        $this->fakeFeeds(['packagist.org/api/security-advisories/*' => fn () => Http::response('down', 503)]);

        Livewire::test(SecurityAlertsPage::class)
            ->callAction('check')
            ->assertNotified('Check incomplete');

        $this->assertGreaterThan(0, SecurityAlert::query()->open()->count());
    }

    public function test_the_check_uses_fresh_feeds(): void
    {
        $this->signIn();
        $this->fakeFeeds();

        Livewire::test(SecurityAlertsPage::class)->callAction('check');
        $first = count(Http::recorded());
        $this->assertGreaterThan(0, $first);

        Livewire::test(SecurityAlertsPage::class)->callAction('check');
        $this->assertGreaterThan($first, count(Http::recorded()));
    }

    public function test_it_does_not_send_alerts_by_default(): void
    {
        $this->signIn();
        $this->fakeFeeds();
        config()->set('security-alerts.notify.mail', ['ops@halyard.test']);
        Notifications::fake();

        Livewire::test(SecurityAlertsPage::class)->callAction('check');

        Notifications::assertNothingSent();
        $this->assertSame(13, SecurityAlert::query()->whereNull('notified_at')->count());
    }

    public function test_it_can_send_alerts_like_the_scheduled_check(): void
    {
        $this->signIn();
        $this->plugin()->notifyOnCheck();
        $this->fakeFeeds();
        config()->set('security-alerts.notify.mail', ['ops@halyard.test']);
        Notifications::fake();

        Livewire::test(SecurityAlertsPage::class)->callAction('check');

        Notifications::assertSentOnDemandTimes(VulnerabilitiesFound::class, 1);
        $this->assertSame(0, SecurityAlert::query()->whereNull('notified_at')->count());
    }

    public function test_without_the_migration_it_says_what_to_do(): void
    {
        $this->signIn();
        $this->fakeFeeds();
        Schema::drop('security_alerts');

        Livewire::test(SecurityAlertsPage::class)
            ->callAction('check')
            ->assertNotified('Security alerts isn’t set up yet');
    }

    public function test_the_button_is_hidden_from_people_who_may_not_check(): void
    {
        $this->signIn();
        Gate::define('check-security-alerts', fn () => false);

        Livewire::test(SecurityAlertsPage::class)->assertActionHidden('check')->assertOk();
    }

    public function test_calling_the_action_without_permission_is_forbidden(): void
    {
        $this->signIn();
        $this->fakeFeeds();
        $this->plugin()->canCheck(false);

        Livewire::test(SecurityAlertsPage::class)->call('checkNow')->assertForbidden();

        $this->assertSame(0, SecurityAlert::query()->count());
        Http::assertNothingSent();
    }

    public function test_the_button_is_visible_by_default(): void
    {
        $this->signIn();

        Livewire::test(SecurityAlertsPage::class)->assertActionVisible('check')->assertActionEnabled('check');
    }
}
