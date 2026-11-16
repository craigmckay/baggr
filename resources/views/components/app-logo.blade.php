@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'baggr')" {{ $attributes->class('font-brand [&_.min-w-0]:text-xl [&_.min-w-0]:font-black [&_.min-w-0]:tracking-tight') }}>
        <x-slot name="logo" class="flex h-8 w-11 items-center justify-center">
            <x-app-logo-icon class="h-7 fill-current text-hill dark:text-heather" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'baggr')" {{ $attributes->class('font-brand [&_.min-w-0]:text-xl [&_.min-w-0]:font-black [&_.min-w-0]:tracking-tight') }}>
        <x-slot name="logo" class="flex h-8 w-11 items-center justify-center">
            <x-app-logo-icon class="h-7 fill-current text-hill dark:text-heather" />
        </x-slot>
    </flux:brand>
@endif
