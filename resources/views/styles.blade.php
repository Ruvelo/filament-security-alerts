<style>
    /* ruvelo/filament-security-alerts: only what Filament's theme has no class for. */
    .fi-sa-status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; font-size: .875rem; color: var(--gray-600); }
    .fi-sa-status code { font-size: .8125rem; padding: .0625rem .375rem; border-radius: .375rem; background: var(--gray-100); }
    .dark .fi-sa-status { color: var(--gray-400); }
    .dark .fi-sa-status code { background: color-mix(in oklab, var(--gray-500) 20%, transparent); }

    /* The all-clear state is green: good news should look it. */
    .fi-sa-clear .fi-ta-empty-state-icon-bg { background: color-mix(in oklab, var(--success-500) 14%, transparent); }
    .fi-sa-clear .fi-ta-empty-state-icon-bg .fi-icon { color: var(--success-600); }
    .dark .fi-sa-clear .fi-ta-empty-state-icon-bg .fi-icon { color: var(--success-400); }

    /* Anything exploited turns its stat red, not just its small print. */
    .fi-sa-exploited { background: color-mix(in oklab, var(--danger-500) 8%, white); --tw-ring-color: color-mix(in oklab, var(--danger-500) 45%, transparent); }
    .fi-sa-exploited .fi-wi-stats-overview-stat-value { color: var(--danger-600); }
    .dark .fi-sa-exploited { background: color-mix(in oklab, var(--danger-500) 14%, transparent); --tw-ring-color: color-mix(in oklab, var(--danger-400) 45%, transparent); }
    .dark .fi-sa-exploited .fi-wi-stats-overview-stat-value { color: var(--danger-400); }
</style>
