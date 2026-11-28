<?php

use App\Models\Ascent;
use App\Models\Group;
use App\Support\HillProgress;
use App\Support\Mwis\Forecasts;
use App\Support\MwisAreas;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The area view (spec §4): "where should we go this weekend?" Each MWIS area with
 * its forecast, and how many hills there are still to do.
 */
new #[Title('Weather')] class extends Component
{
    /** A DoBIH class code from config('dobih.lists'). */
    #[Url]
    public string $list = 'M';

    /** The id of the group whose count is shown too, or "none". */
    #[Url]
    public string $group = '';

    public function mount(): void
    {
        if ($this->group === '') {
            $this->group = (string) ($this->groups->first()->id ?? 'none');
        }
    }

    /**
     * @return Collection<int, Group>
     */
    #[Computed]
    public function groups(): Collection
    {
        return Auth::user()->groups()->orderBy('name')->get();
    }

    /** Only a group the person is in. */
    #[Computed]
    public function selectedGroup(): ?Group
    {
        return $this->groups->firstWhere('id', (int) $this->group);
    }

    /**
     * For each area, the hills on the list that I haven't done, and that nobody in
     * the group has done.
     *
     * @return array<string, array{mine: int, group: int}>
     */
    #[Computed]
    public function counts(): array
    {
        $hills = (new HillProgress(Auth::user(), $this->list))->hills();
        $done = fn (array $users) => Ascent::query()->whereIn('user_id', $users)->pluck('hill_id')->flip();

        $mine = $done([Auth::id()]);
        $theirs = $this->selectedGroup === null ? collect() : $done($this->selectedGroup->users()->pluck('users.id')->all());

        $counts = array_map(fn () => ['mine' => 0, 'group' => 0], MwisAreas::all());

        foreach ($hills as $hill) {
            $code = $hill->mwisArea()['code'] ?? null;

            if ($code === null) {
                continue;
            }

            $counts[$code]['mine'] += $mine->has($hill->id) ? 0 : 1;
            $counts[$code]['group'] += $theirs->has($hill->id) ? 0 : 1;
        }

        return $counts;
    }
};
?>

@php
    $forecasts = new Forecasts;
    $list = array_key_exists($this->list, config('dobih.lists')) ? $this->list : 'M';
@endphp

<div class="mx-auto w-full max-w-3xl space-y-6">
    <div class="space-y-1">
        <flux:heading size="xl">{{ __('Weather') }}</flux:heading>
        @if ($forecasts->day())
            <flux:subheading data-test="forecast-day">{{ __('MWIS forecast for :day', ['day' => $forecasts->day()->format('l j F')]) }}</flux:subheading>
        @else
            <flux:subheading>{{ __('Each area links to its forecast on MWIS.') }}</flux:subheading>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="list" class="max-w-44" aria-label="{{ __('Hill list') }}">
            @foreach (config('dobih.lists') as $code => $name)
                <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->groups->isNotEmpty())
            <flux:select wire:model.live="group" class="max-w-56" aria-label="{{ __('Group') }}">
                <flux:select.option value="none">{{ __('Just me') }}</flux:select.option>
                @foreach ($this->groups as $mine)
                    <flux:select.option value="{{ $mine->id }}">{{ $mine->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    @foreach (['scottish' => __('Scotland'), 'english-and-welsh' => __('England and Wales')] as $path => $heading)
        <section class="space-y-3">
            <flux:heading size="lg">{{ $heading }}</flux:heading>

            @foreach (\App\Support\MwisAreas::all() as $code => $area)
                @continue(! str_contains($area['url'], "/{$path}/"))
                @php($forecast = $forecasts->forArea($code))

                <article wire:key="area-{{ $code }}" class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="area-{{ $code }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading>
                                <a href="{{ route('my-hills.todo', ['mwis' => $code, 'list' => $list]) }}" wire:navigate class="hover:underline">{{ $area['name'] }}</a>
                            </flux:heading>
                            @if ($forecast && ! $forecast->date->isSameDay($forecasts->day()))
                                <flux:text>{{ $forecast->date->format('l j F') }}</flux:text>
                            @endif
                        </div>
                        @if ($forecast)
                            <x-weather-rating :forecast="$forecast" />
                        @endif
                    </div>

                    @if ($forecast)
                        <x-forecast-details :forecast="$forecast" :forecasts="$forecasts" :url="$area['url']" compact />
                    @else
                        <p class="text-sm"><a href="{{ $area['url'] }}" target="_blank" rel="noopener" class="underline">{{ __('Full MWIS forecast') }}</a></p>
                    @endif

                    <p class="text-sm">
                        <a href="{{ route('my-hills.todo', ['mwis' => $code, 'list' => $list]) }}" wire:navigate class="font-medium underline">
                            {{ __(':count for me to do', ['count' => $this->counts[$code]['mine']]) }}
                        </a>
                        @if ($this->selectedGroup)
                            <span class="text-zinc-500">&middot; {{ __(':count nobody in :group has done', ['count' => $this->counts[$code]['group'], 'group' => $this->selectedGroup->name]) }}</span>
                        @endif
                    </p>
                </article>
            @endforeach
        </section>
    @endforeach

    <x-mwis-credit />
</div>
