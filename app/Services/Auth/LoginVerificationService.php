<?php

namespace App\Services\Auth;

use App\Mail\LoginVerificationCodeMail;
use App\Models\LoginCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class LoginVerificationService
{
    public const SESSION_KEY = 'auth.pending_verification';

    public function issueFor(User $user, Tenant $tenant, string $email): LoginCode
    {
        LoginCode::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = LoginCode::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'email' => $email,
            'code_hash' => bcrypt($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user)->send(new LoginVerificationCodeMail(
            user: $user,
            tenant: $tenant,
            code: $code,
            expiresAt: $verification->expires_at,
        ));

        return $verification;
    }
}
