<?php

use App\Models\Walk;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public Walk $walk;

    public function mount(Walk $walk): void
    {
        $this->walk = $walk;

        // Anyone who can't see a walk is told it doesn't exist.
        abort_unless($walk->canBeSeenBy(Auth::user()), 404);
    }

    public function rendering($view): void
    {
        $view->title($this->walk->title ?: __('Walk'));
    }

    public function delete(): void
    {
        abort_unless($this->walk->canBeDeletedBy(Auth::user()), 403);

        $this->walk->delete();

        Flux::toast(variant: 'success', text: __('Walk deleted.'));

        $this->redirectRoute('walks.index', navigate: true);
    }
};
?>

@php
    $walk->load(['group', 'organiser', 'days.hills', 'days.ascents.user', 'attendees.user', 'attendees.guest']);
    $first = $walk->days->first();
    $heading = $walk->title ?: __('Walk on :date', ['date' => $first?->date?->format('j M Y')]);
@endphp

<div class="mx-auto w-full max-w-2xl space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $heading }}</flux:heading>
            <flux:subheading>
                @if ($walk->group)
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background-color: {{ $walk->group->colour }}"></span>{{ $walk->group->name }}</span>
                @else
                    {{ __('Solo') }}
                @endif
                @if ($walk->days->count() > 1)
                    · {{ __(':n days', ['n' => $walk->days->count()]) }}
                @endif
            </flux:subheading>
        </div>

        <flux:button icon="x-mark" :href="route('walks.index')" wire:navigate variant="ghost" aria-label="{{ __('Back to walks') }}" />
    </div>

    @foreach ($walk->days as $day)
        <section class="space-y-3" data-test="walk-day">
            <div>
                @if ($walk->days->count() > 1)
                    <flux:heading>{{ __('Day :n', ['n' => $day->day_number]) }}</flux:heading>
                @endif
                <flux:text class="text-sm">
                    {{ collect([
                        $day->date?->format('l j F Y'),
                        $day->direction?->label(),
                        $day->duration_minutes ? __(':h h :m min', ['h' => intdiv($day->duration_minutes, 60), 'm' => $day->duration_minutes % 60]) : null,
                    ])->filter()->implode(' · ') }}
                </flux:text>
            </div>

            @foreach ($day->hills as $hill)
                @php $climbers = $day->ascents->where('hill_id', $hill->id)->map(fn ($ascent) => $ascent->user?->name)->filter()->sort()->values(); @endphp

                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="walk-hill">
                    <div class="flex items-baseline justify-between gap-3">
                        <a href="{{ route('hills.show', $hill) }}" wire:navigate class="font-medium hover:underline">
                            <span class="text-zinc-500">{{ $hill->pivot->position }}.</span> {{ $hill->name }}
                        </a>
                        <span class="shrink-0 text-sm text-zinc-500">{{ $hill->height_m }}m</span>
                    </div>

                    @if ($climbers->isNotEmpty())
                        <flux:text class="mt-1 text-sm">{{ $climbers->implode(', ') }}</flux:text>
                    @endif
                </div>
            @endforeach
        </section>
    @endforeach

    <section class="space-y-2">
        <flux:heading>{{ __('On the walk') }}</flux:heading>
        <flux:text>{{ $walk->attendees->map(fn ($attendee) => $attendee->displayName())->sort()->implode(', ') }}</flux:text>
    </section>

    @if ($walk->notes)
        <section class="space-y-2">
            <flux:heading>{{ __('Notes') }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $walk->notes }}</flux:text>
        </section>
    @endif

    <flux:text class="text-sm text-zinc-500">
        {{ __('Logged by :name.', ['name' => $walk->organiser?->name ?? __('a former member')]) }}
    </flux:text>

    @if ($walk->canBeDeletedBy(auth()->user()))
        <flux:button
            variant="ghost"
            size="sm"
            wire:click="delete"
            wire:confirm="{{ __('Delete this walk? It also removes the ascents it recorded, for everyone on it.') }}"
            data-test="delete-walk-button"
        >
            {{ __('Delete the walk') }}
        </flux:button>
    @endif
</div>
