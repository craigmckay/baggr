{{-- A picker for any DoBIH area, grouped by type. $model is the Livewire property it sets. --}}
<flux:select wire:model.live="{{ $model ?? 'area' }}" class="max-w-56" aria-label="{{ __('Area') }}">
    <option value="">{{ __('Anywhere') }}</option>
    @foreach (\App\Support\AreaOptions::grouped() as $type => $areas)
        <optgroup label="{{ $type }}">
            @foreach ($areas as $area)
                <option value="{{ $area['code'] }}">{{ $area['name'] }} ({{ number_format($area['count']) }})</option>
            @endforeach
        </optgroup>
    @endforeach
</flux:select>
