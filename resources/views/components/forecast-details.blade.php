@props(['forecast', 'forecasts', 'url', 'compact' => false])

{{--
    The extracts Baggr keeps from one day's forecast, always with a link to the whole thing.
    Compact gives the figures, with MWIS's own sentences behind "More detail".
--}}
@php
    $full = [__('Wind') => $forecast->wind, __('How wet') => $forecast->rain, __('Cloud-free') => $forecast->cloud_free, __('Freezing level') => $forecast->freezing_level];
@endphp

<div {{ $attributes->class('space-y-2 text-sm') }}>
    @if ($forecasts->isOld($forecast))
        <p class="font-medium text-amber-600 dark:text-amber-400">{{ __('This forecast is out of date: it couldn\'t be refreshed.') }}</p>
    @endif

    @if ($forecasts->isForAnotherDay($forecast))
        <p class="font-medium text-amber-700 dark:text-amber-300" data-test="forecast-date">
            {{ __('Forecast date: :day', ['day' => $forecast->date->format('l j F')]) }}
        </p>
    @endif

    @if ($forecast->headline)
        <p class="font-medium">{{ $forecast->headline }}</p>
    @endif

    @if ($compact)
        <p data-test="forecast-figures">
            @if ($forecast->windRange())
                {{ __('Wind :range', ['range' => $forecast->windRange()]) }}
            @endif
            @if ($forecast->windRange() && $forecast->cloud_free_percent !== null)
                &middot;
            @endif
            @if ($forecast->cloud_free_percent !== null)
                {{ __('cloud-free summits up to :percent%', ['percent' => $forecast->cloud_free_percent]) }}
            @endif
        </p>

        @if ($forecast->rain)
            <p>{{ $forecast->rain }}</p>
        @endif

        <details>
            <summary class="cursor-pointer text-zinc-500 underline">{{ __('More detail') }}</summary>
            <dl class="mt-2 grid grid-cols-[6.5rem_1fr] gap-x-3 gap-y-1">
                @foreach (array_filter($full) as $label => $value)
                    <dt class="text-zinc-500">{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                @endforeach
            </dl>
        </details>
    @else
        <dl class="grid grid-cols-[6.5rem_1fr] gap-x-3 gap-y-1">
            @foreach (array_filter($full) as $label => $value)
                <dt class="text-zinc-500">{{ $label }}</dt>
                <dd>
                    {{-- The speeds picked out, above MWIS's own sentence. --}}
                    @if ($label === __('Wind') && $forecast->windRange())
                        <span class="block font-medium" data-test="wind-range">{{ $forecast->windRange() }}</span>
                    @endif
                    {{ $value }}
                </dd>
            @endforeach
        </dl>
    @endif

    <p class="text-xs text-zinc-500">
        <a href="{{ $url }}" target="_blank" rel="noopener" class="underline">{{ __('Full MWIS forecast') }}</a>
        @if ($forecast->issued_at)
            &middot; {{ __('issued :when', ['when' => $forecast->issued_at->timezone(config('mwis.timezone'))->format('D j M, g:ia')]) }}
        @endif
    </p>
</div>
