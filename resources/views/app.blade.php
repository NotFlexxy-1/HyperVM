<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ app(App\Services\SettingsRepository::class)->get('theme.default_mode') === 'light' ? '' : 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($hv = app(App\Services\SettingsRepository::class)->frontendPayload())
    <title inertia>{{ $hv['branding']['panel_name'] }}</title>
    <meta name="description" content="{{ $hv['branding']['social_description'] }}">
    <meta property="og:title" content="{{ $hv['branding']['panel_name'] }}">
    <meta property="og:description" content="{{ $hv['branding']['social_description'] }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    @if($hv['branding']['logo_url'])
        <meta property="og:image" content="{{ $hv['branding']['logo_url'] }}">
        <meta name="twitter:image" content="{{ $hv['branding']['logo_url'] }}">
    @endif
    <link rel="icon" href="{{ $hv['branding']['favicon_url'] ?? '/favicon.ico' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/icon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicons/icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileImage" content="/favicons/mstile-270.png">
    <meta name="msapplication-TileColor" content="{{ $hv['theme']['brand'] }}">
    <meta name="theme-color" content="{{ $hv['theme']['brand'] }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family={{ urlencode(strtolower($hv['theme']['font'])) }}:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --hv-brand: {{ implode(' ', sscanf($hv['theme']['brand'], '#%02x%02x%02x')) }};
            --hv-brand-soft: {{ implode(' ', sscanf($hv['theme']['brand_soft'], '#%02x%02x%02x')) }};
            --hv-brand-contrast: {{ implode(' ', sscanf($hv['theme']['brand_contrast'], '#%02x%02x%02x')) }};
            --hv-accent: {{ implode(' ', sscanf($hv['theme']['accent'], '#%02x%02x%02x')) }};
            --hv-radius: {{ $hv['theme']['radius'] }};
            --hv-font: '{{ $hv['theme']['font'] }}';
        }
    </style>
    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-surface font-sans text-ink antialiased">
    @inertia
</body>
</html>
