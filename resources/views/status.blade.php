@php
    /** @var \Ruvelo\FilamentSecurityAlerts\Support\Summary $summary */
@endphp

<div class="fi-sa-status" role="status">
    @if (! $summary->installed)
        <x-filament::badge color="danger" :icon="\Filament\Support\Icons\Heroicon::ExclamationCircle">Not set up</x-filament::badge>
        <span>The alerts table is missing. Run <code>php artisan migrate</code>, then check.</span>
    @elseif ($summary->neverChecked())
        <x-filament::badge color="warning" :icon="\Filament\Support\Icons\Heroicon::ExclamationTriangle">Not checked yet</x-filament::badge>
        <span>Click “Check now”, or make sure the Laravel scheduler is running: the check runs daily.</span>
    @elseif ($summary->isStale())
        <x-filament::badge color="warning" :icon="\Filament\Support\Icons\Heroicon::ExclamationTriangle">Out of date</x-filament::badge>
        <span>
            Last checked <time datetime="{{ $summary->checkedAt?->toIso8601String() }}" title="{{ $summary->checkedAt?->toDayDateTimeString() }}">{{ $summary->checkedAt?->diffForHumans() }}</time>.
            Is the Laravel scheduler running?
        </span>
    @else
        <x-filament::badge color="gray" :icon="\Filament\Support\Icons\Heroicon::Clock">Checked</x-filament::badge>
        <span>
            <time datetime="{{ $summary->checkedAt?->toIso8601String() }}" title="{{ $summary->checkedAt?->toDayDateTimeString() }}">{{ $summary->checkedAt?->diffForHumans() }}</time>@if ($summary->packages !== null), {{ \Ruvelo\FilamentSecurityAlerts\Support\Summary::noun('package', $summary->packages) }} looked at @endif@if ($summary->source !== null) · via {{ $summary->source }}@endif
        </span>
    @endif

    @if ($summary->installed && ! $summary->complete && ! $summary->neverChecked())
        <x-filament::badge color="warning" :icon="\Filament\Support\Icons\Heroicon::ExclamationTriangle">Incomplete</x-filament::badge>
        <span>A lockfile or advisory source couldn’t be read. Nothing was marked as fixed.</span>
    @endif
</div>
