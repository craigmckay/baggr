<?php

use App\Enums\HillGrouping;
use App\Models\Group;
use App\Support\HillProgress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Hills still to do')] class extends Component
{
    /** Sections with this many hills or fewer to do, in all, start open. */
    private const OPEN_BELOW = 300;

    /** A DoBIH class code from config('dobih.lists'). */
    #[Url]
    public string $list = 'M';

    /** "all", "solo" or a group's id. */
    #[Url]
    public string $scope = 'all';

    /** "region" keeps the hills in their regions; "height" is one list, tallest first. */
    #[Url]
    public string $sort = 'region';

    /** How regions are grouped; remembered on the person. */
    public string $grouping = 'mwis';

    public function mount(): void
    {
        $this->grouping = HillGrouping::tryFrom((string) Auth::user()->hill_grouping)?->value ?? 'mwis';
    }

    public function updatedGrouping(): void
    {
        $grouping = HillGrouping::tryFrom($this->grouping);

        if ($grouping === null) {
            $this->grouping = HillGrouping::Mwis->value;

            return;
        }

        Auth::user()->forceFill(['hill_grouping' => $grouping->value])->save();
    }

    #[Computed]
    public function progress(): HillProgress
    {
        return new HillProgress(Auth::user(), $this->list, $this->scope);
    }

    /**
     * @return Collection<int, Group>
     */
    #[Computed]
    public function groups(): Collection
    {
        return Auth::user()->groups()->orderBy('name')->get();
    }

    /**
     * The regions that still have hills to do, with only those hills in them.
     *
     * @return list<array{key: string, label: string, done: int, total: int, hills: list<array{hill: App\Models\Hill, colour: string|null}>}>
     */
    #[Computed]
    public function sections(): array
    {
        $sections = [];

        foreach ($this->progress->sections(HillGrouping::tryFrom($this->grouping) ?? HillGrouping::Mwis) as $section) {
            $todo = array_values(array_filter($section['hills'], fn (array $row) => $row['colour'] === null));

            if ($todo !== []) {
                $sections[] = [...$section, 'hills' => $todo];
            }
        }

        return $sections;
    }

    /**
     * Every hill still to do in one list, tallest first, each with its region.
     *
     * @return list<array{hill: App\Models\Hill, region: string}>
     */
    #[Computed]
    public function tallestFirst(): array
    {
        $rows = [];

        foreach ($this->sections as $section) {
            foreach ($section['hills'] as $row) {
                $rows[] = ['hill' => $row['hill'], 'region' => $section['label']];
            }
        }

        usort($rows, fn (array $a, array $b) => [$b['hill']->height_m, $a['hill']->name] <=> [$a['hill']->height_m, $b['hill']->name]);

        return $rows;
    }

    public function todoCount(): int
    {
        return $this->progress->total() - $this->progress->doneCount();
    }

    public function startsOpen(): bool
    {
        return $this->todoCount() <= self::OPEN_BELOW;
    }
};
?>

<div class="mx-auto w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Hills still to do') }}</flux:heading>
        <flux:subheading>
            {{ config('dobih.lists')[$this->progress->list()] }}:
            {{ __(':count to go', ['count' => $this->todoCount()]) }}
        </flux:subheading>
    </div>

    @include('partials.hill-filters')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:select wire:model.live="sort" class="max-w-44" aria-label="{{ __('Sort by') }}">
            <flux:select.option value="region">{{ __('Sort by region') }}</flux:select.option>
            <flux:select.option value="height">{{ __('Sort by height') }}</flux:select.option>
        </flux:select>

        <flux:link href="{{ route('my-hills.index', ['list' => $this->progress->list(), 'scope' => $this->progress->scope()]) }}" wire:navigate>
            {{ __('Back to my hills') }}
        </flux:link>
    </div>

    @if ($this->todoCount() === 0)
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.heading>{{ __('All done') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Nothing left on this list. Compleat!') }}</flux:callout.text>
        </flux:callout>
    @elseif ($sort === 'height')
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->tallestFirst as $row)
                <li wire:key="hill-{{ $row['hill']->id }}" class="flex items-center gap-3 py-2 text-sm">
                    <a href="{{ route('hills.show', $row['hill']) }}" wire:navigate class="font-medium hover:underline">{{ $row['hill']->name }}</a>
                    <span class="truncate text-zinc-500">{{ $row['region'] }}</span>
                    <span class="ms-auto text-zinc-500 tabular-nums">{{ $row['hill']->height_m }}m</span>
                </li>
            @endforeach
        </ul>
    @else
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->sections as $section)
                <div x-data="{ open: {{ $this->startsOpen() ? 'true' : 'false' }} }" wire:key="section-{{ $section['key'] }}" class="py-3">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex w-full items-center justify-between gap-3 text-start">
                        <span class="font-medium">{{ $section['label'] }}</span>
                        <span class="flex items-center gap-2 text-sm text-zinc-500 tabular-nums">
                            {{ __(':count to do', ['count' => count($section['hills'])]) }}
                            <flux:icon.chevron-down class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                        </span>
                    </button>

                    <ul x-show="open" x-cloak class="mt-3 space-y-1 text-sm">
                        @foreach ($section['hills'] as $row)
                            <li class="flex items-center gap-2" wire:key="hill-{{ $section['key'] }}-{{ $row['hill']->id }}">
                                <a href="{{ route('hills.show', $row['hill']) }}" wire:navigate class="font-medium hover:underline">{{ $row['hill']->name }}</a>
                                <span class="ms-auto text-zinc-500 tabular-nums">{{ $row['hill']->height_m }}m</span>
                                <a href="{{ route('walks.log', ['hill' => $row['hill']->slug]) }}" wire:navigate class="text-zinc-500 underline hover:text-zinc-900 dark:hover:text-white">{{ __('Log') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
