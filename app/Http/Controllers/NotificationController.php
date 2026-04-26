<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Support\AdminNotificationFeed;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant, AdminNotificationFeed $notificationFeed): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $user = $request->user();

        abort_unless($user !== null, 401);

        return response()->json([
            'notifications' => $notificationFeed->forUser($tenant, $user)->all(),
        ]);
    }

    public function dismiss(Request $request, CurrentTenant $currentTenant, TenantNotification $notification): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $user = $request->user();

        abort_unless($user !== null, 401);
        abort_unless($notification->tenant_id === $tenant->id && $notification->user_id === $user->id, 404);

        $notification->forceFill([
            'read_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Notification removed.',
        ]);
    }

    private function requireTenant(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
