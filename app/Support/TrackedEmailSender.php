<?php

namespace App\Support;

use App\Models\EmailLog;
use App\Models\Tenant;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Throwable;

class TrackedEmailSender
{
    public function send(Mailable $mailable, array|string $to, array|string $cc = [], array $meta = []): array
    {
        $toRecipients = $this->normalizeRecipients($to, 'to');
        $ccRecipients = $this->normalizeRecipients($cc, 'cc');
        $trackingConfig = $this->trackingConfigFor($mailable::class);
        $shouldTrack = (bool) ($trackingConfig['track'] ?? true);
        $tenant = $meta['tenant'] ?? null;
        $attachments = $this->normalizeAttachments($meta['attachments'] ?? []);
        $subject = method_exists($mailable, 'envelope')
            ? ($mailable->envelope()->subject ?? class_basename($mailable))
            : class_basename($mailable);
        $htmlContent = $mailable->render();
        $context = $meta['context'] ?? null;

        try {
            $pending = Mail::to($this->mailAddresses($toRecipients));

            if ($ccRecipients !== []) {
                $pending->cc($this->mailAddresses($ccRecipients));
            }

            $pending->send($mailable);

            if (! $shouldTrack) {
                return [];
            }

            return $this->storeLogs(
                array_merge($toRecipients, $ccRecipients),
                $tenant,
                $subject,
                $htmlContent,
                $mailable::class,
                $context,
                $attachments,
                'sent',
                null,
            );
        } catch (Throwable $throwable) {
            if (! $shouldTrack) {
                throw $throwable;
            }

            $this->storeLogs(
                array_merge($toRecipients, $ccRecipients),
                $tenant,
                $subject,
                $htmlContent,
                $mailable::class,
                $context,
                $attachments,
                'failed',
                $throwable->getMessage(),
            );

            throw $throwable;
        }
    }

    public static function trackingConfig(string $mailableClass): array
    {
        return (array) config("email-tracking.mailables.{$mailableClass}", []);
    }

    public static function trackingTitle(?string $mailableClass, ?string $fallback = null): ?string
    {
        if (! is_string($mailableClass) || $mailableClass === '') {
            return $fallback;
        }

        $title = static::trackingConfig($mailableClass)['title'] ?? null;

        return is_string($title) && $title !== '' ? $title : $fallback;
    }

    public static function trackingCode(?string $mailableClass, ?string $fallback = null): ?string
    {
        $title = static::trackingTitle($mailableClass, $fallback);

        if (! is_string($title) || trim($title) === '') {
            return $fallback;
        }

        $letters = collect(preg_split('/[^A-Za-z0-9]+/', $title) ?: [])
            ->filter(fn ($part) => $part !== '')
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : $fallback;
    }

    public function resendLog(EmailLog $emailLog): EmailLog
    {
        try {
            Mail::html($emailLog->html_content, function ($message) use ($emailLog): void {
                $message->to($emailLog->recipient_email, $emailLog->recipient_name ?: null)
                    ->subject($emailLog->subject);

                foreach ($emailLog->attachments ?? [] as $attachment) {
                    if (! is_array($attachment) || blank($attachment['content'] ?? null) || blank($attachment['name'] ?? null)) {
                        continue;
                    }

                    $message->attachData(
                        base64_decode((string) $attachment['content']),
                        (string) $attachment['name'],
                        ['mime' => (string) ($attachment['mime'] ?? 'application/octet-stream')],
                    );
                }
            });

            return EmailLog::query()->create([
                'tenant_id' => $emailLog->tenant_id,
                'recipient_email' => $emailLog->recipient_email,
                'recipient_name' => $emailLog->recipient_name,
                'recipient_type' => 'resend',
                'subject' => $emailLog->subject,
                'html_content' => $emailLog->html_content,
                'text_content' => $emailLog->text_content,
                'attachments' => $emailLog->attachments,
                'mailable_class' => $emailLog->mailable_class,
                'context_type' => $emailLog->context_type,
                'context_id' => $emailLog->context_id,
                'status' => 'sent',
                'error_message' => null,
                'related_email_log_id' => $emailLog->id,
                'sent_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            return EmailLog::query()->create([
                'tenant_id' => $emailLog->tenant_id,
                'recipient_email' => $emailLog->recipient_email,
                'recipient_name' => $emailLog->recipient_name,
                'recipient_type' => 'resend',
                'subject' => $emailLog->subject,
                'html_content' => $emailLog->html_content,
                'text_content' => $emailLog->text_content,
                'attachments' => $emailLog->attachments,
                'mailable_class' => $emailLog->mailable_class,
                'context_type' => $emailLog->context_type,
                'context_id' => $emailLog->context_id,
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
                'related_email_log_id' => $emailLog->id,
                'sent_at' => null,
            ]);
        }
    }

    private function normalizeRecipients(array|string $value, string $recipientType): array
    {
        $items = match (true) {
            ! is_array($value) => [$value],
            array_key_exists('email', $value) => [$value],
            default => $value,
        };

        return collect($items)
            ->map(function ($recipient) use ($recipientType): ?array {
                if (blank($recipient)) {
                    return null;
                }

                if (is_string($recipient)) {
                    return [
                        'email' => $recipient,
                        'name' => null,
                        'recipient_type' => $recipientType,
                    ];
                }

                if (is_array($recipient)) {
                    $email = $recipient['email'] ?? null;

                    if (blank($email)) {
                        return null;
                    }

                    return [
                        'email' => $email,
                        'name' => $recipient['name'] ?? null,
                        'recipient_type' => $recipientType,
                    ];
                }

                return null;
            })
            ->filter()
            ->unique('email')
            ->values()
            ->all();
    }

    private function trackingConfigFor(string $mailableClass): array
    {
        return static::trackingConfig($mailableClass);
    }

    private function mailAddresses(array $recipients): array
    {
        return collect($recipients)
            ->map(fn (array $recipient) => new Address(
                $recipient['email'],
                (string) ($recipient['name'] ?? ''),
            ))
            ->values()
            ->all();
    }

    private function storeLogs(
        array $recipients,
        ?Tenant $tenant,
        string $subject,
        string $htmlContent,
        ?string $mailableClass,
        mixed $context,
        array $attachments,
        string $status,
        ?string $errorMessage,
    ): array {
        return collect($recipients)
            ->map(fn (array $recipient) => EmailLog::query()->create([
                'tenant_id' => $tenant?->id,
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'],
                'recipient_type' => $recipient['recipient_type'],
                'subject' => $subject,
                'html_content' => $htmlContent,
                'text_content' => null,
                'attachments' => $attachments === [] ? null : $attachments,
                'mailable_class' => $mailableClass,
                'context_type' => $context?->getMorphClass(),
                'context_id' => $context?->getKey(),
                'status' => $status,
                'error_message' => $errorMessage,
                'sent_at' => $status === 'sent' ? now() : null,
            ]))
            ->all();
    }

    private function normalizeAttachments(array $attachments): array
    {
        return collect($attachments)
            ->map(function ($attachment): ?array {
                if (! is_array($attachment)) {
                    return null;
                }

                $content = $attachment['content'] ?? null;
                $name = $attachment['name'] ?? null;

                if (blank($content) || blank($name)) {
                    return null;
                }

                return [
                    'name' => (string) $name,
                    'mime' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
                    'content' => base64_encode((string) $content),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
