<?php

use App\Models\Hill;
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
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedList(): void
    {
        $this->resetPage();
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

        return Hill::query()
            ->when(array_key_exists($this->list, config('dobih.lists')), fn ($query) => $query
                ->whereHas('classes', fn ($classes) => $classes->where('hill_classes.code', $this->list)))
            ->when($this->search !== '', function ($query) {
                $term = "%{$this->search}%";

                $query->where(fn ($match) => $match
                    ->where('name', 'like', $term)
                    ->orWhere('region', 'like', $term)
                    ->orWhereHas('alternateNames', fn ($names) => $names->where('name', 'like', $term))
                    ->orWhereHas('areas', fn ($areas) => $areas->where('areas.name', 'like', $term)));
            })
            ->orderBy($sortBy, $direction)
            ->orderBy('id')
            ->paginate(50);
    }
};
?>

<div class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Hills') }}</flux:heading>
        <flux:subheading>{{ number_format($this->hills->total()) }} {{ $this->listName }}</flux:subheading>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="list" class="max-w-44" aria-label="{{ __('Hill list') }}">
            @foreach (config('dobih.lists') as $code => $name)
                <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
            @endforeach
            <flux:select.option value="all">{{ __('All hills') }}</flux:select.option>
        </flux:select>

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search by name or area…') }}"
            class="max-w-xs"
        />
    </div>

    <flux:table class="w-full">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')" class="w-[55%] !whitespace-normal sm:w-auto">
                {{ __('Name') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'height_m'" :direction="$sortDirection" wire:click="sort('height_m')" class="w-[25%] sm:w-auto">
                {{ __('Height (m)') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'region'" :direction="$sortDirection" wire:click="sort('region')" class="hidden w-[20%] sm:table-cell sm:w-auto">
                {{ __('Region') }}
            </flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->hills as $hill)
                <flux:table.row
                    :key="$hill->id"
                    x-on:click="Livewire.navigate('{{ route('hills.show', $hill) }}')"
                    class="cursor-pointer"
                >
                    <flux:table.cell class="!whitespace-normal font-medium">{{ $hill->name }}</flux:table.cell>
                    <flux:table.cell>{{ $hill->height_m }}</flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">{{ $hill->region ?? config('dobih.countries')[$hill->country] ?? '' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center text-zinc-500">
                        {{ __('No hills match your search.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:pagination :paginator="$this->hills" />

    <x-dobih-credit class="text-zinc-500" />
</div>
