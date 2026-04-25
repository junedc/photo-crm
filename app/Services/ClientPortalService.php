<?php

namespace App\Services;

use App\Mail\ClientPortalCodeMail;
use App\Mail\ClientPortalInviteMail;
use App\Models\Booking;
use App\Models\ClientPortalAccess;
use App\Models\ClientPortalCode;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TrackedEmailSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientPortalService
{
    public const ACCESS_SESSION_KEY = 'client_portal.access';
    public const AUTH_SESSION_KEY = 'client_portal.auth';

    public function __construct(
        private readonly TrackedEmailSender $trackedEmailSender,
    ) {
    }

    public function grantForBooking(Booking $booking, ?User $grantedBy = null): ClientPortalAccess
    {
        $access = ClientPortalAccess::query()->updateOrCreate(
            [
                'tenant_id' => $booking->tenant_id,
                'customer_email' => strtolower((string) $booking->customer_email),
            ],
            [
                'booking_id' => $booking->id,
                'granted_by_user_id' => $grantedBy?->id,
                'customer_name' => $booking->customer_name,
                'invite_token' => (string) Str::uuid(),
                'granted_at' => now(),
                'last_notified_at' => now(),
            ],
        );

        if (! $access->wasRecentlyCreated && blank($access->invite_token)) {
            $access->forceFill([
                'invite_token' => (string) Str::uuid(),
            ])->save();
        }

        $portalUrl = route('client.portal.login', ['access' => $access->invite_token]);

        $this->trackedEmailSender->send(
            new ClientPortalInviteMail(
                tenant: $booking->tenant,
                booking: $booking,
                access: $access,
                portalUrl: $portalUrl,
            ),
            [
                'email' => $access->customer_email,
                'name' => $access->customer_name,
            ],
            [],
            [
                'tenant' => $booking->tenant,
                'context' => $booking,
            ],
        );

        return $access->refresh();
    }

    public function resolveGrantedAccess(Tenant $tenant, ?string $email = null, ?string $token = null): ?ClientPortalAccess
    {
        return ClientPortalAccess::query()
            ->where('tenant_id', $tenant->id)
            ->when($token, fn ($query) => $query->where('invite_token', $token))
            ->when($email, fn ($query) => $query->where('customer_email', strtolower($email)))
            ->first();
    }

    public function issueCode(ClientPortalAccess $access): ClientPortalCode
    {
        $access->codes()
            ->whereNull('consumed_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = $access->codes()->create([
            'email' => $access->customer_email,
            'code_hash' => bcrypt($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $tenant = Tenant::query()->findOrFail($access->tenant_id);

        $this->trackedEmailSender->send(
            new ClientPortalCodeMail(
                tenant: $tenant,
                customerName: $access->customer_name ?: 'there',
                code: $code,
                expiresAt: $verification->expires_at,
            ),
            [
                'email' => $access->customer_email,
                'name' => $access->customer_name,
            ],
            [],
            [
                'tenant' => $tenant,
                'context' => $access,
            ],
        );

        return $verification;
    }

    public function codeMatches(ClientPortalCode $code, string $value): bool
    {
        return Hash::check($value, $code->code_hash);
    }
}
