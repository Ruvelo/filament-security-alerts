<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Ruvelo\FilamentSecurityAlerts\Pages\SecurityAlertsPage;
use Ruvelo\FilamentSecurityAlerts\Support\Summary;

/**
 * Open alerts by severity and how many are exploited in the wild, red when
 * any is, plus when the last check ran. Reads the database and the cache;
 * never a feed.
 */
class SecurityAlertsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Two cheap reads; no reason to flash a placeholder first.
    protected static bool $isLazy = false;

    protected ?string $heading = 'Security alerts';

    protected ?string $description = null;

    protected int|array|null $columns = 3;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return SecurityAlertsPage::canAccess();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = SecurityAlertsPage::summary();
        $page = SecurityAlertsPage::getUrl();

        return [
            $this->exploited($summary, $page),
            $this->severity('Critical', $summary->critical, 'danger', $page, 'critical'),
            $this->severity('High', $summary->high, 'warning', $page, 'high'),
            $this->severity('Medium', $summary->medium, 'info', $page, 'medium'),
            $this->severity('Low', $summary->low, 'gray', $page, 'low', 'unknown'),
            $this->lastChecked($summary, $page),
        ];
    }

    private function exploited(Summary $summary, string $page): Stat
    {
        $stat = Stat::make('Exploited in the wild', $summary->exploited)
            ->url($page.'?'.http_build_query(['tableFilters' => ['exploited' => ['isActive' => true]]]));

        if ($summary->exploited === 0) {
            return $stat->description($summary->isClear() ? 'All clear' : 'Nothing under attack')
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success');
        }

        $worst = $summary->worstExploited;

        return $stat
            ->description($worst === null ? 'Patch today' : $worst->package.' '.$worst->version.' · patch today')
            ->descriptionIcon(Heroicon::Fire)
            ->color('danger')
            ->extraAttributes(['class' => 'fi-sa-exploited']);
    }

    private function severity(string $label, int $count, string $color, string $page, string ...$values): Stat
    {
        return Stat::make($label, $count)
            ->color($count > 0 ? $color : 'gray')
            ->url($page.'?'.http_build_query(['tableFilters' => ['severity' => ['values' => array_values($values)]]]));
    }

    private function lastChecked(Summary $summary, string $page): Stat
    {
        $stat = Stat::make('Last checked', $summary->checkedAt?->diffForHumans(short: true, parts: 1) ?? 'Never')->url($page);

        if ($summary->isStale()) {
            return $stat
                ->description($summary->neverChecked() ? 'Run a check, or start the scheduler' : 'Over '.Summary::noun('hour', $summary->staleAfterHours).' ago: is the scheduler running?')
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->color('warning');
        }

        return $stat
            ->description($summary->packages !== null ? Summary::noun('package', $summary->packages).' checked' : 'Scheduled daily')
            ->descriptionIcon($summary->complete ? Heroicon::CheckCircle : Heroicon::ExclamationTriangle)
            ->color($summary->complete ? 'gray' : 'warning');
    }
}
