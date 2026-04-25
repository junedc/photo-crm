<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TaskStatus;
use App\Models\WorkspaceStatus;
use Illuminate\Support\Collection;

class TenantStatuses
{
    public const SCOPE_BOOKING = 'booking';
    public const SCOPE_INVOICE = 'invoice';
    public const SCOPE_PACKAGE = 'package';
    public const SCOPE_EQUIPMENT = 'equipment';
    public const SCOPE_TASK = 'task';
    public const SCOPE_QUOTE_RESPONSE = 'quote_response';
    public const SCOPE_CAMPAIGN = 'campaign';
    public const SCOPE_SUPPORT = 'support';
    public const SCOPE_REFERRAL = 'referral';
    public const SCOPE_EMAIL_TRACKING = 'email_tracking';
    public const SCOPE_INVOICE_INSTALLMENT = 'invoice_installment';

    public static function scopes(): array
    {
        return [
            self::SCOPE_INVOICE => 'Invoice Status',
            self::SCOPE_INVOICE_INSTALLMENT => 'Invoice Installment Status',
            self::SCOPE_TASK => 'Task Status',
            self::SCOPE_BOOKING => 'Booking Status',
            self::SCOPE_QUOTE_RESPONSE => 'Quote Response Status',
            self::SCOPE_PACKAGE => 'Package Status',
            self::SCOPE_EQUIPMENT => 'Equipment Status',
            self::SCOPE_CAMPAIGN => 'Campaign Status',
            self::SCOPE_SUPPORT => 'Support Status',
            self::SCOPE_REFERRAL => 'Referral Status',
            self::SCOPE_EMAIL_TRACKING => 'Email Tracking Status',
        ];
    }

    public static function workspaceScopes(): array
    {
        return array_values(array_filter(
            array_keys(self::scopes()),
            fn (string $scope): bool => $scope !== self::SCOPE_TASK,
        ));
    }

    public static function defaults(string $scope): array
    {
        return match ($scope) {
            self::SCOPE_INVOICE => ['draft', 'issued', 'partially_paid', 'paid', 'cancelled'],
            self::SCOPE_INVOICE_INSTALLMENT => ['pending', 'paid'],
            self::SCOPE_TASK => ['pending', 'in_progress', 'completed'],
            self::SCOPE_BOOKING => ['pending', 'confirmed', 'completed', 'cancelled'],
            self::SCOPE_QUOTE_RESPONSE => ['pending', 'accepted', 'rejected'],
            self::SCOPE_PACKAGE => ['active', 'inactive'],
            self::SCOPE_EQUIPMENT => ['ready', 'maintenance', 'retired'],
            self::SCOPE_CAMPAIGN => ['draft', 'sent'],
            self::SCOPE_SUPPORT => ['open', 'in_progress', 'resolved', 'closed'],
            self::SCOPE_REFERRAL => ['registered', 'qualified', 'rewarded'],
            self::SCOPE_EMAIL_TRACKING => ['sent', 'failed'],
            default => [],
        };
    }

    public static function systemStatuses(string $scope): array
    {
        return match ($scope) {
            self::SCOPE_INVOICE => ['issued', 'partially_paid', 'paid'],
            self::SCOPE_INVOICE_INSTALLMENT => ['pending', 'paid'],
            self::SCOPE_BOOKING => ['pending', 'confirmed', 'completed'],
            default => [],
        };
    }

    public static function isSystemStatus(string $scope, ?string $name): bool
    {
        $normalized = self::normalizeName($name);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::systemStatuses($scope), true);
    }

    public static function names(?Tenant $tenant, string $scope): array
    {
        if (! $tenant instanceof Tenant) {
            return self::defaults($scope);
        }

        $names = match ($scope) {
            self::SCOPE_TASK => $tenant->taskStatuses()->orderBy('name')->pluck('name')->all(),
            default => $tenant->workspaceStatuses()->where('scope', $scope)->orderBy('name')->pluck('name')->all(),
        };

        return $names !== [] ? array_values($names) : self::defaults($scope);
    }

    public static function records(?Tenant $tenant, string $scope): Collection
    {
        if (! $tenant instanceof Tenant) {
            return collect(self::defaults($scope))->map(fn (string $name, int $index) => [
                'id' => null,
                'name' => $name,
                'system' => self::isSystemStatus($scope, $name),
                'scope' => $scope,
                'sort_order' => $index + 1,
            ]);
        }

        $records = match ($scope) {
            self::SCOPE_TASK => $tenant->taskStatuses()->orderBy('name')->get()->map(fn (TaskStatus $status, int $index) => [
                'id' => $status->id,
                'name' => $status->name,
                'system' => (bool) $status->system,
                'scope' => $scope,
                'sort_order' => $index + 1,
            ]),
            default => $tenant->workspaceStatuses()->where('scope', $scope)->orderBy('name')->get()->map(fn (WorkspaceStatus $status, int $index) => [
                'id' => $status->id,
                'name' => $status->name,
                'system' => (bool) $status->system,
                'scope' => $scope,
                'sort_order' => $index + 1,
            ]),
        };

        if ($records->isNotEmpty()) {
            return $records->values();
        }

        return collect(self::defaults($scope))->map(fn (string $name, int $index) => [
            'id' => null,
            'name' => $name,
            'system' => self::isSystemStatus($scope, $name),
            'scope' => $scope,
            'sort_order' => $index + 1,
        ]);
    }

    public static function ensureWorkspaceRecords(Tenant $tenant, string $scope): Collection
    {
        abort_if($scope === self::SCOPE_TASK, 500, 'Task statuses are stored separately.');

        $existing = $tenant->workspaceStatuses()->where('scope', $scope)->orderBy('name')->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        foreach (self::defaults($scope) as $name) {
            $tenant->workspaceStatuses()->firstOrCreate([
                'scope' => $scope,
                'name' => $name,
            ], [
                'system' => self::isSystemStatus($scope, $name),
            ]);
        }

        return $tenant->workspaceStatuses()->where('scope', $scope)->orderBy('name')->get();
    }

    public static function seedDefaults(Tenant $tenant): void
    {
        foreach (self::defaults(self::SCOPE_TASK) as $name) {
            $tenant->taskStatuses()->updateOrCreate(
                ['name' => $name],
                ['system' => self::isSystemStatus(self::SCOPE_TASK, $name)],
            );
        }

        foreach (self::workspaceScopes() as $scope) {
            self::ensureWorkspaceRecords($tenant, $scope);
        }

        $tenant->taskStatuses()->get()->each(function (TaskStatus $status): void {
            $status->forceFill([
                'system' => self::isSystemStatus(self::SCOPE_TASK, $status->name),
            ])->saveQuietly();
        });

        $tenant->workspaceStatuses()->get()->each(function (WorkspaceStatus $status): void {
            $status->forceFill([
                'system' => self::isSystemStatus($status->scope, $status->name),
            ])->saveQuietly();
        });
    }

    public static function findWorkspaceStatusById(Tenant $tenant, string $scope, mixed $id): ?WorkspaceStatus
    {
        if (blank($id) || $scope === self::SCOPE_TASK) {
            return null;
        }

        self::ensureWorkspaceRecords($tenant, $scope);

        return $tenant->workspaceStatuses()
            ->where('scope', $scope)
            ->whereKey($id)
            ->first();
    }

    public static function firstOrCreateWorkspaceStatus(Tenant $tenant, string $scope, ?string $name): ?WorkspaceStatus
    {
        $normalized = self::normalizeName($name);

        if ($normalized === null || $scope === self::SCOPE_TASK) {
            return null;
        }

        self::ensureWorkspaceRecords($tenant, $scope);

        return $tenant->workspaceStatuses()->firstOrCreate([
            'scope' => $scope,
            'name' => $normalized,
        ], [
            'system' => self::isSystemStatus($scope, $normalized),
        ]);
    }

    public static function normalizeName(?string $name): ?string
    {
        $value = trim((string) $name);

        return $value === '' ? null : str_replace([' ', '-'], '_', strtolower($value));
    }
}
