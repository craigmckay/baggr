<?php

use App\Enums\GroupRole;
use App\Exceptions\GroupRuleException;
use App\Mail\GroupInvitation;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\User;
use App\Models\Walk;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Group $group;

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    /** The role chosen for each waiting visitor, by user id. */
    public array $visitorRole = [];

    public function mount(Group $group): void
    {
        $this->group = $group;

        // Only people in the group can see it exists.
        abort_if($this->role === null, 404);
    }

    public function rendering($view): void
    {
        $view->title($this->group->name);
    }

    #[Computed]
    public function role(): ?GroupRole
    {
        return $this->group->roleOf(Auth::user());
    }

    #[Computed]
    public function isAdmin(): bool
    {
        return $this->role === GroupRole::Admin;
    }

    /**
     * Everyone who has been let in (not waiting visitors).
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->group->users()
            ->wherePivot('role', '!=', GroupRole::Visitor->value)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function visitors(): Collection
    {
        return $this->group->users()
            ->wherePivot('role', GroupRole::Visitor->value)
            ->orderBy('group_user.created_at')
            ->get();
    }

    /**
     * @return Collection<int, GroupInvite>
     */
    #[Computed]
    public function invites(): Collection
    {
        return $this->group->invites()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('email')
            ->get();
    }

    /**
     * The group's latest walks.
     *
     * @return Collection<int, Walk>
     */
    #[Computed]
    public function walks(): Collection
    {
        return $this->group->walks()
            ->with('days.hills')
            ->withMin('days', 'date')
            ->orderByDesc('days_min_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    private function requireAdmin(): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);
    }

    private function refresh(): void
    {
        unset($this->role, $this->isAdmin, $this->members, $this->visitors, $this->invites, $this->walks);
        $this->group->refresh();
    }

    private function roleFromInput(string $value): GroupRole
    {
        $role = GroupRole::tryFrom($value);

        // Visitor can't be handed out from here, whatever the browser sends.
        abort_unless($role?->isAssignable(), 422);

        return $role;
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->requireAdmin();
        $newRole = $this->roleFromInput($role);
        $user = $this->group->users()->findOrFail($userId);

        try {
            $this->group->setRole($user, $newRole);
        } catch (GroupRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->refresh();
    }

    public function approve(int $userId): void
    {
        $this->changeRole($userId, $this->visitorRole[$userId] ?? 'member');
    }

    public function remove(int $userId): void
    {
        $this->requireAdmin();
        $user = $this->group->users()->findOrFail($userId);

        try {
            $this->group->removeMember($user);
        } catch (GroupRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->refresh();
    }

    public function leave(): void
    {
        try {
            $this->group->removeMember(Auth::user());
        } catch (GroupRuleException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->redirectRoute('groups.index', navigate: true);
    }

    public function invite(): void
    {
        $this->requireAdmin();

        $this->validate([
            'inviteEmail' => ['required', 'string', 'email', 'max:255'],
            'inviteRole' => ['required', Rule::in(array_map(fn (GroupRole $r) => $r->value, GroupRole::assignable()))],
        ]);

        $email = Str::lower(trim($this->inviteEmail));

        $existing = User::where('email', $email)->first();

        if ($existing !== null && $this->group->roleOf($existing) !== null && $this->group->roleOf($existing) !== GroupRole::Visitor) {
            $this->addError('inviteEmail', __('That person is already in this group.'));

            return;
        }

        $invite = GroupInvite::issue($this->group, $email, GroupRole::from($this->inviteRole), Auth::user());

        Mail::to($email)->send(new GroupInvitation($invite->load('group', 'inviter')));

        $this->reset('inviteEmail');
        $this->refresh();

        Flux::toast(variant: 'success', text: __('Invitation sent to :email.', ['email' => $email]));
    }

    public function cancelInvite(int $inviteId): void
    {
        $this->requireAdmin();

        $this->group->invites()->whereKey($inviteId)->delete();

        $this->refresh();
    }

    public function makeLink(): void
    {
        $this->requireAdmin();

        $this->group->makeJoinLink();
        $this->refresh();
    }

    public function revokeLink(): void
    {
        $this->requireAdmin();

        $this->group->revokeJoinLink();
        $this->refresh();
    }
};
?>

<div class="mx-auto w-full max-w-2xl space-y-8">
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="size-4 shrink-0 rounded-full" style="background-color: {{ $group->colour }}"></span>
            <div>
                <flux:heading size="xl">{{ $group->name }}</flux:heading>
                <flux:subheading>{{ $this->role->label() }}</flux:subheading>
            </div>
        </div>

        <flux:button icon="x-mark" :href="route('groups.index')" wire:navigate variant="ghost" aria-label="{{ __('Back to groups') }}" />
    </div>

    @if ($this->role === GroupRole::Visitor)
        <div class="space-y-4" data-test="waiting-for-approval">
            <flux:text>
                {{ __('You are waiting for an admin to approve you. Once they do, you will see the group here.') }}
            </flux:text>

            <flux:button
                wire:click="leave"
                wire:confirm="{{ __('Leave this group?') }}"
                variant="ghost"
                size="sm"
            >
                {{ __('Leave') }}
            </flux:button>
        </div>
    @else
        <section class="space-y-3">
            <flux:heading>{{ __('People') }}</flux:heading>

            @foreach ($this->members as $member)
                <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="member-row">
                    <div class="min-w-0">
                        <div class="truncate font-medium">
                            {{ $member->name }}
                            @if ($member->is(auth()->user()))
                                <span class="text-sm font-normal text-zinc-500">{{ __('(you)') }}</span>
                            @endif
                        </div>
                        @if ($this->isAdmin)
                            <flux:text class="truncate text-sm">{{ $member->email }}</flux:text>
                        @endif
                    </div>

                    @if ($this->isAdmin)
                        <div class="flex shrink-0 items-center gap-2">
                            <flux:select size="sm" class="w-32" wire:change="changeRole({{ $member->id }}, $event.target.value)" aria-label="{{ __('Role') }}">
                                @foreach (GroupRole::assignable() as $option)
                                    <flux:select.option value="{{ $option->value }}" :selected="$member->pivot->role === $option->value">{{ $option->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="remove({{ $member->id }})"
                                wire:confirm="{{ __('Remove :name from the group?', ['name' => $member->name]) }}"
                                aria-label="{{ __('Remove') }}"
                            />
                        </div>
                    @else
                        <flux:badge size="sm">{{ GroupRole::from($member->pivot->role)->label() }}</flux:badge>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="space-y-3" data-test="group-walks">
            <div class="flex items-center justify-between gap-3">
                <flux:heading>{{ __('Walks') }}</flux:heading>

                @if (in_array($this->role, [GroupRole::Admin, GroupRole::Member], true))
                    <flux:button size="xs" icon="plus" :href="route('walks.log', ['group' => $group->id])" wire:navigate>{{ __('Log a walk') }}</flux:button>
                @endif
            </div>

            @forelse ($this->walks as $walk)
                @php $hills = $walk->days->flatMap->hills; @endphp

                <a href="{{ route('walks.show', $walk) }}" wire:navigate class="flex items-baseline justify-between gap-3 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40" data-test="group-walk-row">
                    <span class="min-w-0 truncate">{{ $walk->title ?: $hills->pluck('name')->take(2)->implode(', ') }}</span>
                    <span class="shrink-0 text-sm text-zinc-500">{{ $walk->days->first()?->date?->format('j M Y') }}</span>
                </a>
            @empty
                <flux:text class="text-sm">{{ __('No walks logged for this group yet.') }}</flux:text>
            @endforelse
        </section>

        @if ($this->isAdmin)
            @if ($this->visitors->isNotEmpty())
                <section class="space-y-3" data-test="waiting-visitors">
                    <flux:heading>{{ __('Waiting for approval') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('These people joined through the group link. They see nothing until you approve them.') }}</flux:text>

                    @foreach ($this->visitors as $visitor)
                        <div class="space-y-2 rounded-lg border border-amber-300 p-3 dark:border-amber-700" data-test="visitor-row">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $visitor->name }}</div>
                                <flux:text class="truncate text-sm">{{ $visitor->email }}</flux:text>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:select size="sm" class="w-32" wire:model="visitorRole.{{ $visitor->id }}" aria-label="{{ __('Approve as') }}">
                                    {{-- Member first: it is what Approve uses if nothing is picked. --}}
                                    @foreach ([GroupRole::Member, GroupRole::Follower, GroupRole::Admin] as $option)
                                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:button size="sm" variant="primary" wire:click="approve({{ $visitor->id }})" data-test="approve-visitor">{{ __('Approve') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="remove({{ $visitor->id }})" wire:confirm="{{ __('Decline :name?', ['name' => $visitor->name]) }}">{{ __('Decline') }}</flux:button>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

            <flux:separator />

            <section class="space-y-4" data-test="invite-section">
                <flux:heading>{{ __('Invite by email') }}</flux:heading>
                <flux:text class="text-sm">{{ __('They get a link made for that address alone. It works once and lasts a week. You choose their role.') }}</flux:text>

                <form wire:submit="invite" class="space-y-3">
                    <flux:input wire:model="inviteEmail" type="email" :label="__('Email address')" placeholder="email@example.com" required />

                    <flux:select wire:model="inviteRole" :label="__('Role')">
                        @foreach (GroupRole::assignable() as $option)
                            <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button type="submit" variant="primary" data-test="send-invitation-button">{{ __('Send invitation') }}</flux:button>
                </form>

                @if ($this->invites->isNotEmpty())
                    <div class="space-y-2" data-test="pending-invitations">
                        <flux:text class="text-sm font-medium">{{ __('Waiting for an answer') }}</flux:text>

                        @foreach ($this->invites as $pending)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="min-w-0 break-words">{{ $pending->email }} <span class="text-zinc-500">({{ strtolower($pending->role->label()) }}, {{ __('expires :date', ['date' => $pending->expires_at->format('j M')]) }})</span></span>
                                <flux:button size="xs" variant="ghost" wire:click="cancelInvite({{ $pending->id }})">{{ __('Cancel') }}</flux:button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <flux:separator />

            <section class="space-y-4" data-test="join-link-section">
                <flux:heading>{{ __('Group link') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ __('One link for the whole group, handy to send on Signal or WhatsApp. Anyone who uses it arrives as a Visitor and sees nothing until you approve them. It lasts a week.') }}
                </flux:text>

                @if ($group->joinLinkIsLive())
                    <flux:input :value="route('groups.join', $group->join_token)" readonly copyable />
                    <flux:text class="text-sm">{{ __('Expires :date.', ['date' => $group->join_token_expires_at->format('j M, H:i')]) }}</flux:text>

                    <div class="flex gap-2">
                        <flux:button size="sm" wire:click="makeLink">{{ __('Make a new link') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="revokeLink" wire:confirm="{{ __('Switch the link off? Anyone who has it will no longer be able to use it.') }}">{{ __('Switch it off') }}</flux:button>
                    </div>
                @else
                    <flux:button wire:click="makeLink" data-test="make-link-button">{{ __('Make a link') }}</flux:button>
                @endif
            </section>
        @endif

        <flux:separator />

        <flux:button wire:click="leave" wire:confirm="{{ __('Leave this group? You keep your own ascents.') }}" variant="ghost" size="sm">
            {{ __('Leave the group') }}
        </flux:button>
    @endif
</div>
