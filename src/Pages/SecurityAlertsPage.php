<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Pages;

use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Ruvelo\FilamentSecurityAlerts\SecurityAlertsPlugin;
use Ruvelo\FilamentSecurityAlerts\Support\Summary;
use Ruvelo\SecurityAdvisories\Severity;
use Ruvelo\SecurityAlerts\CheckResult;
use Ruvelo\SecurityAlerts\Ecosystem;
use Ruvelo\SecurityAlerts\Exceptions\NotInstalled;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;
use Ruvelo\SecurityAlerts\SecurityAlerts;
use UnitEnum;

/**
 * The open alerts from the last check, exploited first, with a button to
 * check again. Reads the package's table; only "Check now" calls a feed.
 */
class SecurityAlertsPage extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * In the address, so the widget can link to a filtered list.
     *
     * @var array<string, mixed>|null
     */
    #[Url]
    public ?array $tableFilters = null;

    public static function getSlug(?Panel $panel = null): string
    {
        $panel ??= Filament::getCurrentOrDefaultPanel();
        $plugin = $panel?->hasPlugin(SecurityAlertsPlugin::ID) ? $panel->getPlugin(SecurityAlertsPlugin::ID) : null;

        return ($plugin instanceof SecurityAlertsPlugin ? $plugin : SecurityAlertsPlugin::make())->getSlug();
    }

    public static function canAccess(): bool
    {
        return SecurityAlertsPlugin::get()->userCanAccess();
    }

    public static function getNavigationLabel(): string
    {
        return SecurityAlertsPlugin::get()->getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return SecurityAlertsPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return SecurityAlertsPlugin::get()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return SecurityAlertsPlugin::get()->getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! SecurityAlertsPlugin::get()->hasNavigationBadge()) {
            return null;
        }

        $urgent = self::summary()->urgent;

        return $urgent > 0 ? (string) $urgent : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return self::summary()->exploited > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $summary = self::summary();

        if ($summary->urgent === 0) {
            return null;
        }

        return match (true) {
            $summary->critical === 0 => $summary->exploited.' exploited in the wild',
            $summary->exploited === 0 => $summary->critical.' critical',
            default => $summary->critical.' critical, '.$summary->exploited.' exploited in the wild',
        };
    }

    public function getTitle(): string
    {
        return SecurityAlertsPlugin::get()->getNavigationLabel();
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public static function summary(): Summary
    {
        return Summary::current(SecurityAlertsPlugin::get()->getStaleAfterHours());
    }

    public function content(Schema $schema): Schema
    {
        $summary = self::summary();

        return $schema->components([
            View::make('filament-security-alerts::status')->viewData(fn (): array => ['summary' => self::summary()]),
            // Without the migration there is no table to query.
            ...($summary->installed ? [EmbeddedTable::make()] : []),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('check')
                ->label('Check now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => SecurityAlertsPlugin::get()->userCanCheck())
                ->action($this->checkNow(...)),
        ];
    }

    /**
     * Runs a check with fresh feeds, as the scheduler would, and says what
     * it found. Nothing is emailed unless the plugin was told to.
     */
    public function checkNow(): void
    {
        $plugin = SecurityAlertsPlugin::get();

        abort_unless($plugin->userCanCheck(), 403);

        try {
            $result = SecurityAlerts::check(fresh: true, notify: $plugin->notifiesOnCheck());
        } catch (NotInstalled $e) {
            Notification::make()->danger()->persistent()
                ->title('Security alerts isn’t set up yet')
                ->body('Run php artisan migrate, then check again.')
                ->send();

            return;
        }

        Summary::rememberSource($result->report);

        self::announce($result)->send();
    }

    public static function announce(CheckResult $result): Notification
    {
        $report = $result->report;
        $counts = $report->counts();
        $open = SecurityAlerts::open()->count();
        $changes = array_filter([
            $result->found->isNotEmpty() ? $result->found->count().' new' : null,
            $result->resolved->isNotEmpty() ? $result->resolved->count().' fixed' : null,
        ]);

        if (! $report->isComplete()) {
            return Notification::make()->warning()->persistent()
                ->title('Check incomplete')
                ->body(implode(' ', $report->problems).' Nothing was marked as fixed.');
        }

        if ($open === 0) {
            return Notification::make()->success()
                ->title('All clear')
                ->body('No known vulnerabilities in '.Summary::noun('package', $report->packageCount()).($changes === [] ? '.' : ': '.implode(', ', $changes).'.'));
        }

        $body = Summary::noun('open alert', $open).($changes === [] ? '' : ': '.implode(', ', $changes));
        $notification = Notification::make()->title(($counts['exploited'] ?? 0) > 0 ? 'Check complete: exploited in the wild' : 'Check complete')->body($body.'.');

        return ($counts['exploited'] ?? 0) > 0 ? $notification->danger() : $notification->warning();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SecurityAlert::query()->open()->worstFirst())
            ->defaultSort(null)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('exploited_at')
                    ->label('Exploited')
                    ->badge()
                    ->state(fn (SecurityAlert $record): ?string => $record->isExploited() ? 'Exploited' : null)
                    ->color('danger')
                    ->icon(Heroicon::Fire)
                    ->placeholder('')
                    ->description(fn (SecurityAlert $record): ?string => $this->exploitedNote($record)),
                TextColumn::make('severity')
                    ->badge()
                    ->formatStateUsing(fn (Severity $state): string => $state->label())
                    ->color(fn (Severity $state): string => match ($state) {
                        Severity::Critical => 'danger',
                        Severity::High => 'warning',
                        Severity::Medium => 'info',
                        Severity::Low, Severity::NotRated => 'gray',
                    }),
                TextColumn::make('package')
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (SecurityAlert $record): string => $record->ecosystem->label().($record->dev ? ' · dev' : '')),
                TextColumn::make('title')
                    ->label('Advisory')
                    ->wrap()
                    ->lineClamp(2)
                    ->searchable(['title', 'cve', 'advisory_id'])
                    ->description(fn (SecurityAlert $record): string => $record->cve ?? $record->advisory_id)
                    ->url(fn (SecurityAlert $record): ?string => self::safeUrl($record->link), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->tooltip(fn (SecurityAlert $record): ?string => self::safeUrl($record->link) === null ? null : 'Read the advisory'),
                TextColumn::make('version')
                    ->label('Installed')
                    ->fontFamily('mono'),
                TextColumn::make('fixed_in')
                    ->label('Fixed in')
                    ->fontFamily('mono')
                    ->badge()
                    ->color(fn (?string $state): string => $state === null ? 'gray' : 'success')
                    ->placeholder('No fix yet')
                    ->tooltip(fn (SecurityAlert $record): ?string => $record->fixed_in === null ? null : 'Affected: '.$record->affected_versions),
                TextColumn::make('first_seen_at')
                    ->label('First seen')
                    ->visibleFrom('lg')
                    ->since()
                    ->dateTimeTooltip()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->multiple()
                    ->options(collect(Severity::cases())->mapWithKeys(fn (Severity $severity): array => [$severity->value => $severity->label()])->all()),
                SelectFilter::make('ecosystem')
                    ->options(collect(Ecosystem::cases())->mapWithKeys(fn (Ecosystem $ecosystem): array => [$ecosystem->value => $ecosystem->label()])->all()),
                Filter::make('exploited')
                    ->label('Exploited in the wild')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('exploited_at')),
            ])
            ->emptyState(fn (): ?ViewContract => self::summary()->isClear()
                ? view('filament-security-alerts::all-clear', ['summary' => self::summary()])
                : null)
            ->emptyStateHeading('Nothing matches')
            ->emptyStateDescription('Try a different search or clear the filters.')
            ->emptyStateIcon(Heroicon::OutlinedFunnel);
    }

    /**
     * The catalogue's dates, in words: "Listed 20 Mar 2026 · patch by 3 Apr 2026".
     */
    private function exploitedNote(SecurityAlert $record): ?string
    {
        if (! $record->isExploited()) {
            return null;
        }

        return implode(' · ', array_filter([
            'Listed '.self::day($record->exploited_at),
            $record->kev_due_date !== null ? 'due '.self::day($record->kev_due_date) : null,
            $record->ransomware ? 'ransomware' : null,
        ]));
    }

    /**
     * "20 Mar", or "20 Mar 2025" when it isn't this year.
     */
    private static function day(?CarbonInterface $date): string
    {
        return $date === null ? '' : $date->format($date->isCurrentYear() ? 'j M' : 'j M Y');
    }

    /**
     * Advisory links come from feeds: only web links become links.
     */
    public static function safeUrl(?string $url): ?string
    {
        return $url !== null && preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
