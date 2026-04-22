<!DOCTYPE html>
<html lang="en">
    <body style="margin: 0; background: #f7f3ed; font-family: Arial, sans-serif; color: #292524;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f7f3ed; padding: 32px 16px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 560px; background: #ffffff; border: 1px solid #e7dfd5; border-radius: 24px; overflow: hidden;">
                        <tr>
                            <td style="padding: 28px;">
                                <p style="margin: 0 0 10px; color: #d97706; font-size: 12px; font-weight: bold; letter-spacing: 0.22em; text-transform: uppercase;">Subscription Reminder</p>
                                <h1 style="margin: 0 0 16px; font-size: 24px;">Upcoming platform payment</h1>
                                <p style="margin: 0 0 16px; line-height: 1.6;">Hi {{ $tenant->name }},</p>
                                <p style="margin: 0 0 16px; line-height: 1.6;">
                                    Your {{ $charge->subscription_name }} subscription payment of
                                    <strong>{{ strtoupper($charge->currency) }} {{ number_format((float) $charge->amount, 2) }}</strong>
                                    is scheduled for {{ $charge->period_starts_at?->format('d M Y') }}.
                                </p>
                                <p style="margin: 0; line-height: 1.6;">We will automatically charge the saved payment method on the due date.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
