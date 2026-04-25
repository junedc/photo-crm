@php
    use App\Support\DateFormatter;
    $selectedAddOns = $booking->relationLoaded('addOns') && $booking->addOns->isNotEmpty()
        ? $booking->addOns->pluck('name')->join(', ')
        : 'None selected';
    $tenantLogoUrl = $booking->tenant?->logo_path ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($booking->tenant->logo_path)) : null;
    $packagePrice = (float) ($booking->package_price ?? $booking->package?->base_price ?? 0);
    $addOnTotal = (float) ($booking->relationLoaded('addOns') ? $booking->addOns->sum('unit_price') : 0);
    $travelFee = (float) ($booking->travel_fee ?? 0);
    $discountAmount = (float) ($booking->discount_amount ?? 0);
    $bookingTotal = max(0, $packagePrice + $addOnTotal + $travelFee - $discountAmount);

    $formatTime = fn (?string $time): string => DateFormatter::time($time, 'N/A');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Quote Request</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f1e8; font-family: Arial, Helvetica, sans-serif; color: #1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f5f1e8; margin: 0; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 720px;">
                    <tr>
                        <td style="padding-bottom: 16px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, #111827 0%, #ea580c 100%); border-radius: 24px 24px 0 0;">
                                <tr>
                                    <td style="padding: 30px 32px;">
                                        @if ($tenantLogoUrl)
                                            <img src="{{ $tenantLogoUrl }}" alt="{{ $booking->tenant?->name ?? 'MemoShot' }} logo" width="56" height="56" style="display: block; width: 56px; height: 56px; border-radius: 16px; object-fit: cover; margin-bottom: 14px;">
                                        @endif
                                        <p style="margin: 0 0 8px; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #fed7aa;">
                                            MemoShot Admin Alert
                                        </p>
                                        <h1 style="margin: 0; font-size: 30px; line-height: 1.2; color: #ffffff;">
                                            New quote request
                                        </h1>
                                        <p style="margin: 12px 0 0; font-size: 15px; line-height: 1.7; color: #ffedd5;">
                                            A customer has requested a new quote for {{ $booking->tenant?->name ?? 'your workspace' }}.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 0 0 24px 24px; overflow: hidden; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);">
                                <tr>
                                    <td style="padding: 32px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px; background-color: #fff7ed; border: 1px solid #fdba74; border-radius: 18px;">
                                            <tr>
                                                <td style="padding: 24px;">
                                                    <p style="margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #c2410c;">
                                                        Quote Snapshot
                                                    </p>
                                                    <p style="margin: 0; font-size: 30px; font-weight: bold; color: #111827;">
                                                        {{ $booking->package?->name ?? 'No package selected' }}
                                                    </p>
                                                    <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: #7c2d12;">
                                                        {{ $booking->customer_name }} requested pricing for {{ DateFormatter::date($booking->event_date, 'an unconfirmed date') }}.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px;">
                                            <tr>
                                                <td valign="top" width="50%" style="padding: 0 10px 16px 0;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px;">
                                                        <tr>
                                                            <td style="padding: 22px;">
                                                                <h2 style="margin: 0 0 14px; font-size: 17px; color: #111827;">
                                                                    Customer Details
                                                                </h2>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Name:</strong> {{ $booking->customer_name }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Email:</strong> {{ $booking->customer_email }}
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Phone:</strong> {{ $booking->customer_phone }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td valign="top" width="50%" style="padding: 0 0 16px 10px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px;">
                                                        <tr>
                                                            <td style="padding: 22px;">
                                                                <h2 style="margin: 0 0 14px; font-size: 17px; color: #111827;">
                                                                    Booking Details
                                                                </h2>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Event date:</strong> {{ DateFormatter::date($booking->event_date, 'Not provided') }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Start hour:</strong> {{ $formatTime($booking->start_time) }}
                                                                </p>
                                                                <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6;">
                                                                    <strong>End hour:</strong> {{ $formatTime($booking->end_time) }}
                                                                </p>
                                                                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                                                                    <strong>Total hours:</strong> {{ number_format((float) ($booking->total_hours ?? 0), 2) }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 18px;">
                                            <tr>
                                                <td style="padding: 22px;">
                                                    <h2 style="margin: 0 0 14px; font-size: 17px; color: #1d4ed8;">
                                                        Quote Breakdown
                                                    </h2>
                                                    <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6; color: #1e3a8a;">
                                                        <strong>Package:</strong> {{ $booking->package?->name ?? 'No package selected' }}
                                                    </p>
                                                    <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6; color: #1e3a8a;">
                                                        <strong>Add-ons:</strong> {{ $selectedAddOns }}
                                                    </p>
                                                    <p style="margin: 0 0 10px; font-size: 14px; line-height: 1.6; color: #1e3a8a;">
                                                        <strong>Location:</strong> {{ $booking->event_location ?: 'Not provided' }}
                                                    </p>
                                                    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #1e3a8a;">
                                                        <strong>Travel fee:</strong> ${{ number_format((float) ($booking->travel_fee ?? 0), 2) }}
                                                        ({{ number_format((float) ($booking->travel_distance_km ?? 0), 2) }} km)
                                                    </p>
                                                    <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: #1e3a8a;">
                                                        <strong>Package price:</strong> ${{ number_format($packagePrice, 2) }}
                                                    </p>
                                                    @if ($discountAmount > 0)
                                                        <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: #1e3a8a;">
                                                            <strong>Discount:</strong>
                                                            {{ $booking->discount?->code ? $booking->discount->code.' - '.$booking->discount->name : ($booking->discount?->name ?? 'Applied discount') }}
                                                            <span style="color: #047857;">(-${{ number_format($discountAmount, 2) }})</span>
                                                        </p>
                                                    @endif
                                                    <p style="margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: #1e3a8a;">
                                                        <strong>Quoted total:</strong> ${{ number_format($bookingTotal, 2) }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        @if ($booking->notes)
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom: 24px; background-color: #faf5ff; border: 1px solid #d8b4fe; border-radius: 18px;">
                                                <tr>
                                                    <td style="padding: 22px;">
                                                        <h2 style="margin: 0 0 12px; font-size: 17px; color: #7c3aed;">
                                                            Customer Notes
                                                        </h2>
                                                        <p style="margin: 0; font-size: 14px; line-height: 1.7; color: #5b21b6;">
                                                            {{ $booking->notes }}
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        @if ($addonsPdf ?? false)
                                            <p style="margin: 0; font-size: 14px; line-height: 1.7; color: #475569;">
                                                A booking PDF is attached with the selected package, add-ons, descriptions, and images for quick review.
                                            </p>
                                        @endif
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
                                            This admin alert was generated from the public quote request form and includes the latest customer selections.
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
