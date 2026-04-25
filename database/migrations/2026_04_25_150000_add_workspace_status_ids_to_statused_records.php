<?php

use App\Support\TenantStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('booking_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
            $table->foreignId('quote_response_status_id')->nullable()->after('customer_response_status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('package_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->foreignId('maintenance_status_id')->nullable()->after('maintenance_status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('invoice_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->foreignId('invoice_installment_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('campaign_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('support_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('tenant_referrals', function (Blueprint $table) {
            $table->foreignId('referral_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('email_tracking_status_id')->nullable()->after('status')->constrained('workspace_statuses')->nullOnDelete();
        });

        $statusId = function (int $tenantId, string $scope, ?string $name): ?int {
            $normalized = TenantStatuses::normalizeName($name);

            if ($normalized === null) {
                return null;
            }

            $status = DB::table('workspace_statuses')
                ->where('tenant_id', $tenantId)
                ->where('scope', $scope)
                ->where('name', $normalized)
                ->first();

            if ($status) {
                return (int) $status->id;
            }

            return (int) DB::table('workspace_statuses')->insertGetId([
                'tenant_id' => $tenantId,
                'scope' => $scope,
                'name' => $normalized,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        DB::table('tenants')->select('id')->orderBy('id')->each(function ($tenant) use ($statusId): void {
            $tenantId = (int) $tenant->id;

            foreach ([
                TenantStatuses::SCOPE_BOOKING,
                TenantStatuses::SCOPE_INVOICE,
                TenantStatuses::SCOPE_INVOICE_INSTALLMENT,
                TenantStatuses::SCOPE_PACKAGE,
                TenantStatuses::SCOPE_EQUIPMENT,
                TenantStatuses::SCOPE_QUOTE_RESPONSE,
                TenantStatuses::SCOPE_CAMPAIGN,
                TenantStatuses::SCOPE_SUPPORT,
                TenantStatuses::SCOPE_REFERRAL,
                TenantStatuses::SCOPE_EMAIL_TRACKING,
            ] as $scope) {
                foreach (TenantStatuses::defaults($scope) as $name) {
                    $statusId($tenantId, $scope, $name);
                }
            }
        });

        DB::table('bookings')->select(['id', 'tenant_id', 'status', 'customer_response_status'])->orderBy('id')->each(function ($booking) use ($statusId): void {
            DB::table('bookings')
                ->where('id', $booking->id)
                ->update([
                    'booking_status_id' => $statusId((int) $booking->tenant_id, TenantStatuses::SCOPE_BOOKING, $booking->status),
                    'quote_response_status_id' => $statusId((int) $booking->tenant_id, TenantStatuses::SCOPE_QUOTE_RESPONSE, $booking->customer_response_status),
                ]);
        });

        DB::table('packages')->select(['id', 'tenant_id', 'status', 'is_active'])->orderBy('id')->each(function ($package) use ($statusId): void {
            $statusName = $package->status ?: ($package->is_active ? 'active' : 'inactive');

            DB::table('packages')
                ->where('id', $package->id)
                ->update([
                    'status' => TenantStatuses::normalizeName($statusName),
                    'package_status_id' => $statusId((int) $package->tenant_id, TenantStatuses::SCOPE_PACKAGE, $statusName),
                ]);
        });

        DB::table('equipment')->select(['id', 'tenant_id', 'maintenance_status'])->orderBy('id')->each(function ($equipment) use ($statusId): void {
            DB::table('equipment')
                ->where('id', $equipment->id)
                ->update([
                    'maintenance_status' => TenantStatuses::normalizeName($equipment->maintenance_status) ?? 'ready',
                    'maintenance_status_id' => $statusId((int) $equipment->tenant_id, TenantStatuses::SCOPE_EQUIPMENT, $equipment->maintenance_status),
                ]);
        });

        DB::table('invoices')->select(['id', 'tenant_id', 'status'])->orderBy('id')->each(function ($invoice) use ($statusId): void {
            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update([
                    'status' => TenantStatuses::normalizeName($invoice->status) ?? 'draft',
                    'invoice_status_id' => $statusId((int) $invoice->tenant_id, TenantStatuses::SCOPE_INVOICE, $invoice->status),
                ]);
        });

        DB::table('invoice_installments')
            ->join('invoices', 'invoice_installments.invoice_id', '=', 'invoices.id')
            ->select([
                'invoice_installments.id as installment_id',
                'invoice_installments.status',
                'invoices.tenant_id',
            ])
            ->orderBy('invoice_installments.id')
            ->each(function ($installment) use ($statusId): void {
                DB::table('invoice_installments')
                    ->where('id', $installment->installment_id)
                    ->update([
                        'status' => TenantStatuses::normalizeName($installment->status) ?? 'pending',
                        'invoice_installment_status_id' => $statusId((int) $installment->tenant_id, TenantStatuses::SCOPE_INVOICE_INSTALLMENT, $installment->status),
                    ]);
            });

        DB::table('campaigns')->select(['id', 'tenant_id', 'status'])->orderBy('id')->each(function ($campaign) use ($statusId): void {
            DB::table('campaigns')
                ->where('id', $campaign->id)
                ->update([
                    'status' => TenantStatuses::normalizeName($campaign->status) ?? 'draft',
                    'campaign_status_id' => $statusId((int) $campaign->tenant_id, TenantStatuses::SCOPE_CAMPAIGN, $campaign->status),
                ]);
        });

        DB::table('support_tickets')->select(['id', 'tenant_id', 'status'])->orderBy('id')->each(function ($ticket) use ($statusId): void {
            DB::table('support_tickets')
                ->where('id', $ticket->id)
                ->update([
                    'status' => TenantStatuses::normalizeName($ticket->status) ?? 'open',
                    'support_status_id' => $statusId((int) $ticket->tenant_id, TenantStatuses::SCOPE_SUPPORT, $ticket->status),
                ]);
        });

        DB::table('tenant_referrals')
            ->join('tenants', 'tenant_referrals.referrer_tenant_id', '=', 'tenants.id')
            ->select([
                'tenant_referrals.id as referral_id',
                'tenant_referrals.status',
                'tenant_referrals.referrer_tenant_id as tenant_id',
            ])
            ->orderBy('tenant_referrals.id')
            ->each(function ($referral) use ($statusId): void {
                DB::table('tenant_referrals')
                    ->where('id', $referral->referral_id)
                    ->update([
                        'status' => TenantStatuses::normalizeName($referral->status) ?? 'registered',
                        'referral_status_id' => $statusId((int) $referral->tenant_id, TenantStatuses::SCOPE_REFERRAL, $referral->status),
                    ]);
            });

        DB::table('email_logs')->select(['id', 'tenant_id', 'status'])->orderBy('id')->each(function ($log) use ($statusId): void {
            DB::table('email_logs')
                ->where('id', $log->id)
                ->update([
                    'status' => TenantStatuses::normalizeName($log->status) ?? 'failed',
                    'email_tracking_status_id' => $log->tenant_id
                        ? $statusId((int) $log->tenant_id, TenantStatuses::SCOPE_EMAIL_TRACKING, $log->status)
                        : null,
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_tracking_status_id');
        });

        Schema::table('tenant_referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_status_id');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('support_status_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_status_id');
        });

        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_installment_status_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_status_id');
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_status_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_status_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_response_status_id');
            $table->dropConstrainedForeignId('booking_status_id');
        });
    }
};
