<x-mail::message>
# {{ __('Someone is waiting to join :group', ['group' => $groupName]) }}

{{ __('**:name** (:email) has used the link for **:group** on :app and is waiting for you to approve them.', ['name' => $visitorName, 'email' => $visitorEmail, 'group' => $groupName, 'app' => config('app.name')]) }}

{{ __('They can\'t see anything in the group until an admin gives them a role. Sign in and open the group to approve them or remove them.') }}

<x-mail::button :url="$url">
{{ __('Open :group', ['group' => $groupName]) }}
</x-mail::button>

{{ __('If you don\'t know this person, don\'t approve them. You can make a new link for the group from the same page.') }}
</x-mail::message>
