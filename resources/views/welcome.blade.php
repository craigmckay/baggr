<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-night font-brand antialiased">
        <main class="mx-auto flex min-h-svh w-full max-w-md flex-col overflow-hidden bg-night">
            <svg viewBox="0 0 390 420" preserveAspectRatio="xMidYMax slice" class="-mb-px block h-[40svh] max-h-[420px] min-h-[220px] w-full shrink-0" aria-hidden="true">
                <rect width="390" height="420" fill="#EDE3EE" />
                <circle cx="292" cy="196" r="36" fill="#F4CDB6" />
                <path d="M0 250 L48 205 L86 226 L140 168 L188 214 L236 186 L290 232 L340 196 L390 228 L390 420 L0 420 Z" fill="#A58DB8" />
                <path d="M0 300 L60 262 L104 284 L170 220 L214 262 L252 246 L318 296 L360 276 L390 290 L390 420 L0 420 Z" fill="#3F6F58" />
                <path d="M0 360 L52 334 L96 350 L150 300 L178 318 L232 262 L300 336 L346 322 L390 344 L390 420 L0 420 Z" fill="#13281F" />
            </svg>

            <div class="flex grow flex-col px-8 pb-10 pt-2">
                <div class="flex items-baseline gap-3">
                    <x-app-logo-icon class="h-11 shrink-0 fill-current text-heather" aria-hidden="true" />
                    <h1 class="text-6xl font-black leading-none tracking-tighter text-white">{{ config('app.name') }}</h1>
                </div>

                <p class="mt-4 text-[22px] font-black leading-snug text-[#D3E1DA]">{{ __('Get yourselves together.') }}</p>
                <p class="mt-1 text-[17px] font-semibold leading-snug text-[#A9C0B5]">{{ __('The hills are calling.') }}</p>

                <div class="grow"></div>

                <a href="{{ route('login') }}" class="flex h-14 items-center justify-center rounded-2xl bg-white text-lg font-black text-night hover:bg-[#E7EFEB]" data-test="sign-in-link">
                    {{ __('Sign in') }}
                </a>
                <p class="mt-4 text-center text-sm leading-snug text-[#A9C0B5]">
                    {{ __('Invite only. Ask someone in your group for an invite.') }}
                </p>
                <x-dobih-credit class="mt-3 text-center text-[#A9C0B5]/70" />
            </div>
        </main>
    </body>
</html>
