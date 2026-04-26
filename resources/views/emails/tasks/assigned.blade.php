<!DOCTYPE html>
@php use App\Support\DateFormatter; @endphp
<html lang="en">
<body style="margin:0;padding:32px;background:#f4f1ea;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.08);">
        <tr>
            <td style="padding:32px;background:linear-gradient(135deg,#0f172a 0%,#0891b2 100%);color:#ffffff;">
                <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.24em;text-transform:uppercase;color:#cffafe;">Task Assignment</p>
                <h1 style="margin:0;font-size:30px;line-height:1.2;">A task has been assigned to you</h1>
                <p style="margin:14px 0 0;font-size:15px;line-height:1.7;color:#e0f2fe;">
                    {{ $tenant->name }} added or updated a task and assigned it to you.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:32px;">
                <p style="margin:0 0 18px;font-size:16px;line-height:1.7;">Hello {{ $assigneeName }},</p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 24px;">
                    <tr>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;">Task</td>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#0f172a;text-align:right;">{{ $task->task_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;">Status</td>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#0f172a;text-align:right;">{{ $task->status?->label() ?: 'No status' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;">Due Date</td>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#0f172a;text-align:right;">{{ DateFormatter::date($task->due_date, 'Not set') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;">Hours</td>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#0f172a;text-align:right;">{{ $task->task_duration_hours ?: '0.00' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;">Booking</td>
                        <td style="padding:12px 0;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#0f172a;text-align:right;">{{ $task->booking?->quote_number ?: ($task->booking?->customer_name ?: 'General task') }}</td>
                    </tr>
                </table>

                @if (filled($task->remarks))
                    <div style="padding:18px 20px;border-radius:18px;background:#f8fafc;border:1px solid #e2e8f0;">
                        <p style="margin:0 0 10px;font-size:12px;letter-spacing:0.2em;text-transform:uppercase;color:#64748b;">Remarks</p>
                        <p style="margin:0;font-size:15px;line-height:1.7;color:#334155;">{{ $task->remarks }}</p>
                    </div>
                @endif

                @if (filled($actionUrl))
                    <div style="margin-top:24px;">
                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 22px;border-radius:999px;background:#0891b2;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                            {{ $actionLabel }}
                        </a>
                    </div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
