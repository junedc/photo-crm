@php
    use App\Support\DateFormatter;
    $acceptUrl = route('quotes.respond', ['booking' => $booking->quote_token, 'response' => 'accept']);
    $rejectUrl = route('quotes.respond', ['booking' => $booking->quote_token, 'response' => 'reject']);
    $selectedAddOns = $booking->relationLoaded('addOns') && $booking->addOns->isNotEmpty()
        ? $booking->addOns->pluck('name')->join(', ')
        : 'None selected';
    $tenantLogoUrl = $booking->tenant?->logo_path ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($booking->tenant->logo_path)) : null;
    $packagePrice = (float) ($booking->package_price ?? $booking->package?->base_price ?? 0);
    $addOnTotal = (float) ($booking->relationLoaded('addOns') ? $booking->addOns->sum(fn ($addOn) => $addOn->discountedUnitPriceForBookingSelection(
        $addOn->pivot?->discount_type,
        $addOn->pivot?->discount_value,
        (float) ($addOn->pivot?->discount_percentage ?? 0),
    )) : 0);
    $travelFee = (float) ($booking->travel_fee ?? 0);
    $discountAmount = (float) ($booking->discount_amount ?? 0);
    $bookingTotal = max(0, $packagePrice + $addOnTotal + $travelFee - $discountAmount);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Quote Is Ready</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f1ea; font-family: Arial, Helvetica, sans-serif; color: #1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f4f1ea; margin: 0; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 700px;">
                    <tr>
                        <td style="padding-bottom: 16px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #0f172a 0%, #0891b2 100%); border-radius: 20px 20px 0 0;">
                                <tr>
                                    <td style="padding: 28px 32px;">
                                        @if ($tenantLogoUrl)
                                            <img src="{{ $tenantLogoUrl }}" alt="{{ $booking->tenant?->name ?? 'MemoShot' }} logo" width="56" height="56" style="display: block; width: 56px; height: 56px; border-radius: 16px; object-fit: cover; margin-bottom: 14px;">
                                        @endif
                                        <p style="margin: 0 0 8px; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #cffafe;">
                                            MemoShot Quote
                                        </p>
                                        <h1 style="margin: 0; font-size: 30px; line-height: 1.2; color: #ffffff;">
                                            Your quote is ready
                                        </h1>
                                        <p style="margin: 12px 0 0; font-size: 15px; line-height: 1.6; color: #e0f2fe;">
                                            {{ $booking->tenant?->name ?? 'MemoShot' }} prepared a quote for {{ $booking->customer_name }}
                                        </p>
                                        <p style="margin: 10px 0 0; font-size: 13px; line-height: 1.6; color: #bae6fd;">
                                            Quote number {{ $booking->quote_number }}
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
                                            Thank you for requesting a quote. We have put together your package details below, and you can let us know whether you would like to move forward.
                                        </p>

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px; background-color: #ecfeff; border: 1px solid #a5f3fc; border-radius: 16px;">
                                            <tr>
                                                <td style="padding: 24px;">
                                                    <p style="margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #0f766e;">
                                                        Selected Package
                                                    </p>
                                                    <p style="margin: 0; font-size: 30px; font-weight: bold; color: #0f172a;">
                                                        {{ $booking->package?->name ?? 'No package selected' }}
                                                    </p>
                                                    <p style="margin: 8px 0 0; font-size: 14px; color: #475569;">
                                                        Event date {{ DateFormatter::date($booking->event_date, 'to be confirmed') }}
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
                                                                    Quote Details
                                                                </h2>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Quote number:</strong> {{ $booking->quote_number }}
                                                                </p>
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
                                                                    <strong>Add-ons:</strong> {{ $selectedAddOns }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Package price:</strong> ${{ number_format($packagePrice, 2) }}
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Travel fee:</strong> ${{ number_format((float) ($booking->travel_fee ?? 0), 2) }}
                                                                    ({{ number_format((float) ($booking->travel_distance_km ?? 0), 2) }} km)
                                                                </p>
                                                                @if ($discountAmount > 0)
                                                                    <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.6;">
                                                                        <strong>Discount:</strong>
                                                                        {{ $booking->discount?->code ? $booking->discount->code.' - '.$booking->discount->name : ($booking->discount?->name ?? 'Applied discount') }}
                                                                        <span style="color: #047857;">(-${{ number_format($discountAmount, 2) }})</span>
                                                                    </p>
                                                                @endif
                                                                <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Quoted total:</strong> ${{ number_format($bookingTotal, 2) }}
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
                                                                    Contact Details
                                                                </h2>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Email:</strong> {{ $booking->customer_email }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Phone:</strong> {{ $booking->customer_phone }}
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Response status:</strong> Pending
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        @if ($booking->notes)
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px; background-color: #fff7ed; border: 1px solid #fed7aa; border-radius: 16px;">
                                                <tr>
                                                    <td style="padding: 22px;">
                                                        <h2 style="margin: 0 0 12px; font-size: 17px; color: #9a3412;">
                                                            Your Notes
                                                        </h2>
                                                        <p style="margin: 0; font-size: 14px; line-height: 1.7; color: #7c2d12;">
                                                            {{ $booking->notes }}
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 10px;">
                                            <tr>
                                                <td align="center" style="padding: 8px 0 14px;">
                                                    <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 14px 28px; background-color: #0f766e; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: bold; border-radius: 999px; margin-right: 8px;">
                                                        Accept Quote
                                                    </a>
                                                    <a href="{{ $rejectUrl }}" style="display: inline-block; padding: 14px 28px; background-color: #ffffff; color: #b91c1c; text-decoration: none; font-size: 15px; font-weight: bold; border-radius: 999px; border: 1px solid #fecaca;">
                                                        Reject Quote
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 0 0 18px; font-size: 14px; line-height: 1.7; color: #475569;">
                                            If the buttons above do not work in your email client, use these links:
                                        </p>
                                        <p style="margin: 0 0 6px; font-size: 14px; line-height: 1.7;">
                                            <a href="{{ $acceptUrl }}" style="color: #0f766e; text-decoration: none;">{{ $acceptUrl }}</a>
                                        </p>
                                        <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.7;">
                                            <a href="{{ $rejectUrl }}" style="color: #b91c1c; text-decoration: none;">{{ $rejectUrl }}</a>
                                        </p>

                                        @if ($addonsPdf ?? false)
                                            <p style="margin: 0 0 16px; font-size: 14px; line-height: 1.7; color: #475569;">
                                                We have attached a PDF with your selected package details and any chosen add-ons, including descriptions and images.
                                            </p>
                                        @endif

                                        <p style="margin: 0; font-size: 14px; line-height: 1.7; color: #475569;">
                                            If you have any questions before deciding, just reply to this email and we will be happy to help.
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
                                            This quote was sent to {{ $booking->customer_email }} for follow-up and customer response tracking.
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
