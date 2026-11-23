{{-- Pick the area category (hill range, Murray sub-section, council area...) and then one of that kind. Sets the Livewire properties area and areaCategory. --}}
@php($types = \App\Support\AreaOptions::grouped())

<flux:select wire:model.live="areaCategory" class="max-w-44" aria-label="{{ __('Area category') }}">
    <option value="">{{ __('Anywhere') }}</option>
    @foreach ($types as $key => $type)
        <option value="{{ $key }}">{{ $type['label'] }}</option>
    @endforeach
</flux:select>

@if ($areaCategory !== '' && isset($types[$areaCategory]))
    <flux:select wire:model.live="area" wire:key="area-{{ $areaCategory }}" class="max-w-64" aria-label="{{ $types[$areaCategory]['label'] }}">
        <option value="">{{ __(':type: any', ['type' => $types[$areaCategory]['label']]) }}</option>
        @foreach ($types[$areaCategory]['areas'] as $area)
            <option value="{{ $area['code'] }}">{{ $area['name'] }} ({{ number_format($area['count']) }})</option>
        @endforeach
    </flux:select>
@endif
