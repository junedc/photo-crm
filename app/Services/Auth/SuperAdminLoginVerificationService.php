<?php

namespace App\Services\Auth;

use App\Mail\SuperAdminLoginCodeMail;
use App\Models\SuperAdminLoginCode;
use Illuminate\Support\Facades\Mail;

class SuperAdminLoginVerificationService
{
    public const SESSION_KEY = 'super_admin.pending_verification';
    public const AUTH_KEY = 'super_admin.authenticated';
    public const EMAIL_KEY = 'super_admin.email';

    public static function allowedEmails(): array
    {
        return collect(explode('|', (string) config('app.super_admin')))
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function isAllowedEmail(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' && in_array($email, self::allowedEmails(), true);
    }

    public function issueFor(string $email): SuperAdminLoginCode
    {
        SuperAdminLoginCode::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = SuperAdminLoginCode::query()->create([
            'email' => $email,
            'code_hash' => bcrypt($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new SuperAdminLoginCodeMail(
            email: $email,
            code: $code,
            expiresAt: $verification->expires_at,
        ));

        return $verification;
    }
}
