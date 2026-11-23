<?php

use App\Enums\HillGrouping;
use App\Models\Group;
use App\Models\Hill;
use App\Support\AreaOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Hills')] class extends Component
{
    use WithPagination;

    private const SORTABLE = ['name', 'height_m', 'region'];

    #[Url]
    public string $search = '';

    /** A DoBIH class code from config('dobih.lists'), or "all". */
    #[Url]
    public string $list = 'M';

    /** A DoBIH area code, or empty for anywhere. */
    #[Url]
    public string $area = '';

    /** The kind of area being picked (region, murray_section...); it only narrows the area picker. */
    public string $areaType = '';

    /** "mwis", "smc" or "rhb" to show the hills in sections, or empty for one plain list. */
    #[Url]
    public string $groupBy = '';

    /** Done by me: "", "done" or "todo"; or "group-{id}" for hills nobody in that group has done. */
    #[Url]
    public string $status = '';

    /** Height limits in metres. */
    #[Url]
    public string $minHeight = '';

    #[Url]
    public string $maxHeight = '';

    #[Url]
    public string $sortBy = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    /**
     * Livewire only tracks the page in the URL once a page is set, so set it
     * up front. That keeps the page in the address, and so in the link back
     * from a hill.
     */
    public function mount(): void
    {
        $this->paginators['page'] = Paginator::resolveCurrentPage('page');
        $this->areaType = AreaOptions::typeOf($this->area);
    }

    /** Choosing another kind of area drops the area picked from the last one. */
    public function updatedAreaType(): void
    {
        $this->area = '';
    }

    /** Any filter changing starts again from the first page. */
    public function updated(string $property): void
    {
        if ($property === 'groupBy') {
            $this->rememberGrouping();
        }

        $this->resetPage();
    }

    /** The grouping chosen here is the one My hills opens with. */
    private function rememberGrouping(): void
    {
        $grouping = HillGrouping::tryFrom($this->groupBy);

        if ($grouping === null) {
            $this->groupBy = '';

            return;
        }

        Auth::user()?->forceFill(['hill_grouping' => $grouping->value])->save();
    }

    public function clearFilters(): void
    {
        $this->reset('area', 'areaType', 'status', 'minHeight', 'maxHeight', 'search');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->area !== '' || $this->status !== '' || $this->minHeight !== '' || $this->maxHeight !== '' || $this->search !== '';
    }

    /** Only people who can log walks have anything to be "done" by. */
    public function canFilterByDone(): bool
    {
        return Auth::user()?->hasVerifiedEmail() ?? false;
    }

    /**
     * @return Collection<int, Group>
     */
    #[Computed]
    public function groups(): Collection
    {
        return $this->canFilterByDone() ? Auth::user()->groups()->orderBy('name')->get() : new Collection;
    }

    #[Computed]
    public function areaName(): ?string
    {
        return AreaOptions::name($this->area);
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function listName(): string
    {
        return config('dobih.lists')[$this->list] ?? __('hills');
    }

    #[Computed]
    public function hills(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $grouping = HillGrouping::tryFrom($this->groupBy);

        return Hill::query()
            ->when($this->area !== '', fn ($query) => $query
                ->whereHas('areas', fn ($areas) => $areas->where('areas.code', $this->area)))
            ->when(ctype_digit($this->minHeight), fn ($query) => $query->where('height_m', '>=', (int) $this->minHeight))
            ->when(ctype_digit($this->maxHeight), fn ($query) => $query->where('height_m', '<=', (int) $this->maxHeight))
            ->tap(fn ($query) => $this->applyDoneFilter($query))
            ->when(array_key_exists($this->list, config('dobih.lists')), fn ($query) => $query
                ->whereHas('classes', fn ($classes) => $classes->where('hill_classes.code', $this->list)))
            ->when(trim($this->search) !== '', fn ($query) => $this->applySearch($query))
            ->when($grouping !== null, fn ($query) => $query->orderByRaw($grouping->orderSql()))
            ->orderBy($sortBy, $direction)
            ->orderBy('id')
            ->paginate(50);
    }

    /**
     * Matches the start of a word in a hill's name, other names or region, so
     * "Dum" finds Dumyat and not every hill with "dum" in the middle of a word.
     * Area names are searched too, but only once there are four letters to go
     * on: "Dum" would otherwise bring in every hill in Dumfries and Galloway.
     *
     * @param  Illuminate\Database\Eloquent\Builder<Hill>  $query
     */
    private function applySearch($query): void
    {
        $term = trim($this->search);
        $wordStart = fn ($column) => fn ($match) => $match->where($column, 'like', "{$term}%")->orWhere($column, 'like', "% {$term}%");

        $query->where(function ($match) use ($term, $wordStart) {
            $match->where($wordStart('name'))
                ->orWhere($wordStart('region'))
                ->orWhereHas('alternateNames', fn ($names) => $names->where($wordStart('name')));

            if (mb_strlen($term) >= 4) {
                $match->orWhereHas('areas', fn ($areas) => $areas->where($wordStart('areas.name')));
            }
        });
    }

    /**
     * @param  Illuminate\Database\Eloquent\Builder<Hill>  $query
     */
    private function applyDoneFilter($query): void
    {
        if ($this->status === '' || ! $this->canFilterByDone()) {
            return;
        }

        $user = Auth::user();

        if ($this->status === 'done') {
            $query->whereHas('ascents', fn ($ascents) => $ascents->where('user_id', $user->id));
        } elseif ($this->status === 'todo') {
            $query->whereDoesntHave('ascents', fn ($ascents) => $ascents->where('user_id', $user->id));
        } elseif (str_starts_with($this->status, 'group-')) {
            // Only a group the person is in.
            $group = $this->groups->firstWhere('id', (int) substr($this->status, 6));

            if ($group !== null) {
                $members = $group->users()->pluck('users.id');

                $query->whereDoesntHave('ascents', fn ($ascents) => $ascents->whereIn('user_id', $members));
            }
        }
    }
};
?>

<div class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Hills') }}</flux:heading>
        <flux:subheading>
            {{ number_format($this->hills->total()) }} {{ $this->listName }}@if ($this->areaName), {{ $this->areaName }}@endif
        </flux:subheading>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="list" class="max-w-44" aria-label="{{ __('Hill list') }}">
            @foreach (config('dobih.lists') as $code => $name)
                <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
            @endforeach
            <flux:select.option value="all">{{ __('All hills') }}</flux:select.option>
        </flux:select>

        @include('partials.area-select', ['model' => 'area'])

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search by name or area…') }}"
            class="max-w-xs"
        />
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="groupBy" class="max-w-56" aria-label="{{ __('Group by') }}">
            <flux:select.option value="">{{ __('Not grouped') }}</flux:select.option>
            @foreach (\App\Enums\HillGrouping::cases() as $option)
                <flux:select.option value="{{ $option->value }}">{{ __('Group by :name', ['name' => $option->label()]) }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->canFilterByDone())
            <flux:select wire:model.live="status" class="max-w-56" aria-label="{{ __('Done by me') }}">
                <flux:select.option value="">{{ __('Done or not') }}</flux:select.option>
                <flux:select.option value="todo">{{ __('Not done by me') }}</flux:select.option>
                <flux:select.option value="done">{{ __('Done by me') }}</flux:select.option>
                @foreach ($this->groups as $group)
                    <flux:select.option value="group-{{ $group->id }}">{{ __('Not done by anyone in :group', ['group' => $group->name]) }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="flex items-center gap-2">
            <flux:input type="number" min="0" wire:model.live.debounce.500ms="minHeight" placeholder="{{ __('Min m') }}" class="w-24" aria-label="{{ __('Minimum height in metres') }}" />
            <span class="text-zinc-500">&ndash;</span>
            <flux:input type="number" min="0" wire:model.live.debounce.500ms="maxHeight" placeholder="{{ __('Max m') }}" class="w-24" aria-label="{{ __('Maximum height in metres') }}" />
        </div>

        @if ($this->hasFilters())
            <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark">{{ __('Clear filters') }}</flux:button>
        @endif
    </div>

    <flux:table class="w-full">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')" class="w-[45%] !whitespace-normal sm:w-auto">
                {{ __('Name') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'height_m'" :direction="$sortDirection" wire:click="sort('height_m')" class="w-[25%] sm:w-auto">
                {{ __('Height (m)') }}
            </flux:table.column>
            <flux:table.column class="w-[15%] sm:w-auto">{{ __('MWIS') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'region'" :direction="$sortDirection" wire:click="sort('region')" class="hidden w-[20%] sm:table-cell sm:w-auto">
                {{ __('Region') }}
            </flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @php
                $grouping = \App\Enums\HillGrouping::tryFrom($groupBy);
                $currentSection = null;
            @endphp
            @forelse ($this->hills as $hill)
                @if ($grouping)
                    @php($section = $grouping->sectionFor($hill))
                    @if ($section['key'] !== $currentSection)
                        @php($currentSection = $section['key'])
                        <flux:table.row :key="'section-'.$section['key'].'-'.$hill->id">
                            <flux:table.cell colspan="4" class="!bg-hill/10 text-sm font-semibold !text-hill dark:!bg-heather/15 dark:!text-heather">{{ $section['label'] }}</flux:table.cell>
                        </flux:table.row>
                    @endif
                @endif
                <flux:table.row
                    :key="$hill->id"
                    x-on:click="Livewire.navigate('{{ route('hills.show', $hill) }}')"
                    class="cursor-pointer"
                >
                    <flux:table.cell class="!whitespace-normal font-medium {{ $grouping ? 'ps-8' : '' }}">{{ $hill->name }}</flux:table.cell>
                    <flux:table.cell>{{ $hill->height_m }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($mwis = $hill->mwisArea())
                            <a
                                href="{{ $mwis['url'] }}"
                                target="_blank"
                                rel="noopener"
                                x-on:click.stop
                                title="{{ __('MWIS forecast: :area', ['area' => $mwis['name']]) }}"
                                class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600"
                            >{{ $mwis['code'] }}</a>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">{{ $hill->region ?? config('dobih.countries')[$hill->country] ?? '' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        {{ __('No hills match your search.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:pagination :paginator="$this->hills" />

    <x-dobih-credit class="text-zinc-500" />
</div>
