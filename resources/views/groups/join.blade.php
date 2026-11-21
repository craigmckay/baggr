<x-layouts::auth :title="__('Join :group', ['group' => $group->name])">
    <div class="flex flex-col gap-6" data-test="join-group">
        <x-auth-header
            :title="$group->name"
            :description="__('You have been sent a link to join this group on Baggr.')"
        />

        @if ($already)
            <flux:text class="text-center">
                {{ __('You are already in this group.') }}
            </flux:text>

            <flux:button variant="primary" :href="route('groups.show', $group)" wire:navigate>
                {{ __('Go to the group') }}
            </flux:button>
        @elseif ($full)
            <flux:text class="text-center">
                {{ __('This group has too many people waiting to be approved. Ask an admin to clear the list, then try again.') }}
            </flux:text>
        @else
            <flux:text class="text-center">
                {{ __('An admin will need to approve you before you can see anything in the group.') }}
            </flux:text>

            <form method="POST" action="{{ route('groups.join.store', $token) }}" class="flex flex-col gap-6">
                @csrf

                @auth
                    <flux:text class="text-center">
                        {{ __('Joining as :name.', ['name' => auth()->user()->name]) }}
                    </flux:text>
                @else
                    <flux:input name="name" :label="__('Your name')" :value="old('name')" required autofocus autocomplete="name" />

                    <flux:input name="email" :label="__('Email address')" :value="old('email')" type="email" required autocomplete="email" placeholder="email@example.com" />

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
                @endauth

                @if ($errors->any() && auth()->check())
                    <flux:text class="text-red-600 dark:text-red-400">{{ $errors->first() }}</flux:text>
                @endif

                <flux:button variant="primary" type="submit" class="w-full" data-test="join-group-button">
                    {{ __('Join') }}
                </flux:button>
            </form>

            @guest
                <p class="text-sm text-center text-zinc-600 dark:text-zinc-400">
                    {{ __('Already have an account?') }}
                    <flux:link :href="route('login')" wire:navigate>{{ __('Log in first') }}</flux:link>,
                    {{ __('then open this link again.') }}
                </p>
            @endguest
        @endif
    </div>
</x-layouts::auth>
