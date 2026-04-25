<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:32px;background:#f4f1ea;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.08);">
        <tr>
            <td style="padding:32px;background:linear-gradient(135deg,#0f172a 0%,#0891b2 100%);color:#ffffff;">
                <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.24em;text-transform:uppercase;color:#cffafe;">Client Portal</p>
                <h1 style="margin:0;font-size:30px;line-height:1.2;">Your event portal is ready</h1>
                <p style="margin:14px 0 0;font-size:15px;line-height:1.7;color:#e0f2fe;">
                    Congratulations on your event, and we are extremely glad that we are part of it.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:32px;">
                <p style="margin:0 0 18px;font-size:16px;line-height:1.7;">Hello {{ $access->customer_name ?: $booking->customer_name }},</p>
                <p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#475569;">
                    We have opened your {{ $tenant->name }} client portal so you can review your previous and current bookings in one place.
                </p>
                <p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#475569;">
                    Use the button below to visit the portal. For security, we will ask you to verify your access with a six-digit code sent to this same email address.
                </p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                    <tr>
                        <td align="center">
                            <a href="{{ $portalUrl }}" style="display:inline-block;padding:14px 28px;background:#0891b2;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;border-radius:999px;">
                                Open Client Portal
                            </a>
                        </td>
                    </tr>
                </table>
                <p style="margin:0;font-size:14px;line-height:1.7;color:#64748b;">
                    Portal link: <a href="{{ $portalUrl }}" style="color:#0891b2;text-decoration:none;">{{ $portalUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
