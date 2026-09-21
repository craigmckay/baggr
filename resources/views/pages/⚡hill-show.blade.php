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

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $hill->name }}</flux:heading>
            <flux:subheading>{{ $hill->height_m }}m &middot; {{ $hill->region }}</flux:subheading>
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
        <a href="{{ $hill->walkhighlands_url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <img src="{{ $hill->photo_url }}" alt="{{ $hill->name }}" class="w-full object-cover" />
        </a>
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <flux:link href="{{ $hill->walkhighlands_url }}" target="_blank" rel="noopener">
            {{ __('View route on Walkhighlands') }}
        </flux:link>

        <a href="{{ $hill->mwis_forecast_url }}" target="_blank" rel="noopener" class="inline-flex items-center">
            <img src="{{ asset('images/mwis-logo.png') }}" alt="{{ __('MWIS mountain weather forecast') }}" class="h-8" />
        </a>
    </div>

    <flux:separator />

    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
        <div>
            <dt class="text-zinc-500">{{ __('Grid reference') }}</dt>
            <dd class="font-medium">{{ $hill->grid_ref }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('Coordinates') }}</dt>
            <dd class="font-medium">{{ $hill->latitude }}, {{ $hill->longitude }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('SMC section') }}</dt>
            <dd class="font-medium">{{ $hill->smc_section }} &ndash; {{ $hill->smc_section_description }}</dd>
        </div>
        <div class="col-span-2 sm:col-span-3">
            <dt class="text-zinc-500">{{ __('RHB region classification') }}</dt>
            <dd class="font-medium">{{ $hill->rhb_section }} &ndash; {{ $hill->rhb_section_description }}</dd>
        </div>
    </dl>
</div>
