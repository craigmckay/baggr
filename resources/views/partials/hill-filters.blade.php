{{-- Shared by My hills and Hills still to do: list, which ascents count, and how hills are put into sections. --}}
<div class="flex flex-wrap items-center gap-3">
    <flux:select wire:model.live="list" class="max-w-44" aria-label="{{ __('Hill list') }}">
        @foreach (config('dobih.lists') as $code => $name)
            <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
        @endforeach
    </flux:select>

    @include('partials.area-select', ['model' => 'area'])

    <flux:select wire:model.live="scope" class="max-w-52" aria-label="{{ __('Which ascents') }}">
        <flux:select.option value="all">{{ __('All ascents') }}</flux:select.option>
        <flux:select.option value="solo">{{ __('Solo only') }}</flux:select.option>
        @foreach ($this->groups as $group)
            <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select wire:model.live="grouping" class="max-w-56" aria-label="{{ __('Group sections by') }}">
        @foreach (\App\Enums\HillGrouping::cases() as $option)
            <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
