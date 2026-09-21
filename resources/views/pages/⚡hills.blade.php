<?php

use App\Models\Hill;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Hills')] class extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function hills(): Collection
    {
        return Hill::query()
            ->when($this->search !== '', fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('region', 'like', "%{$this->search}%"))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->get();
    }
};
?>

<div class="w-full space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Hills') }}</flux:heading>
            <flux:subheading>{{ __('All :count Munros, matched to their Walkhighlands route and MWIS forecast area.', ['count' => Hill::count()]) }}</flux:subheading>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search by name or region…') }}"
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
                    <flux:table.cell class="hidden sm:table-cell">{{ $hill->region }}</flux:table.cell>
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
</div>
