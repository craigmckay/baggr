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

new #[Title('My hills')] class extends Component
{
    /** A DoBIH class code from config('dobih.lists'). */
    #[Url]
    public string $list = 'M';

    /** "all", "solo" or a group's id. */
    #[Url]
    public string $scope = 'all';

    /** A DoBIH area code, or empty for anywhere. */
    #[Url]
    public string $area = '';

    /** How regions are grouped; remembered on the person. */
    public string $grouping = 'mwis';

    /** Regions whose hills are shown. Only these are drawn, so a long list stays quick. */
    public array $open = [];

    public function toggle(string $key): void
    {
        $this->open = in_array($key, $this->open, true)
            ? array_values(array_diff($this->open, [$key]))
            : [...$this->open, $key];
    }

    public function updatedList(): void
    {
        $this->open = [];
    }

    public function updatedScope(): void
    {
        $this->open = [];
    }

    public function updatedArea(): void
    {
        $this->open = [];
    }

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
        return new HillProgress(Auth::user(), $this->list, $this->scope, $this->area);
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
     * @return list<array{key: string, label: string, done: int, total: int, hills: list<array{hill: App\Models\Hill, colour: string|null}>}>
     */
    #[Computed]
    public function sections(): array
    {
        return $this->progress->sections(HillGrouping::tryFrom($this->grouping) ?? HillGrouping::Mwis);
    }
};
?>

<div class="mx-auto w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('My hills') }}</flux:heading>
        <flux:subheading>
            {{ config('dobih.lists')[$this->progress->list()] }}@if ($this->progress->area() !== ''), {{ \App\Support\AreaOptions::name($this->progress->area()) }}@endif
        </flux:subheading>
    </div>

    @include('partials.hill-filters')

    <div class="space-y-2">
        <div class="flex items-baseline gap-3">
            <span class="text-5xl font-bold tabular-nums" data-test="done-count">{{ $this->progress->doneCount() }}</span>
            <span class="text-2xl text-zinc-500 tabular-nums">/ {{ $this->progress->total() }}</span>
            <span class="ms-auto text-lg text-zinc-500 tabular-nums">{{ $this->progress->percentage() }}%</span>
        </div>

        <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-valuenow="{{ $this->progress->percentage() }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-2 rounded-full" style="width: {{ $this->progress->percentage() }}%; background: {{ auth()->user()->colour }}"></div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2" aria-label="{{ __('Other lists') }}">
        @foreach ($this->progress->summary() as $code => $row)
            @if ($row['total'] > 0)
                <button
                    type="button"
                    wire:click="$set('list', '{{ $code }}')"
                    @class([
                        'rounded-full border px-3 py-1 text-sm tabular-nums',
                        'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $code === $this->progress->list(),
                        'border-zinc-300 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-700' => $code !== $this->progress->list(),
                    ])
                >
                    {{ $row['name'] }} {{ $row['done'] }}/{{ $row['total'] }}
                </button>
            @endif
        @endforeach
    </div>

    @if ($this->progress->doneCount() === 0)
        <flux:callout icon="flag">
            <flux:callout.heading>{{ __('Nothing here yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Log a walk and the hills you climb will fill in below.') }}
                <flux:callout.link href="{{ route('walks.log') }}" wire:navigate>{{ __('Log a walk') }}</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-1">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('By region') }}</flux:heading>
            <flux:link href="{{ route('my-hills.todo', ['list' => $this->progress->list(), 'scope' => $this->progress->scope(), 'area' => $this->progress->area()]) }}" wire:navigate>
                {{ __('Hills still to do') }}
            </flux:link>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->sections as $section)
                @php($isOpen = in_array($section['key'], $this->open, true))
                <div wire:key="section-{{ $section['key'] }}" class="py-3">
                    <button type="button" wire:click="toggle(@js($section['key']))" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" class="flex w-full items-center justify-between gap-3 text-start">
                        <span class="font-medium">{{ $section['label'] }}</span>
                        <span class="flex items-center gap-2 text-sm text-zinc-500 tabular-nums">
                            {{ $section['done'] }} / {{ $section['total'] }}
                            <flux:icon.chevron-down @class(['size-4 transition', 'rotate-180' => $isOpen]) />
                        </span>
                    </button>

                    @if ($section['done'] > 0)
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($section['hills'] as $row)
                                @if ($row['colour'] !== null)
                                    <a
                                        href="{{ route('hills.show', $row['hill']) }}"
                                        wire:navigate
                                        title="{{ $row['hill']->name }}"
                                        aria-label="{{ $row['hill']->name }}"
                                        class="size-3.5 rounded-sm"
                                        style="background: {{ $row['colour'] }}"
                                    ></a>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if ($isOpen)
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($section['hills'] as $row)
                            <li class="flex items-center gap-2" wire:key="hill-{{ $section['key'] }}-{{ $row['hill']->id }}">
                                <span class="size-3 shrink-0 rounded-sm {{ $row['colour'] === null ? 'border border-zinc-400 dark:border-zinc-500' : '' }}" @if ($row['colour'] !== null) style="background: {{ $row['colour'] }}" @endif></span>
                                <a href="{{ route('hills.show', $row['hill']) }}" wire:navigate class="{{ $row['colour'] === null ? 'text-zinc-500' : 'font-medium' }} hover:underline">{{ $row['hill']->name }}</a>
                                <span class="ms-auto text-zinc-500 tabular-nums">{{ $row['hill']->height_m }}m</span>
                            </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
