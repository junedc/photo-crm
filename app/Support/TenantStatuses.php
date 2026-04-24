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

    public static function scopes(): array
    {
        return [
            self::SCOPE_INVOICE => 'Invoice Status',
            self::SCOPE_TASK => 'Task Status',
            self::SCOPE_BOOKING => 'Booking Status',
            self::SCOPE_PACKAGE => 'Package Status',
            self::SCOPE_EQUIPMENT => 'Equipment Status',
        ];
    }

    public static function defaults(string $scope): array
    {
        return match ($scope) {
            self::SCOPE_INVOICE => ['draft', 'issued', 'partially_paid', 'paid', 'cancelled'],
            self::SCOPE_TASK => ['pending', 'in_progress', 'completed'],
            self::SCOPE_BOOKING => ['pending', 'confirmed', 'completed', 'cancelled'],
            self::SCOPE_PACKAGE => ['active', 'inactive'],
            self::SCOPE_EQUIPMENT => ['ready', 'maintenance', 'retired'],
            default => [],
        };
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
                'scope' => $scope,
                'sort_order' => $index + 1,
            ]);
        }

        $records = match ($scope) {
            self::SCOPE_TASK => $tenant->taskStatuses()->orderBy('name')->get()->map(fn (TaskStatus $status, int $index) => [
                'id' => $status->id,
                'name' => $status->name,
                'scope' => $scope,
                'sort_order' => $index + 1,
            ]),
            default => $tenant->workspaceStatuses()->where('scope', $scope)->orderBy('name')->get()->map(fn (WorkspaceStatus $status, int $index) => [
                'id' => $status->id,
                'name' => $status->name,
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
            'scope' => $scope,
            'sort_order' => $index + 1,
        ]);
    }
}
