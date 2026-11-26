<?php

use App\Models\Hill;
use Livewire\Component;

new class extends Component
{
    public Hill $hill;

    public function mount(Hill $hill): void
    {
        $this->hill = $hill;
    }

    public function rendering($view): void
    {
        $view->title($this->hill->name);
    }
};
?>

@php
    $lists = $hill->classes()->where('show_in_list', true)->orderBy('sort_order')->pluck('name');
    $areaTypes = config('dobih.area_types');
    $areas = $hill->areas()->orderBy('areas.name')->get()
        ->groupBy('type')
        ->sortBy(fn ($group, $type) => array_search($type, array_keys($areaTypes), true));
    $maps = $hill->maps()->orderBy('type')->orderBy('ref')->get()->groupBy('type');
    $place = config('dobih.countries')[$hill->country] ?? null;
    // The sub-section is one of the hill's areas, listed below; the section above it is worked out here.
    $murraySection = \App\Enums\HillGrouping::MurraySection->sectionFor($hill);
    $mwis = $hill->mwisArea();

    // The hill pages are public, so logging and ascents only appear for someone who can use them.
    $viewer = auth()->user();
    $canLog = $viewer !== null && $viewer->hasVerifiedEmail();
    $ascents = $canLog
        ? $viewer->ascents()->where('hill_id', $hill->id)->with('walkDay.walk.days.hills')->get()->sortBy(fn ($ascent) => $ascent->walkDay->date)->values()
        : collect();
@endphp

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $hill->name }}</flux:heading>
            <flux:subheading>{{ collect([$hill->height_m.'m', $place])->filter()->implode(' · ') }}</flux:subheading>
        </div>

        <flux:button
            icon="x-mark"
            href="{{ url()->previous(route('hills.index')) }}"
            wire:navigate
            variant="ghost"
            aria-label="{{ __('Back to hills') }}"
        />
    </div>

    @if ($hill->photo_url)
        <figure class="space-y-2">
            @if ($hill->photo_page_url)
                <a href="{{ $hill->photo_page_url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <img src="{{ $hill->photo_url }}" alt="{{ $hill->photo_title ?: $hill->name }}" class="w-full object-cover" />
                </a>
            @else
                <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <img src="{{ $hill->photo_url }}" alt="{{ $hill->photo_title ?: $hill->name }}" class="w-full object-cover" />
                </div>
            @endif

            @if ($hill->photo_author)
                <figcaption class="text-xs text-zinc-500">
                    &copy; {{ $hill->photo_author }},
                    <a href="https://creativecommons.org/licenses/by-sa/2.0/" target="_blank" rel="license noopener" class="underline">CC BY-SA 2.0</a>
                </figcaption>
            @endif
        </figure>
    @endif

    @if ($hill->walkhighlands_url || $hill->mwis_forecast_url)
        <div class="flex flex-wrap items-center gap-4">
            @if ($hill->walkhighlands_url)
                <flux:link href="{{ $hill->walkhighlands_url }}" target="_blank" rel="noopener">
                    {{ __('View route on Walkhighlands') }}
                </flux:link>
            @endif

            @if ($hill->mwis_forecast_url)
                <a href="{{ $hill->mwis_forecast_url }}" target="_blank" rel="noopener" class="inline-flex items-center">
                    <img src="{{ asset('images/mwis-logo.png') }}" alt="{{ __('MWIS mountain weather forecast') }}" class="h-8" />
                </a>
            @endif
        </div>
    @endif

    @if ($canLog)
        <div class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="my-ascents">
            <div class="min-w-0 space-y-2">
                <flux:text>
                    @if ($ascents->isEmpty())
                        {{ __('You haven\'t logged this hill yet.') }}
                    @else
                        {{ trans_choice('You have climbed this once|You have climbed this :count times', $ascents->count()) }}:
                    @endif
                </flux:text>

                @if ($ascents->isNotEmpty())
                    <ul class="space-y-1">
                        @foreach ($ascents as $ascent)
                            <li class="flex gap-3 text-sm" data-test="my-ascent">
                                <span class="w-24 shrink-0 text-zinc-500 tabular-nums">{{ $ascent->walkDay->date?->format('j M Y') }}</span>
                                <flux:link :href="route('walks.show', $ascent->walkDay->walk)" wire:navigate class="min-w-0">{{ $ascent->walkDay->walk->displayName() }}</flux:link>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <flux:button size="sm" variant="primary" :href="route('walks.log', ['hill' => $hill->slug])" wire:navigate class="shrink-0" data-test="log-this-hill">
                {{ __('Log this hill') }}
            </flux:button>
        </div>
    @endif

    <flux:separator />

    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
        @if ($hill->grid_ref)
            <div>
                <dt class="text-zinc-500">{{ __('Grid reference') }}</dt>
                <dd class="font-medium">{{ $hill->grid_ref }}</dd>
            </div>
        @endif
        @if ($hill->latitude !== null && $hill->longitude !== null)
            <div>
                <dt class="text-zinc-500">{{ __('Coordinates') }}</dt>
                <dd class="font-medium">{{ $hill->latitude }}, {{ $hill->longitude }}</dd>
            </div>
        @endif
        @if ($hill->drop_m !== null)
            <div>
                <dt class="text-zinc-500">{{ __('Drop') }}</dt>
                <dd class="font-medium">{{ number_format($hill->drop_m, 1) }}m</dd>
            </div>
        @endif
        @if ($mwis)
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-zinc-500">{{ __('MWIS area') }}</dt>
                <dd class="font-medium">
                    <a href="{{ $mwis['url'] }}" target="_blank" rel="noopener" class="underline" data-test="mwis-area">{{ $mwis['name'] }}</a>
                </dd>
            </div>
        @endif
        @if ($murraySection['key'] !== 'none')
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-zinc-500">{{ __('Murray section') }}</dt>
                <dd class="font-medium">{{ $murraySection['label'] }}</dd>
            </div>
        @endif
        @if ($lists->isNotEmpty())
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-zinc-500">{{ __('Lists') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                    @foreach ($lists as $list)
                        <flux:badge size="sm">{{ $list }}</flux:badge>
                    @endforeach
                </dd>
            </div>
        @endif
        @foreach ($areas as $type => $group)
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-zinc-500">{{ $areaTypes[$type] ?? $areaTypes['other'] }}</dt>
                <dd class="font-medium">{{ $group->pluck('name')->implode(', ') }}</dd>
            </div>
        @endforeach
        @foreach ($maps as $type => $sheets)
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-zinc-500">{{ config('dobih.map_types')[$type] ?? $type }}</dt>
                <dd class="font-medium">{{ $sheets->pluck('ref')->implode(', ') }}</dd>
            </div>
        @endforeach
    </dl>

    <x-dobih-credit class="text-zinc-500" />
</div>
