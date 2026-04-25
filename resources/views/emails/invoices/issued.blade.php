@php
    use App\Support\DateFormatter;
    $booking = $invoice->booking;
    $balanceDue = max(0, (float) $invoice->total_amount - (float) $invoice->amount_paid);
    $invoiceUrl = route('invoices.show', $invoice);
    $tenantLogoUrl = $booking->tenant?->logo_path ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($booking->tenant->logo_path)) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f1ea; font-family: Arial, Helvetica, sans-serif; color: #1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f4f1ea; margin: 0; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 700px;">
                    <tr>
                        <td style="padding-bottom: 16px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); border-radius: 20px 20px 0 0;">
                                <tr>
                                    <td style="padding: 28px 32px;">
                                        @if ($tenantLogoUrl)
                                            <img src="{{ $tenantLogoUrl }}" alt="{{ $booking->tenant?->name ?? 'MemoShot' }} logo" width="56" height="56" style="display: block; width: 56px; height: 56px; border-radius: 16px; object-fit: cover; margin-bottom: 14px;">
                                        @endif
                                        <p style="margin: 0 0 8px; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #bfdbfe;">
                                            MemoShot Invoice
                                        </p>
                                        <h1 style="margin: 0; font-size: 30px; line-height: 1.2; color: #ffffff;">
                                            {{ $installment->label }} is ready
                                        </h1>
                                        <p style="margin: 12px 0 0; font-size: 15px; line-height: 1.6; color: #dbeafe;">
                                            Booking {{ $invoice->invoice_number }} for {{ $booking->customer_name }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 0 0 20px 20px; overflow: hidden; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);">
                                <tr>
                                    <td style="padding: 32px;">
                                        <p style="margin: 0 0 18px; font-size: 16px; line-height: 1.7;">
                                            Hello {{ $booking->customer_name }},
                                        </p>
                                        <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: #475569;">
                                            Your booking invoice is ready. The next amount due is shown below, together with your event details and a secure payment link.
                                        </p>

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 16px;">
                                            <tr>
                                                <td style="padding: 24px;">
                                                    <p style="margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #1d4ed8;">
                                                        Amount Due Now
                                                    </p>
                                                    <p style="margin: 0; font-size: 34px; font-weight: bold; color: #0f172a;">
                                                        ${{ number_format((float) $installment->amount, 2) }}
                                                    </p>
                                                    <p style="margin: 8px 0 0; font-size: 14px; color: #475569;">
                                                        {{ $installment->label }} due {{ DateFormatter::date($installment->due_date, 'upon receipt') }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px;">
                                            <tr>
                                                <td valign="top" width="50%" style="padding: 0 10px 16px 0;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px;">
                                                        <tr>
                                                            <td style="padding: 22px;">
                                                                <h2 style="margin: 0 0 14px; font-size: 17px; color: #0f172a;">
                                                                    Booking Details
                                                                </h2>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Package:</strong> {{ $booking->package?->name ?? 'No package selected' }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Event date:</strong> {{ DateFormatter::date($booking->event_date, 'Not provided') }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Start hour:</strong> {{ DateFormatter::time($booking->start_time, 'N/A') }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>End hour:</strong> {{ DateFormatter::time($booking->end_time, 'N/A') }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Total hours:</strong> {{ number_format((float) ($booking->total_hours ?? 0), 2) }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Location:</strong> {{ $booking->event_location ?: 'Not provided' }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Travel fee:</strong> ${{ number_format((float) ($booking->travel_fee ?? 0), 2) }}
                                                                    ({{ number_format((float) ($booking->travel_distance_km ?? 0), 2) }} km)
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Add-ons:</strong>
                                                                    {{ $booking->relationLoaded('addOns') && $booking->addOns->isNotEmpty() ? $booking->addOns->pluck('name')->join(', ') : 'None selected' }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td valign="top" width="50%" style="padding: 0 0 16px 10px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px;">
                                                        <tr>
                                                            <td style="padding: 22px;">
                                                                <h2 style="margin: 0 0 14px; font-size: 17px; color: #0f172a;">
                                                                    Invoice Summary
                                                                </h2>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Invoice number:</strong> {{ $invoice->invoice_number }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Total:</strong> ${{ number_format((float) $invoice->total_amount, 2) }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Paid:</strong> ${{ number_format((float) $invoice->amount_paid, 2) }}
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Balance due:</strong> ${{ number_format($balanceDue, 2) }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 16px;">
                                            <tr>
                                                <td align="center" style="padding: 8px 0 24px;">
                                                    <a href="{{ $stripeCheckoutUrl }}" style="display: inline-block; padding: 14px 28px; background-color: #1d4ed8; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: bold; border-radius: 999px;">
                                                        Pay Securely With Stripe
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 0 0 8px; font-size: 14px; line-height: 1.7; color: #475569;">
                                            You can also review your invoice online here:
                                        </p>
                                        <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.7;">
                                            <a href="{{ $invoiceUrl }}" style="color: #1d4ed8; text-decoration: none;">{{ $invoiceUrl }}</a>
                                        </p>

                                        <p style="margin: 0; font-size: 14px; line-height: 1.7; color: #475569;">
                                            If you have any questions about your booking or payment schedule, please reply to this email and we will help you out.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 20px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0;">
                                        @if ($tenantLogoUrl)
                                            <img src="{{ $tenantLogoUrl }}" alt="{{ $booking->tenant?->name ?? 'MemoShot' }} logo" width="36" height="36" style="display: block; width: 36px; height: 36px; border-radius: 12px; object-fit: cover; margin-bottom: 10px;">
                                        @endif
                                        <p style="margin: 0 0 6px; font-size: 13px; color: #334155;">
                                            {{ $booking->tenant?->name ?? 'MemoShot' }}
                                        </p>
                                        <p style="margin: 0; font-size: 12px; line-height: 1.6; color: #64748b;">
                                            This email was sent regarding booking {{ $invoice->invoice_number }}. Payments are processed securely through Stripe.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
