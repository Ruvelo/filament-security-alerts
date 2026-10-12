<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Ruvelo\FilamentSecurityAlerts\Pages\SecurityAlertsPage;
use Ruvelo\FilamentSecurityAlerts\Widgets\SecurityAlertsOverview;
use UnitEnum;

/**
 * Brings ruvelo/laravel-security-alerts into a Filament panel.
 *
 *     $panel->plugin(SecurityAlertsPlugin::make()->navigationGroup('System'));
 */
class SecurityAlertsPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'ruvelo-security-alerts';

    /** The gate that decides who sees the page, when the app defines it. */
    public const VIEW_GATE = 'view-security-alerts';

    /** The gate that decides who may run a check, when the app defines it. */
    public const CHECK_GATE = 'check-security-alerts';

    protected string|UnitEnum|Closure|null $navigationGroup = null;

    protected string|Closure $navigationLabel = 'Security alerts';

    protected string|BackedEnum|Htmlable|Closure|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected int|Closure|null $navigationSort = null;

    protected bool|Closure $hasNavigationBadge = true;

    protected string $slug = 'security-alerts';

    protected bool|Closure|null $canAccess = null;

    protected bool|Closure|null $canCheck = null;

    protected bool $hasWidget = true;

    protected bool $notifiesOnCheck = false;

    protected int $staleAfterHours = 48;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin registered on the current panel, or one with the defaults.
     */
    public static function get(): static
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel !== null && $panel->hasPlugin(self::ID)) {
            /** @var static */
            return $panel->getPlugin(self::ID);
        }

        return static::make();
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([SecurityAlertsPage::class]);

        if ($this->hasWidget) {
            $panel->widgets([SecurityAlertsOverview::class]);
        }

        // A few rules for what Filament's theme doesn't style. Scoped to
        // fi-sa-*, built on the theme's own colour variables.
        $panel->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => view('filament-security-alerts::styles')->render());
    }

    public function boot(Panel $panel): void {}

    public function navigationGroup(string|UnitEnum|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->evaluate($this->navigationGroup);
    }

    public function navigationLabel(string|Closure $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return (string) $this->evaluate($this->navigationLabel);
    }

    public function navigationIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->navigationIcon);
    }

    public function navigationSort(int|Closure|null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->evaluate($this->navigationSort);
    }

    /**
     * The count of exploited and critical alerts beside the navigation item.
     */
    public function navigationBadge(bool|Closure $condition = true): static
    {
        $this->hasNavigationBadge = $condition;

        return $this;
    }

    public function hasNavigationBadge(): bool
    {
        return (bool) $this->evaluate($this->hasNavigationBadge);
    }

    /**
     * The URL segment the page lives under in the panel: /admin/{slug}.
     */
    public function slug(string $slug): static
    {
        $this->slug = trim($slug, '/');

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Who may see the page, the widget and the badge. A closure receives the
     * panel user as `$user`. Defaults to the `view-security-alerts` gate when
     * the app defines it, and to everyone who can use the panel when it
     * doesn't.
     */
    public function canAccess(bool|Closure|null $condition): static
    {
        $this->canAccess = $condition;

        return $this;
    }

    public function userCanAccess(?Authenticatable $user = null): bool
    {
        return $this->decide($this->canAccess, self::VIEW_GATE, $user);
    }

    /**
     * Who may run a check from the panel. Defaults to the
     * `check-security-alerts` gate when the app defines it, and to everyone
     * who can see the page when it doesn't.
     */
    public function canCheck(bool|Closure|null $condition): static
    {
        $this->canCheck = $condition;

        return $this;
    }

    public function userCanCheck(?Authenticatable $user = null): bool
    {
        $user ??= Filament::auth()->user();

        return $this->userCanAccess($user) && $this->decide($this->canCheck, self::CHECK_GATE, $user);
    }

    /**
     * Also send the alert email or Slack message for what a manual check
     * finds, as the scheduled check does. Off by default: anything not sent
     * waits for the next scheduled check, so nobody gets a surprise email
     * from someone clicking a button.
     */
    public function notifyOnCheck(bool $condition = true): static
    {
        $this->notifiesOnCheck = $condition;

        return $this;
    }

    public function notifiesOnCheck(): bool
    {
        return $this->notifiesOnCheck;
    }

    /**
     * How long without a check before the widget and page warn that it
     * isn't running. 48 hours by default: a daily check missed twice.
     */
    public function staleAfterHours(int $hours): static
    {
        $this->staleAfterHours = max(1, $hours);

        return $this;
    }

    public function getStaleAfterHours(): int
    {
        return $this->staleAfterHours;
    }

    public function widget(bool $condition = true): static
    {
        $this->hasWidget = $condition;

        return $this;
    }

    /**
     * @param  (Closure|bool|null)  $configured
     */
    protected function decide(Closure|bool|null $configured, string $gate, ?Authenticatable $user): bool
    {
        $user ??= Filament::auth()->user();

        if ($user === null) {
            return false;
        }

        if ($configured !== null) {
            return (bool) $this->evaluate($configured, ['user' => $user], [Authenticatable::class => $user]);
        }

        // No gate defined: whoever can use the panel. Defining the gate
        // narrows it, as for any Laravel ability.
        return ! Gate::has($gate) || Gate::forUser($user)->allows($gate);
    }
}
