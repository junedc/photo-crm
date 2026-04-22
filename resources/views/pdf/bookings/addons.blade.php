<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Booking details for {{ $package['name'] ?? 'package' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 12px; margin: 24px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin: 0 0 6px; }
        p { margin: 0 0 8px; }
        .muted { color: #666; }
        .card { border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-top: 18px; page-break-inside: avoid; }
        .image { margin-bottom: 12px; text-align: center; }
        .image img { display: block; width: auto; height: auto; max-width: 100%; max-height: 260px; margin: 0 auto; border-radius: 8px; }
        .meta { font-size: 11px; color: #555; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Booking Package and Add-ons</h1>
    <p class="muted">Booking for {{ $booking->customer_name }} on {{ $booking->event_date?->format('d M Y') }}.</p>
    <p class="muted">{{ $tenant?->name ?? 'MemoShot' }}</p>
    <p class="muted">
        {{ $booking->start_time ? \Illuminate\Support\Carbon::createFromFormat('H:i:s', strlen($booking->start_time) === 5 ? $booking->start_time.':00' : $booking->start_time)->format('g:i A') : 'N/A' }}
        to
        {{ $booking->end_time ? \Illuminate\Support\Carbon::createFromFormat('H:i:s', strlen($booking->end_time) === 5 ? $booking->end_time.':00' : $booking->end_time)->format('g:i A') : 'N/A' }}
        ({{ number_format((float) ($booking->total_hours ?? 0), 2) }} hours)
    </p>

    <section class="card">
        <h2>Selected Package: {{ $package['name'] }}</h2>
        <p class="meta">Base price: ${{ number_format((float) $package['price'], 2) }}</p>

        @if ($package['image_data_uri'])
            <div class="image">
                <img src="{{ $package['image_data_uri'] }}" alt="{{ $package['name'] }}">
            </div>
        @endif

        <p>{{ $package['description'] ?: 'No package description provided.' }}</p>
        <p class="meta" style="margin-top: 12px;">
            Travel distance: {{ number_format((float) ($travel['distance_km'] ?? 0), 2) }} km |
            Travel fee: ${{ number_format((float) ($travel['fee'] ?? 0), 2) }}
        </p>
        @if (($discount['amount'] ?? 0) > 0)
            <p class="meta">
                Discount: {{ ($discount['code'] ?? null) ? $discount['code'].' - '.($discount['name'] ?? '') : ($discount['name'] ?? 'Applied discount') }} |
                Savings: -${{ number_format((float) ($discount['amount'] ?? 0), 2) }}
            </p>
        @endif
        <p class="meta" style="font-weight: bold;">
            Booking total: ${{ number_format((float) ($booking_total ?? 0), 2) }}
        </p>
    </section>

    @if ($addons->isNotEmpty())
        @foreach ($addons as $addon)
            <section class="card">
                <h2>{{ $addon['name'] }}</h2>
                <p class="meta">
                    @if ($addon['product_code'])
                        Code: {{ $addon['product_code'] }}
                    @endif
                    @if ($addon['category'])
                        @if ($addon['product_code'])
                            |
                        @endif
                        Category: {{ $addon['category'] }}
                    @endif
                    @if ($addon['price'] !== null)
                        @if ($addon['product_code'] || $addon['category'])
                            |
                        @endif
                        Price: ${{ number_format((float) $addon['price'], 2) }}
                    @endif
                    @if ($addon['duration'])
                        @if ($addon['product_code'] || $addon['category'] || $addon['price'] !== null)
                            |
                        @endif
                        Duration: {{ $addon['duration'] }}
                    @endif
                </p>

                @if ($addon['image_data_uri'])
                    <div class="image">
                        <img src="{{ $addon['image_data_uri'] }}" alt="{{ $addon['name'] }}">
                    </div>
                @endif

                <p>{{ $addon['description'] ?: 'No description provided.' }}</p>
            </section>
        @endforeach
    @else
        <section class="card">
            <h2>No Add-ons Selected</h2>
            <p>No optional add-ons were selected for this booking.</p>
        </section>
    @endif
</body>
</html>
