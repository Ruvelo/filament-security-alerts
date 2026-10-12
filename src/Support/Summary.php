<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Ruvelo\SecurityAlerts\Models\SecurityAlert;
use Ruvelo\SecurityAlerts\SecurityAlerts;
use Ruvelo\SecurityAlerts\Severity;

/**
 * What the badge, the widget and the page's status line say, worked out once
 * from the package's open alerts and its last-check record. Reads the
 * database and the cache only; never a feed.
 *
 * Severities count every open alert with that severity, exploited or not, so
 * they match what the page's filters show. Exploited is a count of its own.
 */
final readonly class Summary
{
    public function __construct(
        public int $exploited = 0,
        public int $critical = 0,
        public int $high = 0,
        public int $medium = 0,
        public int $low = 0,
        public int $total = 0,
        public int $urgent = 0,
        public ?SecurityAlert $worstExploited = null,
        public ?CarbonImmutable $checkedAt = null,
        public ?int $packages = null,
        public bool $complete = true,
        public bool $installed = true,
        public int $staleAfterHours = 48,
    ) {}

    public static function current(int $staleAfterHours = 48): self
    {
        try {
            $open = SecurityAlerts::open();
        } catch (QueryException) {
            // The package's migration hasn't run. Say so on the page instead
            // of taking the whole panel down with a navigation badge.
            return new self(installed: false, staleAfterHours: $staleAfterHours);
        }

        $last = SecurityAlerts::lastCheck();
        $counts = ['exploited' => 0, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'urgent' => 0];
        $worst = null;

        foreach ($open as $alert) {
            $counts[match ($alert->severity) {
                Severity::Critical => 'critical',
                Severity::High => 'high',
                Severity::Medium => 'medium',
                Severity::Low, Severity::Unknown => 'low',
            }]++;

            if ($alert->isExploited()) {
                $counts['exploited']++;
                $worst ??= $alert;
            }

            if ($alert->isExploited() || $alert->severity === Severity::Critical) {
                $counts['urgent']++;
            }
        }

        return new self(
            exploited: $counts['exploited'],
            critical: $counts['critical'],
            high: $counts['high'],
            medium: $counts['medium'],
            low: $counts['low'],
            total: $open->count(),
            urgent: $counts['urgent'],
            worstExploited: $worst,
            checkedAt: $last['checked_at'] ?? null,
            packages: ($last['packages'] ?? 0) > 0 ? $last['packages'] : null,
            complete: $last['complete'] ?? true,
            staleAfterHours: $staleAfterHours,
        );
    }

    /**
     * "1 package", "105 packages". Str::plural() only prepends the count from
     * Laravel 12.8 on, and this supports 12.4.
     */
    public static function noun(string $word, int $count): string
    {
        return $count.' '.Str::plural($word, $count);
    }

    public function isClear(): bool
    {
        return $this->total === 0;
    }

    public function neverChecked(): bool
    {
        return $this->checkedAt === null;
    }

    /**
     * No check for longer than the limit, or none on record. Usually the
     * scheduler has stopped.
     */
    public function isStale(): bool
    {
        return $this->checkedAt === null || $this->checkedAt->lt(now()->subHours($this->staleAfterHours));
    }
}
