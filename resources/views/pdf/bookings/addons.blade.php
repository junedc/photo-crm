<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quote {{ $booking->quote_number ?: 'Draft' }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; line-height: 1.45; }
        .header-table, .meta-table, .items-table, .summary-table, .right-meta-table { width: 100%; border-collapse: collapse; }
        .header-title { font-size: 24px; font-weight: 700; letter-spacing: 0.2em; color: #111827; }
        .business-block { text-align: right; font-size: 10.5px; line-height: 1.6; }
        .logo-wrap { margin-bottom: 12px; }
        .logo-wrap img { max-width: 170px; max-height: 68px; width: auto; height: auto; }
        .section-gap { height: 18px; }
        .meta-table td { vertical-align: top; padding: 0; }
        .right-meta-table td { padding: 0 0 8px; vertical-align: top; }
        .right-meta-table .meta-label { width: 42%; font-size: 9px; letter-spacing: 0.16em; text-transform: uppercase; color: #6b7280; }
        .right-meta-table .meta-value { font-size: 11px; color: #111827; font-weight: 600; text-align: right; }
        .label { font-size: 9px; letter-spacing: 0.18em; text-transform: uppercase; color: #6b7280; padding-bottom: 3px; }
        .value { font-size: 11px; color: #111827; padding-bottom: 10px; }
        .items-table { margin-top: 10px; border: 1px solid #d1d5db; }
        .items-table thead th {
            background: #f3f4f6;
            color: #4b5563;
            font-size: 9px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid #d1d5db;
        }
        .items-table tbody td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .desc-title { font-weight: 700; color: #111827; }
        .desc-line { margin-top: 3px; color: #6b7280; line-height: 1.35; }
        .desc-line.bullet { padding-left: 10px; text-indent: -10px; }
        .summary-wrap { width: 100%; margin-top: 16px; }
        .summary-table { width: 38%; margin-left: auto; }
        .summary-table td { padding: 6px 0; font-size: 11px; }
        .summary-table .summary-label { color: #4b5563; }
        .summary-table .summary-value { text-align: right; color: #111827; }
        .summary-table .grand-total td {
            border-top: 1px solid #111827;
            padding-top: 8px;
            font-weight: 700;
        }
        .note-block {
            margin-top: 22px;
            padding-top: 12px;
            border-top: 1px solid #d1d5db;
            font-size: 10px;
            color: #4b5563;
        }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 56%; vertical-align: top;">
                @if ($logo_data_uri)
                    <div class="logo-wrap">
                        <img src="{{ $logo_data_uri }}" alt="{{ $tenant?->name ?: 'Logo' }}">
                    </div>
                @endif
                <div class="header-title">QUOTE</div>
            </td>
            <td style="width: 44%; vertical-align: top;">
                <table class="right-meta-table">
                    <tr>
                        <td class="meta-label">Date</td>
                        <td class="meta-value">{{ $quote_date?->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Expiry</td>
                        <td class="meta-value">{{ $expiry_date?->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Quote Number</td>
                        <td class="meta-value">{{ $booking->quote_number ?: 'Draft' }}</td>
                    </tr>
                </table>
                <div class="business-block" style="margin-top: 8px;">
                    @foreach ($business_lines as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    <div class="section-gap"></div>

    <table class="meta-table">
        <tr>
            <td style="width: 100%;">
                <div class="label">Customer</div>
                <div class="value">{{ $customer_name ?: 'Not provided' }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 58%;">Description</th>
                <th style="width: 12%;" class="text-right">Quantity</th>
                <th style="width: 12%;" class="text-right">Unit Price</th>
                <th style="width: 8%;" class="text-right">Discount</th>
                <th style="width: 10%;" class="text-right">Amount {{ $currency_code }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($line_items as $item)
                <tr>
                    <td>
                        <div class="desc-title">{{ $item['description_title'] }}</div>
                        @if (($item['type'] ?? null) !== 'add_on')
                            @foreach (($item['description_lines'] ?? []) as $line)
                                <div class="desc-line{{ str_starts_with($line, '- ') ? ' bullet' : '' }}">{{ $line }}</div>
                            @endforeach
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) ($item['quantity'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ $item['discount_label'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float) ($item['amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-wrap">
        <table class="summary-table">
            <tr>
                <td class="summary-label">Subtotal</td>
                <td class="summary-value">{{ number_format((float) ($subtotal ?? 0), 2) }}</td>
            </tr>
            @if ((float) ($discount['amount'] ?? 0) > 0)
                <tr>
                    <td class="summary-label">
                        Discount
                        @if (filled($discount['code'] ?? null))
                            <span class="muted">({{ $discount['code'] }})</span>
                        @endif
                    </td>
                    <td class="summary-value">-{{ number_format((float) ($discount['amount'] ?? 0), 2) }}</td>
                </tr>
            @endif
            <tr class="grand-total">
                <td class="summary-label">TOTAL {{ $currency_code }}</td>
                <td class="summary-value">{{ number_format((float) ($booking_total ?? 0), 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="note-block">
        <div>Please review the attached booking terms and conditions before confirming this quote.</div>
        @if (filled($booking->notes))
            <div style="margin-top: 8px;"><strong>Notes:</strong> {{ $booking->notes }}</div>
        @endif
    </div>
</body>
</html>
