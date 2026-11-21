<?php

use App\Enums\GroupRole;
use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Groups')] class extends Component
{
    public string $name = '';

    public string $colour = '#1F4D3A';

    /**
     * @return Collection<int, Group>
     */
    #[Computed]
    public function groups(): Collection
    {
        return Auth::user()->groups()->orderBy('name')->get();
    }

    /**
     * Emailed invitations waiting for this person to accept.
     *
     * @return Collection<int, \App\Models\GroupInvite>
     */
    #[Computed]
    public function invites(): Collection
    {
        return Auth::user()->openInvites();
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'colour' => ['required', Rule::in(array_values(Group::COLOURS))],
        ]);

        $group = Group::create($validated);
        $group->users()->attach(Auth::user(), ['role' => GroupRole::Admin->value]);

        $this->redirectRoute('groups.show', $group, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Groups') }}</flux:heading>
        <flux:subheading>{{ __('The people you walk with.') }}</flux:subheading>
    </div>

    @if ($this->invites->isNotEmpty())
        <section class="space-y-3" data-test="open-invitations">
            <flux:heading>{{ __('Invitations for you') }}</flux:heading>

            @foreach ($this->invites as $invite)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $invite->group->name }}</div>
                        <flux:text class="text-sm">{{ __('As :role', ['role' => strtolower($invite->role->label())]) }}</flux:text>
                    </div>

                    <flux:button size="sm" variant="primary" :href="route('invitations.show', $invite->token)" wire:navigate>
                        {{ __('View') }}
                    </flux:button>
                </div>
            @endforeach
        </section>
    @endif

    <section class="space-y-3">
        @forelse ($this->groups as $group)
            <a
                href="{{ route('groups.show', $group) }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40"
                data-test="group-row"
            >
                <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $group->colour }}"></span>
                <span class="min-w-0 flex-1 truncate font-medium">{{ $group->name }}</span>
                <flux:badge size="sm" :color="$group->pivot->role === 'visitor' ? 'amber' : 'zinc'">
                    {{ $group->pivot->role === 'visitor' ? __('Waiting for approval') : GroupRole::from($group->pivot->role)->label() }}
                </flux:badge>
            </a>
        @empty
            <flux:text>{{ __('You are not in a group yet. Start one below, or ask an admin to invite you.') }}</flux:text>
        @endforelse
    </section>

    <flux:separator />

    <form wire:submit="create" class="space-y-4" data-test="create-group-form">
        <flux:heading>{{ __('Start a group') }}</flux:heading>

        <flux:input wire:model="name" :label="__('Name')" placeholder="{{ __('For example, the Sunday regulars') }}" required />

        <flux:select wire:model="colour" :label="__('Colour')">
            @foreach (Group::COLOURS as $label => $hex)
                <flux:select.option value="{{ $hex }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button type="submit" variant="primary" data-test="create-group-button">{{ __('Create the group') }}</flux:button>
    </form>
</div>
