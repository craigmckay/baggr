<x-mail::message>
# {{ __('Join :group', ['group' => $groupName]) }}

@if ($inviter)
{{ __(':inviter has invited you to join **:group** on :app as a :role.', ['inviter' => $inviter, 'group' => $groupName, 'app' => config('app.name'), 'role' => strtolower($role)]) }}
@else
{{ __('You have been invited to join **:group** on :app as a :role.', ['group' => $groupName, 'app' => config('app.name'), 'role' => strtolower($role)]) }}
@endif

<x-mail::button :url="$url">
{{ __('Accept the invitation') }}
</x-mail::button>

{{ __('This link is for you alone and works once. It expires in :days days.', ['days' => $days]) }}

{{ __('If you weren\'t expecting this, you can ignore the email.') }}
</x-mail::message>
