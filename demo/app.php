<?php

declare(strict_types=1);

/*
 * The Halyard admin panel the screenshots are taken of: Filament, the security
 * alerts package and this plugin in a Testbench app, signed in as Maya, served
 * by `php -S`. The alerts come from a real check of Halyard's lockfiles run
 * against the responses recorded in tests/fixtures: nothing reaches the network.
 *
 *   php demo/app.php seed          # build/demo: database, Filament assets
 *   php -S 127.0.0.1:8962 -t build/demo/public demo/server.php
 *
 * demo/screenshots.sh does both. Filament pages are Livewire components, so
 * they need this live server; the published site is a gallery of the shots.
 */

use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Orchestra\Testbench\Foundation\Application;
use Psr\Http\Client\ClientInterface;
use Ruvelo\FilamentSecurityAlerts\FilamentSecurityAlertsServiceProvider;
use Ruvelo\FilamentSecurityAlerts\SecurityAlertsPlugin;
use Ruvelo\FilamentSecurityAlerts\Tests\Support\HttpFacadeClient;
use Ruvelo\FilamentSecurityAlerts\Tests\TestCase;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;
use Ruvelo\SecurityAlerts\SecurityAlerts;

require __DIR__.'/../vendor/autoload.php';

/** DEMO_CLEAR=1 serves the same panel after everything was patched, for the "All clear" shot. */
define('CLEAR', getenv('DEMO_CLEAR') === '1');
define('DEMO_DIR', __DIR__.'/../build/'.(CLEAR ? 'demo-clear' : 'demo'));
const FIXTURES = __DIR__.'/../tests/fixtures';
/** The moment the demo pretends it is: three and a half hours after the check. */
const DEMO_NOW_AT = '2026-10-12 10:30:00';
define('DEMO_URL', 'http://127.0.0.1:'.(CLEAR ? '8963' : '8962'));

/** Ruvelo ultramarine as a Filament palette, with #3D4EFF at 600 for buttons. */
const ULTRAMARINE = [
    50 => '#EEF0FF', 100 => '#E0E3FF', 200 => '#C7CCFF', 300 => '#A5ADFF', 400 => '#8F9BFF', 500 => '#6573FF',
    600 => '#3D4EFF', 700 => '#2F3DE0', 800 => '#2733B5', 900 => '#232D8C', 950 => '#1E2150',
];

class DemoUser extends User implements FilamentUser
{
    protected $table = 'users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}

/** Every visitor is Maya, who looks after the servers. */
class SignInAsMaya extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        if (Auth::guest() && ($maya = DemoUser::query()->find(1)) !== null) {
            Auth::login($maya);
        }

        parent::authenticate($request, $guards);
    }
}

class HalyardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Halyard')
            ->colors(['primary' => ULTRAMARINE])
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class])
            ->navigationItems([
                NavigationItem::make('Customers')->icon(Heroicon::OutlinedUsers)->group('Billing')->url('#')->sort(1),
                NavigationItem::make('Invoices')->icon(Heroicon::OutlinedDocumentText)->group('Billing')->url('#')->sort(2),
            ])
            ->plugin(SecurityAlertsPlugin::make()->navigationGroup('System'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([SignInAsMaya::class]);
    }
}

/** Registered first, so everything after it sees the demo's settings. */
class DemoConfig extends ServiceProvider
{
    public function register(): void
    {
        $this->app->usePublicPath((string) realpath(DEMO_DIR.'/public'));

        $this->app['config']->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('h', 32)),
            'app.url' => DEMO_URL,
            'app.env' => 'local',
            'app.debug' => true,
            'auth.providers.users.model' => DemoUser::class,
            'database.default' => 'demo',
            'database.connections.demo' => ['driver' => 'sqlite', 'database' => realpath(DEMO_DIR.'/demo.sqlite'), 'foreign_key_constraints' => true],
            'session.driver' => 'file',
            'session.files' => realpath(DEMO_DIR.'/storage/framework/sessions'),
            'cache.default' => 'file',
            'cache.stores.file.path' => (string) realpath(DEMO_DIR.'/storage/framework/cache'),
            'security-alerts.source' => 'public',
            'security-alerts.composer.lockfile' => FIXTURES.'/composer.lock',
            'security-alerts.npm.lockfile' => FIXTURES.'/package-lock.json',
            'security-alerts.notify.mail' => [],
        ]);
    }
}

function demo_app(): LaravelApplication
{
    is_dir(DEMO_DIR.'/storage/framework/sessions') || mkdir(DEMO_DIR.'/storage/framework/sessions', 0777, true);
    is_dir(DEMO_DIR.'/storage/framework/cache') || mkdir(DEMO_DIR.'/storage/framework/cache', 0777, true);
    is_dir(DEMO_DIR.'/public') || mkdir(DEMO_DIR.'/public', 0777, true);
    touch(DEMO_DIR.'/demo.sqlite');

    $providers = array_values(array_filter(TestCase::PROVIDERS, class_exists(...)));

    return Application::create(
        basePath: null,
        options: ['extra' => ['providers' => [DemoConfig::class, ...$providers, FilamentSecurityAlertsServiceProvider::class, HalyardPanelProvider::class]]],
    );
}

function demo_seed(LaravelApplication $app): void
{
    Schema::dropAllTables();
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
    $app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);

    $people = [];
    foreach (['Maya Okafor', 'Tom Reyes', 'Inès Laurent', 'Kenji Mori'] as $name) {
        $first = (string) strtok($name, ' ');
        $people[$first] = DemoUser::forceCreate([
            'name' => $name,
            'email' => strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $first)).'@halyard.test',
            'password' => bin2hex(random_bytes(16)),
        ]);
    }

    // The check the scheduler ran at seven on the morning of 12 October 2026.
    $fixture = fn (string $name): array => json_decode((string) file_get_contents(FIXTURES.'/'.$name), true, 512, JSON_THROW_ON_ERROR);
    // One set of answers, which turns empty once everything is patched.
    $patched = false;
    // The package's HTTP goes through PSR-18; send it through Http::fake() instead of the network.
    app()->instance(ClientInterface::class, new HttpFacadeClient);
    Http::fake([
        'packagist.org/api/security-advisories/*' => function () use ($fixture, &$patched) {
            return Http::response($patched ? ['advisories' => []] : $fixture('packagist.json'));
        },
        'api.osv.dev/v1/querybatch' => function (Request $request) use ($fixture, &$patched) {
            return Http::response($patched ? ['results' => array_map(fn (): object => (object) [], $request['queries'])] : $fixture('osv-querybatch.json'));
        },
        'api.osv.dev/v1/vulns/*' => fn (Request $request) => Http::response($fixture('osv/'.basename($request->url()).'.json')),
        'www.cisa.gov/*' => Http::response($fixture('kev.json')),
    ]);
    Carbon::setTestNow(Carbon::parse(DEMO_NOW_AT)->setTime(7, 0));
    SecurityAlerts::check(notify: false);

    if (CLEAR) {
        // Everything patched, and the next morning's check noticed.
        $patched = true;
        SecurityAlerts::check(fresh: true, notify: false);
        Carbon::setTestNow();

        $app->make(ConsoleKernel::class)->call('filament:assets');

        return;
    }

    // Most of these were found on earlier mornings; only a few are new today.
    $ages = [0 => '-9 days', 1 => '-3 days', 2 => '-21 days', 3 => '-34 days', 4 => '-12 days', 5 => '-6 days', 7 => '-48 days', 8 => '-2 days', 10 => '-60 days'];
    foreach (SecurityAlert::query()->open()->worstFirst()->get() as $i => $alert) {
        if (isset($ages[$i])) {
            $alert->forceFill(['first_seen_at' => Carbon::parse(DEMO_NOW_AT)->modify($ages[$i])])->save();
        }
    }
    Carbon::setTestNow();

    $app->make(ConsoleKernel::class)->call('filament:assets');
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? null) !== 'seed') {
        fwrite(STDERR, "Usage: php demo/app.php seed\n");
        exit(1);
    }

    @unlink(DEMO_DIR.'/demo.sqlite');
    $app = demo_app();
    demo_seed($app);

    fwrite(STDOUT, sprintf("Seeded %d open alerts into build/demo\n", SecurityAlert::query()->open()->count()));
}
