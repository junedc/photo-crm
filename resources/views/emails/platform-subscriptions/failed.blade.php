<!DOCTYPE html>
<html lang="en">
    <body style="margin: 0; background: #f7f3ed; font-family: Arial, sans-serif; color: #292524;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f7f3ed; padding: 32px 16px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 560px; background: #ffffff; border: 1px solid #fecaca; border-radius: 24px; overflow: hidden;">
                        <tr>
                            <td style="padding: 28px;">
                                <p style="margin: 0 0 10px; color: #dc2626; font-size: 12px; font-weight: bold; letter-spacing: 0.22em; text-transform: uppercase;">Payment Failed</p>
                                <h1 style="margin: 0 0 16px; font-size: 24px;">We could not process your subscription payment</h1>
                                <p style="margin: 0 0 16px; line-height: 1.6;">Hi {{ $tenant->name }},</p>
                                <p style="margin: 0 0 16px; line-height: 1.6;">
                                    We were unable to process your {{ $charge->subscription_name }} subscription payment of
                                    <strong>{{ strtoupper($charge->currency) }} {{ number_format((float) $charge->amount, 2) }}</strong>.
                                </p>
                                <p style="margin: 0 0 16px; line-height: 1.6;">
                                    We will retry the payment in the next {{ $retryDays }} days.
                                </p>
                                @if ($charge->last_payment_error)
                                    <p style="margin: 0; color: #78716c; font-size: 13px; line-height: 1.5;">Stripe message: {{ $charge->last_payment_error }}</p>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
