@props(['forecasts'])

{{-- Which day the forecasts on a page are for. Amber when it stands in for a Saturday not yet covered. --}}
@php
    $day = $forecasts->day();
    $saturday = $forecasts->saturday();
    $other = ! $day->isSameDay($saturday);
@endphp

<div {{ $attributes->class(['text-sm', 'rounded-lg border border-amber-300 bg-amber-50 p-2 dark:border-amber-700 dark:bg-amber-950/40' => $other]) }} data-test="forecast-day">
    <flux:text>{{ __('MWIS forecast for :day', ['day' => $day->format('l j F')]) }}</flux:text>

    @if ($other)
        <p class="font-medium text-amber-700 dark:text-amber-300">
            {{ __('Not Saturday :date: that isn\'t covered yet.', ['date' => $saturday->format('j F')]) }}
        </p>
    @endif
</div>
