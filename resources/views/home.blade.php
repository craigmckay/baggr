{{-- The home dashboard (spec §6.0). The next plan and open polls join it in build steps 5 and 6. --}}
<x-layouts::app :title="__('Home')">
    @php
        $forecasts = new \App\Support\Mwis\Forecasts;
        $day = $forecasts->day();
        $user = auth()->user();
        $progress = new \App\Support\HillProgress($user, 'M');
        $latest = $user->ascents()
            ->with(['hill', 'walkDay.walk.days.hills'])
            ->get()
            ->sortByDesc(fn ($ascent) => [$ascent->walkDay->date, $ascent->id])
            ->first();
    @endphp

    <div class="mx-auto w-full max-w-2xl space-y-8">
        <section class="space-y-3" data-test="home-weather">
            <div>
                <flux:heading size="xl">{{ __('This weekend') }}</flux:heading>
                @if ($day)
                    <x-forecast-day :forecasts="$forecasts" />
                @endif
            </div>

            <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach (\App\Support\MwisAreas::all() as $code => $area)
                    @continue(! str_contains($area['url'], '/scottish/'))
                    @php($forecast = $forecasts->forArea($code))
                    @php($other = $forecast !== null && $forecasts->isForAnotherDay($forecast))

                    <li>
                        <a href="{{ route('my-hills.todo', ['mwis' => $code]) }}" wire:navigate @class(['flex items-center justify-between gap-3 p-3', 'hover:bg-zinc-50 dark:hover:bg-zinc-700/40' => ! $other, 'bg-amber-50 dark:bg-amber-950/40' => $other])>
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $area['name'] }}</span>
                                @if ($forecast?->headline)
                                    <span class="block text-sm text-zinc-500">{{ $forecast->headline }}</span>
                                @elseif ($forecast?->rain)
                                    <span class="block text-sm text-zinc-500">{{ $forecast->rain }}</span>
                                @endif
                                @if ($other)
                                    <span class="block text-xs font-medium text-amber-700 dark:text-amber-300">{{ __('Forecast date: :day', ['day' => $forecast->date->format('l j F')]) }}</span>
                                @endif
                            </span>
                            @if ($forecast)
                                <x-weather-rating :forecast="$forecast" class="shrink-0" />
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:link :href="route('weather')" wire:navigate>{{ __('All areas') }}</flux:link>
                <x-mwis-credit />
            </div>
        </section>

        <section class="space-y-2" data-test="home-progress">
            <flux:heading size="lg">{{ __('Munros') }}</flux:heading>

            <a href="{{ route('my-hills.index') }}" wire:navigate class="block space-y-2">
                <div class="flex items-baseline gap-3">
                    <span class="text-4xl font-bold tabular-nums">{{ $progress->doneCount() }}</span>
                    <span class="text-xl text-zinc-500 tabular-nums">/ {{ $progress->total() }}</span>
                    <span class="ms-auto text-lg text-zinc-500 tabular-nums">{{ $progress->percentage() }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-2 rounded-full" style="width: {{ $progress->percentage() }}%; background: {{ $user->colour }}"></div>
                </div>
            </a>

            @if ($latest)
                <flux:text>
                    {{ __('Latest: :hill, :date', ['hill' => $latest->hill->name, 'date' => $latest->walkDay->date?->format('j M Y')]) }}
                    &middot;
                    <flux:link :href="route('walks.show', $latest->walkDay->walk)" wire:navigate>{{ $latest->walkDay->walk->displayName() }}</flux:link>
                </flux:text>
            @else
                <flux:text>
                    {{ __('Nothing logged yet.') }}
                    <flux:link :href="route('walks.log')" wire:navigate>{{ __('Log a walk') }}</flux:link>
                </flux:text>
            @endif
        </section>
    </div>
</x-layouts::app>
