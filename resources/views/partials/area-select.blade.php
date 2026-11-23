{{-- Pick the kind of area (region, Murray section, council area...) and then one of that kind. Sets the Livewire properties area and areaType. --}}
@php($types = \App\Support\AreaOptions::grouped())

<flux:select wire:model.live="areaType" class="max-w-44" aria-label="{{ __('Kind of area') }}">
    <option value="">{{ __('Anywhere') }}</option>
    @foreach ($types as $key => $type)
        <option value="{{ $key }}">{{ $type['label'] }}</option>
    @endforeach
</flux:select>

@if ($areaType !== '' && isset($types[$areaType]))
    <flux:select wire:model.live="area" wire:key="area-{{ $areaType }}" class="max-w-64" aria-label="{{ $types[$areaType]['label'] }}">
        <option value="">{{ __(':type: any', ['type' => $types[$areaType]['label']]) }}</option>
        @foreach ($types[$areaType]['areas'] as $area)
            <option value="{{ $area['code'] }}">{{ $area['name'] }} ({{ number_format($area['count']) }})</option>
        @endforeach
    </flux:select>
@endif
