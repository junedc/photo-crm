<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Design Draft | {{ $tenant->name }}</title>
    @if (! empty($fonts))
        <style>
            @foreach ($fonts as $font)
                @if (! empty($font['url']))
                    @font-face {
                        font-family: '{{ str_replace("'", "\\'", $font['family']) }}';
                        src: url('{{ $font['url'] }}') format('{{ $font['css_format'] }}');
                        font-weight: {{ $font['weight'] }};
                        font-style: {{ $font['style'] }};
                        font-display: swap;
                    }
                @endif
            @endforeach
        </style>
    @endif
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-stone-50">
    <script>
        window.clientPortalDesignProps = @js([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'booking' => [
                'id' => $booking->id,
                'quote_number' => $booking->quote_number,
                'customer_name' => $booking->customer_name,
                'package_name' => $booking->package?->name,
            ],
            'design' => [
                'id' => $design['id'],
                'title' => $design['title'],
                'last_saved_at_label' => $design['last_saved_at_label'],
                'design_data' => $design['design_data'],
            ],
            'routes' => [
                'save' => route('client.portal.design.save', $booking),
                'uploadAsset' => route('client.portal.design.assets.store', $booking),
                'portal' => route('client.portal.index'),
            ],
            'fonts' => $fonts,
        ]);
    </script>
    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-white/10 bg-white/[0.04] px-5 py-4">
            <div class="min-w-0">
                <p class="truncate text-sm text-stone-200">
                    <span class="mr-3 text-[10px] uppercase tracking-[0.32em] text-cyan-200">Client Design Draft</span>
                    <span class="font-semibold text-white">{{ $booking->quote_number ?: 'Booking #'.$booking->id }}</span>
                    <span class="mx-2 text-stone-500">·</span>
                    <span>{{ $booking->customer_name }}</span>
                    @if ($booking->package)
                        <span class="mx-2 text-stone-500">·</span>
                        <span>{{ $booking->package->name }}</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('client.portal.index') }}" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/5">Back to portal</a>
        </div>

        <div id="client-portal-design-app"></div>
    </div>
</body>
</html>
