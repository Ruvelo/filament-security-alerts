@php
    /** @var \Ruvelo\FilamentSecurityAlerts\Support\Summary $summary */
@endphp

<div class="fi-ta-empty-state fi-sa-clear" role="status">
    <div class="fi-ta-empty-state-content">
        <div class="fi-ta-empty-state-icon-bg">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedShieldCheck" class="fi-icon" />
        </div>

        @if ($summary->neverChecked())
            <h4 class="fi-ta-empty-state-heading">Nothing found yet</h4>
            <p class="fi-ta-empty-state-description">No check has run. Click “Check now” to look at the packages this app runs.</p>
        @else
            <h4 class="fi-ta-empty-state-heading">All clear</h4>
            <p class="fi-ta-empty-state-description">
                @if ($summary->packages !== null)
                    None of the {{ number_format($summary->packages) }} packages this app runs has a known vulnerability.
                @else
                    None of the packages this app runs has a known vulnerability.
                @endif
                Checked {{ $summary->checkedAt?->diffForHumans() }}.
            </p>
        @endif
    </div>
</div>
