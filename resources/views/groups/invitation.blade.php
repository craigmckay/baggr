<x-layouts::auth :title="__('Invitation to :group', ['group' => $invite->group->name])">
    <div class="flex flex-col gap-6" data-test="group-invitation">
        <x-auth-header
            :title="$invite->group->name"
            :description="__('You have been invited to join as a :role.', ['role' => strtolower($invite->role->label())])"
        />

        @if ($signedInIsInvitee)
            <form method="POST" action="{{ route('invitations.accept', $invite->token) }}" class="flex flex-col gap-6">
                @csrf
                <flux:button variant="primary" type="submit" class="w-full" data-test="accept-invitation-button">
                    {{ __('Accept the invitation') }}
                </flux:button>
            </form>
        @elseif ($signedInAs)
            <flux:text class="text-center" data-test="wrong-account">
                {{ __('This invitation is for another email address. Log out and open the link again.') }}
            </flux:text>
        @elseif ($hasAccount)
            <flux:text class="text-center">
                {{ __('You already have an account. Log in with :email, then open this link again.', ['email' => $invite->email]) }}
            </flux:text>

            <flux:button variant="primary" :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:button>
        @else
            <form method="POST" action="{{ route('invitations.register', $invite->token) }}" class="flex flex-col gap-6">
                @csrf

                <flux:input :label="__('Email address')" :value="$invite->email" type="email" disabled />

                <flux:input name="name" :label="__('Your name')" :value="old('name')" required autofocus autocomplete="name" />

                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:button variant="primary" type="submit" class="w-full" data-test="create-account-button">
                    {{ __('Create my account') }}
                </flux:button>
            </form>
        @endif
    </div>
</x-layouts::auth>
