<!DOCTYPE html>
@php use App\Support\DateFormatter; @endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number ?: 'Draft' }}</title>
    <style>
        @page { margin: 30px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .header-title { font-size: 24px; font-weight: 700; letter-spacing: 0.18em; color: #111827; }
        .business-block { text-align: right; font-size: 10.5px; line-height: 1.6; color: #374151; }
        .meta-table td { vertical-align: top; padding: 0; }
        .right-meta-table td { padding: 0 0 8px; vertical-align: top; }
        .label { font-size: 9px; letter-spacing: 0.16em; text-transform: uppercase; color: #6b7280; }
        .value { margin-top: 3px; font-size: 11px; color: #111827; font-weight: 600; }
        .section-gap { height: 18px; }
        .panel { border: 1px solid #d1d5db; padding: 12px; }
        .items-table { margin-top: 12px; border: 1px solid #d1d5db; }
        .items-table th {
            background: #f3f4f6;
            color: #4b5563;
            font-size: 9px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            text-align: left;
            padding: 9px 10px;
            border-bottom: 1px solid #d1d5db;
        }
        .items-table td { padding: 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .items-table tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .summary-table { width: 42%; margin-left: auto; margin-top: 16px; }
        .summary-table td { padding: 6px 0; font-size: 11px; }
        .summary-label { color: #4b5563; }
        .summary-value { text-align: right; color: #111827; }
        .grand-total td { border-top: 1px solid #111827; padding-top: 8px; font-weight: 700; font-size: 12px; }
        .muted { color: #6b7280; }
        .status { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 10px; font-weight: 700; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 56%; vertical-align: top;">
                <div class="header-title">INVOICE</div>
                <div style="margin-top: 10px;" class="muted">Generated invoice PDF</div>
            </td>
            <td style="width: 44%; vertical-align: top;">
                <table class="right-meta-table">
                    <tr>
                        <td class="label">Date</td>
                        <td class="text-right value">{{ DateFormatter::date($invoice->issued_at ?: $invoice->created_at) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Invoice</td>
                        <td class="text-right value">{{ $invoice->invoice_number ?: 'Draft' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Status</td>
                        <td class="text-right"><span class="status">{{ str($invoice->status)->replace('_', ' ')->title() }}</span></td>
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
            <td style="width: 50%; padding-right: 10px;">
                <div class="panel">
                    <div class="label">Bill To</div>
                    <div class="value">{{ $booking->customer_name ?: 'Not provided' }}</div>
                    <div class="muted" style="margin-top: 4px;">{{ $booking->customer_email ?: 'No email' }}</div>
                    <div class="muted">{{ $booking->customer_phone ?: 'No phone' }}</div>
                </div>
            </td>
            <td style="width: 50%; padding-left: 10px;">
                <div class="panel">
                    <div class="label">Booking</div>
                    <div class="value">{{ $booking->quote_number ?: 'No quote number' }}</div>
                    <div class="muted" style="margin-top: 4px;">Event: {{ DateFormatter::date($booking->event_date) }}</div>
                    <div class="muted">Location: {{ $booking->event_location ?: 'Not provided' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 18%;">Tax Rate</th>
                <th style="width: 18%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $booking->package?->name ?: 'Booking package' }}</strong>
                    <div class="muted">{{ $line_description }}</div>
                </td>
                <td>{{ $tax_rate_label }}</td>
                <td class="text-right">${{ number_format((float) $invoice->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-label">Amounts are</td>
            <td class="summary-value">{{ $amounts_are_label }}</td>
        </tr>
        <tr>
            <td class="summary-label">Total</td>
            <td class="summary-value">${{ number_format((float) $invoice->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">Paid</td>
            <td class="summary-value">${{ number_format((float) $invoice->amount_paid, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td class="summary-label">Balance Due</td>
            <td class="summary-value">${{ number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2) }}</td>
        </tr>
    </table>

    <div class="section-gap"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Installment</th>
                <th style="width: 22%;">Due Date</th>
                <th style="width: 18%;">Status</th>
                <th style="width: 18%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->installments as $installment)
                <tr>
                    <td>{{ $installment->label }}</td>
                    <td>{{ DateFormatter::date($installment->due_date) }}</td>
                    <td>{{ str($installment->status)->replace('_', ' ')->title() }}</td>
                    <td class="text-right">${{ number_format((float) $installment->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
