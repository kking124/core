@use('Cachet\Cachet')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <script>
            (() => {
                const root = document.documentElement
                const preferenceKey = 'cachet-theme'
                const media = window.matchMedia('(prefers-color-scheme: dark)')
                let savedTheme = null

                try {
                    savedTheme = localStorage.getItem(preferenceKey)
                } catch {}

                const applyTheme = (theme) => {
                    root.dataset.theme = theme
                }

                applyTheme(savedTheme === 'light' || savedTheme === 'dark' ? savedTheme : (media.matches ? 'dark' : 'light'))

                window.cachetSetTheme = (theme) => {
                    const selectedTheme = theme === 'dark' ? 'dark' : 'light'
                    savedTheme = selectedTheme
                    try {
                        localStorage.setItem(preferenceKey, selectedTheme)
                    } catch {}
                    applyTheme(selectedTheme)
                }

                media.addEventListener('change', (event) => {
                    if (savedTheme !== 'light' && savedTheme !== 'dark') {
                        applyTheme(event.matches ? 'dark' : 'light')
                    }
                })
            })()
        </script>

        @if ($favicon)
        <link rel="icon" href="{{ asset('vendor/cachethq/cachet/favicon.ico') }}" sizes="32x32" />
        <link rel="icon" href="{{ $favicon }}" type="image/svg+xml" sizes="any" />
        @else
        <link rel="shortcut icon" href="{{ asset('vendor/cachethq/cachet/favicon.ico') }}" />
        @endif
        <link rel="apple-touch-icon" href="{{ asset('vendor/cachethq/cachet/apple-touch-icon.png') }}" />

        <title>{{ $title ?: config('cachet.title', 'Cachet') }}</title>
        <meta name="title" content="{{ $title ?: config('cachet.title', 'Cachet') }}" />
        <meta name="description" content="{{ $description }}" />

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website" />
        <meta property="og:url" content="{{ url(Cachet::path()) }}" />
        <meta property="og:image" content="{{ asset('/vendor/cachethq/cachet/android-chrome-512x512.png') }}" />
        <meta property="og:title" content="{{ $title ?: config('cachet.title', 'Cachet') }}" />
        <meta property="og:description" content="{{ $description }}" />

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image" />
        <meta property="twitter:url" content="{{ url(Cachet::path()) }}" />
        <meta property="twitter:image" content="{{ asset('/vendor/cachethq/cachet/android-chrome-512x512.png') }}" />
        <meta property="twitter:title" content="{{ $title ?: config('cachet.title', 'Cachet') }}" />
        <meta property="twitter:description" content="{{ $description }}" />

        @vite(['resources/css/cachet.css', 'resources/js/cachet.js'], 'vendor/cachethq/cachet/build')
        @filamentStyles

        @if($refresh_rate)
        <meta http-equiv="refresh" content="{{ $refresh_rate }}">
        @endif

        <!-- Custom Cachet Header -->
        {!! $cachet_header !!}

        <style type="text/css">
            {{ $theme->styles  }}

            {!! $cachet_css !!}
        </style>
    </head>
    <body data-page="{{ $page }}" class="flex min-h-screen flex-col items-stretch antialiased">
        {{ \Cachet\Facades\CachetView::renderHook(\Cachet\View\RenderHook::STATUS_PAGE_BODY_BEFORE) }}
        {{ $slot }}

        <!-- Custom Cachet Footer -->
        {!! $cachet_footer !!}

        {{ \Cachet\Facades\CachetView::renderHook(\Cachet\View\RenderHook::STATUS_PAGE_BODY_AFTER) }}
    </body>
</html>
