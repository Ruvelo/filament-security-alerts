<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Tests;

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Assert;
use Ruvelo\FilamentSecurityAlerts\FilamentSecurityAlertsServiceProvider;
use Ruvelo\FilamentSecurityAlerts\SecurityAlertsPlugin;
use Ruvelo\FilamentSecurityAlerts\Tests\Fixtures\AdminPanelProvider;
use Ruvelo\FilamentSecurityAlerts\Tests\Fixtures\User;
use Ruvelo\SecurityAlerts\Notifier;
use Ruvelo\SecurityAlerts\SecurityAlertsServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    public const FIXTURES = __DIR__.'/fixtures';

    /**
     * What package discovery loads in an app, in the same order (by package
     * name): Filament has to register before Livewire. Filament 4.0 has no
     * query builder package, so only what's installed is loaded.
     */
    public const PROVIDERS = [
        'BladeUI\\Heroicons\\BladeHeroiconsServiceProvider',
        'BladeUI\\Icons\\BladeIconsServiceProvider',
        'Filament\\Actions\\ActionsServiceProvider',
        'Filament\\FilamentServiceProvider',
        'Filament\\Forms\\FormsServiceProvider',
        'Filament\\Infolists\\InfolistsServiceProvider',
        'Filament\\Notifications\\NotificationsServiceProvider',
        'Filament\\QueryBuilder\\QueryBuilderServiceProvider',
        'Filament\\Schemas\\SchemasServiceProvider',
        'Filament\\Support\\SupportServiceProvider',
        'Filament\\Tables\\TablesServiceProvider',
        'Filament\\Widgets\\WidgetsServiceProvider',
        'Kirschbaum\\PowerJoins\\PowerJoinsServiceProvider',
        'Livewire\\LivewireServiceProvider',
        SecurityAlertsServiceProvider::class,
        'RyanChandler\\BladeCaptureDirective\\BladeCaptureDirectiveServiceProvider',
    ];

    /** @var array<string, mixed> URL pattern => response, first match wins */
    protected array $feeds = [];

    protected function getPackageProviders($app): array
    {
        return [
            ...array_values(array_filter(self::PROVIDERS, class_exists(...))),
            FilamentSecurityAlertsServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('security-alerts.composer.lockfile', self::FIXTURES.'/composer.lock');
        $app['config']->set('security-alerts.npm.lockfile', self::FIXTURES.'/package-lock.json');
        $app['config']->set('security-alerts.notify.mail', []);
        $app['config']->set('security-alerts.notify.slack', null);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach Packagist, OSV or CISA.
        Http::preventStrayRequests();
        Sleep::fake();
        Carbon::setTestNow('2026-10-12 09:00:00');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Livewire 4 sends action modals as partial renders; Livewire 3
        // renders them inline. This sees both.
        Testable::macro('assertModalSee', function (string $html): Testable {
            /** @var Testable $this */
            $partials = data_get($this->lastState->getEffects(), 'partials', []);
            Assert::assertStringContainsString($html, implode('', (array) $partials).$this->html());

            return $this;
        });
    }

    protected function tearDown(): void
    {
        Notifier::using(null);
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function user(string $name = 'Maya Okafor'): User
    {
        return User::query()->firstOrCreate(
            ['email' => strtolower(strtok($name, ' ') ?: $name).'@halyard.test'],
            ['name' => $name, 'password' => 'secret'],
        );
    }

    protected function signIn(string $name = 'Maya Okafor'): User
    {
        $user = $this->user($name);
        $this->actingAs($user);

        return $user;
    }

    protected function plugin(): SecurityAlertsPlugin
    {
        return SecurityAlertsPlugin::get();
    }

    /**
     * Answers every feed from tests/fixtures: the responses recorded for the
     * Halyard lockfiles. Pass replacements to change one feed.
     *
     * @param  array<string, mixed>  $overrides  URL pattern => response
     */
    protected function fakeFeeds(array $overrides = []): void
    {
        $this->feeds = $overrides + [
            'packagist.org/api/security-advisories/*' => fn () => Http::response(self::fixture('packagist.json')),
            'api.osv.dev/v1/querybatch' => fn () => Http::response(self::fixture('osv-querybatch.json')),
            'api.osv.dev/v1/vulns/*' => fn (Request $request) => Http::response(self::fixture('osv/'.basename($request->url()).'.json')),
            'www.cisa.gov/*' => fn () => Http::response(self::fixture('kev.json')),
        ];

        Http::fake(function (Request $request, array $options) {
            foreach ($this->feeds as $pattern => $response) {
                if (Str::is(Str::start($pattern, '*'), $request->url())) {
                    return is_callable($response) ? $response($request, $options) : $response;
                }
            }

            return null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixture(string $name): array
    {
        $data = json_decode((string) file_get_contents(self::FIXTURES.'/'.$name), true, 512, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }
}
