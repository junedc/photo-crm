<!DOCTYPE html>
<html lang="en">
    <body style="margin:0;padding:32px;background:#07111f;color:#e5eefb;font-family:Arial,sans-serif;">
        <div style="max-width:560px;margin:0 auto;padding:32px;border:1px solid rgba(125,211,252,0.18);border-radius:24px;background:#0f172a;">
            <p style="margin:0 0 12px;font-size:12px;letter-spacing:0.3em;text-transform:uppercase;color:#7dd3fc;">
                Platform Admin
            </p>
            <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;color:#f8fafc;">
                Use this code to access Photobooth CRM admin
            </h1>
            <p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#cbd5e1;">
                Enter the code below to sign in as {{ $email }}. It expires at {{ $expiresAt->format('g:i A') }}.
            </p>
            <div style="margin:0 0 24px;padding:18px 20px;border-radius:18px;background:#020617;border:1px solid rgba(125,211,252,0.32);text-align:center;font-size:32px;letter-spacing:0.35em;color:#bae6fd;">
                {{ $code }}
            </div>
            <p style="margin:0;font-size:14px;line-height:1.7;color:#94a3b8;">
                If you did not try to access platform admin, you can ignore this email.
            </p>
        </div>
    </body>
</html>
