<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $props['tenant']['name'] ?? 'MemoShot' }} Admin</title>
        @if (!empty($props['tenant']['logo_url']))
            <link rel="icon" type="image/png" href="{{ url($props['tenant']['logo_url']) }}">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50" data-theme="{{ $props['tenant']['theme'] ?? 'dark' }}">
        <script>
            window.googleMapsApiKey = @js($props['tenant']['google_maps_api_key'] ?? env('VITE_GOOGLE_MAPS_API_KEY', ''));
            window.adminPage = @js($page);
            window.adminProps = @js(array_merge($props, [
                'csrfToken' => csrf_token(),
                'flash' => [
                    'status' => session('status'),
                    'errors' => $errors->all(),
                    'old' => session()->getOldInput(),
                ],
            ]));
        </script>
        <div id="app" data-page="{{ $page }}"></div>
    </body>
</html>
