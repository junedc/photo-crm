<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:32px;background:#0c0a09;color:#e7e5e4;font-family:Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;padding:32px;border:1px solid rgba(255,255,255,0.08);border-radius:24px;background:#1c1917;">
        <p style="margin:0 0 12px;font-size:12px;letter-spacing:0.3em;text-transform:uppercase;color:#67e8f9;">
            Client Portal
        </p>
        <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;color:#fafaf9;">
            Use this code to open your portal
        </h1>
        <p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#d6d3d1;">
            Hi {{ $customerName }}, enter this code to access your {{ $tenant->name }} client portal. It expires at {{ $expiresAt->format('g:i A') }}.
        </p>
        <div style="margin:0 0 24px;padding:18px 20px;border-radius:18px;background:#0c0a09;border:1px solid rgba(103,232,249,0.25);text-align:center;font-size:32px;letter-spacing:0.35em;color:#cffafe;">
            {{ $code }}
        </div>
        <p style="margin:0;font-size:14px;line-height:1.7;color:#a8a29e;">
            If you did not try to open the client portal, you can ignore this email.
        </p>
    </div>
</body>
</html>
