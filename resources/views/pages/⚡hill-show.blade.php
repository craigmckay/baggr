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
    $place = $hill->region ?? (config('dobih.countries')[$hill->country] ?? null);
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
        @if ($hill->smc_section)
            <div>
                <dt class="text-zinc-500">{{ __('SMC section') }}</dt>
                <dd class="font-medium">{{ $hill->smc_section }} &ndash; {{ $hill->smc_section_description }}</dd>
            </div>
        @endif
        @if ($hill->rhb_section)
            <div class="col-span-2 sm:col-span-3">
                <dt class="text-zinc-500">{{ __('RHB region classification') }}</dt>
                <dd class="font-medium">{{ $hill->rhb_section }} &ndash; {{ $hill->rhb_section_description }}</dd>
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
