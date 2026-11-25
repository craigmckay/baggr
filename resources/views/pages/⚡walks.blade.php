<?php

use App\Models\Walk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Walks')] class extends Component
{
    /**
     * Walks this person logged or was on, newest first.
     *
     * @return Collection<int, Walk>
     */
    #[Computed]
    public function walks(): Collection
    {
        $user = Auth::user();

        return Walk::query()
            ->where(fn ($query) => $query
                ->where('organiser_id', $user->id)
                ->orWhereHas('attendees', fn ($attendees) => $attendees->where('user_id', $user->id)))
            ->with(['group', 'days.hills'])
            ->withMin('days', 'date')
            ->orderByDesc('days_min_date')
            ->orderByDesc('id')
            ->get();
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Walks') }}</flux:heading>
            <flux:subheading>{{ __('Walks you have logged or been on.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('walks.log')" wire:navigate data-test="log-a-walk">{{ __('Log a walk') }}</flux:button>
    </div>

    <section class="space-y-3">
        @forelse ($this->walks as $walk)
            @php
                $hills = $walk->days->flatMap->hills;
                $date = $walk->days->first()?->date;
            @endphp

            <a
                href="{{ route('walks.show', $walk) }}"
                wire:navigate
                class="block space-y-1 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40"
                data-test="walk-row"
            >
                <div class="flex items-baseline justify-between gap-3">
                    <span class="min-w-0 truncate font-medium">{{ $walk->displayName() }}</span>
                    <span class="shrink-0 text-sm text-zinc-500">{{ $date?->format('j M Y') }}</span>
                </div>

                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    @if ($walk->group)
                        <span class="size-2 rounded-full" style="background-color: {{ $walk->group->colour }}"></span>{{ $walk->group->name }} ·
                    @else
                        {{ __('Solo') }} ·
                    @endif
                    {{ trans_choice(':count hill|:count hills', $hills->count()) }}
                </div>
            </a>
        @empty
            <flux:text>{{ __('No walks yet. Log your first one.') }}</flux:text>
        @endforelse
    </section>
</div>
