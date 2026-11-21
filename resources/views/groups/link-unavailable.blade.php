<x-layouts::auth :title="__('Link unavailable')">
    <div class="flex flex-col gap-6" data-test="link-unavailable">
        <x-auth-header
            :title="$what === 'invitation' ? __('This invitation can\'t be used') : __('This link can\'t be used')"
            :description="__('It may have expired, been cancelled or already been used. Ask the person who sent it for a new one.')"
        />

        <flux:button :href="route('home')" wire:navigate variant="ghost">{{ __('Go to Baggr') }}</flux:button>
    </div>
</x-layouts::auth>
