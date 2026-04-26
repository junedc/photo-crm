<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer task update</title>
</head>
<body style="margin:0;padding:24px;background:#0f172a;color:#e2e8f0;font-family:Arial,sans-serif;">
    <div style="max-width:640px;margin:0 auto;background:#132035;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:24px;">
        <p style="margin:0 0 12px;font-size:12px;letter-spacing:0.25em;text-transform:uppercase;color:#94a3b8;">Customer Task Update</p>
        <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#ffffff;">{{ $task->task_name }}</h1>

        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;">
            Booking:
            <strong>{{ $booking->quote_number ? $booking->quote_number.' - '.($booking->entry_name ?: $booking->customer_name) : ($booking->entry_name ?: $booking->customer_name) }}</strong>
        </p>
        <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
            Customer:
            <strong>{{ $booking->customer_name ?: $booking->customer_email }}</strong>
        </p>

        <div style="margin:0 0 16px;padding:16px;border-radius:12px;background:#0b1220;border:1px solid rgba(255,255,255,0.08);">
            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.2em;text-transform:uppercase;color:#94a3b8;">Reply</p>
            <p style="margin:0;font-size:14px;line-height:1.7;color:#f8fafc;">{{ filled($note) ? $note : 'The customer submitted an update without a text reply.' }}</p>
        </div>

        @if (! empty($responseAttachments))
            <div style="margin:0 0 16px;">
                <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.2em;text-transform:uppercase;color:#94a3b8;">Attachments</p>
                <ul style="margin:0;padding-left:18px;">
                    @foreach ($responseAttachments as $attachment)
                        <li style="margin:0 0 6px;font-size:14px;line-height:1.6;">
                            <a href="{{ $attachment['url'] ?? '#' }}" style="color:#67e8f9;text-decoration:none;">{{ $attachment['name'] ?? 'Attachment' }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($actionUrl)
            <p style="margin:24px 0 0;">
                <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 18px;border-radius:999px;background:#67e8f9;color:#082f49;font-size:14px;font-weight:700;text-decoration:none;">Open booking</a>
            </p>
        @endif
    </div>
</body>
</html>
