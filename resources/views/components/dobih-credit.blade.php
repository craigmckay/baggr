<p {{ $attributes->class('text-xs leading-snug') }} data-test="dobih-credit">
    {{ __('Hill data:') }}
    <a href="{{ config('dobih.url') }}" target="_blank" rel="noopener" class="underline underline-offset-2">{{ __('Database of British and Irish Hills') }} v{{ config('dobih.version') }}</a>,
    <a href="{{ config('dobih.licence_url') }}" target="_blank" rel="noopener" class="underline underline-offset-2">CC BY 4.0</a>
</p>
