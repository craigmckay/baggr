@props(['forecast'])

{{-- Go, Marginal or Poor for a Scottish area. Other areas have no rating, so nothing shows. --}}
@if ($forecast->isRatedArea())
    @if ($rating = $forecast->rating())
        <flux:badge size="sm" :color="$rating->colour()" {{ $attributes }} data-test="weather-rating">{{ $rating->label() }}</flux:badge>
    @else
        <span {{ $attributes->class('text-xs text-zinc-500') }}>{{ __('See MWIS') }}</span>
    @endif
@endif
