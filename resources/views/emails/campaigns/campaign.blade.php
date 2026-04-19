@php
    $tenant = $campaign->tenant;
    $tenantLogoUrl = $tenant?->logo_path ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($tenant->logo_path)) : null;
    $headline = $campaign->headline ?: $campaign->subject;
    $trackingUrl = route('campaigns.track-open', ['token' => $result->token]);
    $unsubscribeUrl = route('campaigns.unsubscribe', ['token' => $result->token]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->subject }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f6f1e8; font-family: Arial, Helvetica, sans-serif; color: #1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f6f1e8; margin: 0; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 680px;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #111827 0%, #7f1d1d 55%, #f59e0b 100%); border-radius: 24px 24px 0 0; padding: 30px 32px;">
                            @if ($tenantLogoUrl)
                                <img src="{{ $tenantLogoUrl }}" alt="{{ $tenant?->name ?? 'MemoShot' }} logo" width="58" height="58" style="display: block; width: 58px; height: 58px; border-radius: 18px; object-fit: cover; margin-bottom: 16px;">
                            @endif
                            <p style="margin: 0 0 10px; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #fde68a;">
                                {{ $tenant?->name ?? 'MemoShot' }} Campaign
                            </p>
                            <h1 style="margin: 0; font-size: 32px; line-height: 1.15; color: #ffffff;">
                                {{ $headline }}
                            </h1>
                            @if ($campaign->preheader)
                                <p style="margin: 14px 0 0; font-size: 15px; line-height: 1.7; color: #fff7ed;">
                                    {{ $campaign->preheader }}
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 0 0 24px 24px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); overflow: hidden;">
                            <div style="padding: 34px 32px;">
                                <p style="margin: 0 0 18px; font-size: 16px; line-height: 1.7;">
                                    Hello {{ $result->name ?: $result->email }},
                                </p>

                                <div style="font-size: 15px; line-height: 1.8; color: #475569;">
                                    {!! $campaign->body !!}
                                </div>

                                @if ($campaign->button_text && $campaign->button_url)
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top: 28px;">
                                        <tr>
                                            <td align="center">
                                                <a href="{{ $campaign->button_url }}" style="display: inline-block; padding: 14px 28px; background-color: #be123c; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: bold; border-radius: 999px;">
                                                    {{ $campaign->button_text }}
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            </div>
                            <div style="padding: 20px 32px; background-color: #fff7ed; border-top: 1px solid #fed7aa;">
                                @if ($tenantLogoUrl)
                                    <img src="{{ $tenantLogoUrl }}" alt="{{ $tenant?->name ?? 'MemoShot' }} logo" width="34" height="34" style="display: block; width: 34px; height: 34px; border-radius: 12px; object-fit: cover; margin-bottom: 10px;">
                                @endif
                                <p style="margin: 0 0 6px; font-size: 13px; color: #334155;">
                                    {{ $tenant?->name ?? 'MemoShot' }}
                                </p>
                                <p style="margin: 0; font-size: 12px; line-height: 1.6; color: #64748b;">
                                    You are receiving this because you are subscribed to {{ $tenant?->name ?? 'MemoShot' }} updates.
                                    <br><a href="{{ $unsubscribeUrl }}" style="color: #be123c;">Unsubscribe from future campaigns</a>
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>
                <img src="{{ $trackingUrl }}" width="1" height="1" alt="" style="display: block; width: 1px; height: 1px; opacity: 0;">
            </td>
        </tr>
    </table>
</body>
</html>
