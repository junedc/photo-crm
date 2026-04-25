<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantVendor;
use App\Models\User;
use Illuminate\Support\Collection;

class TaskAssignees
{
    public static function optionsForTenant(Tenant $tenant, ?Booking $booking = null): Collection
    {
        $users = $tenant->users()
            ->wherePivot('role', '!=', 'guest')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'value' => self::value(Task::ASSIGNEE_USER, $user->id),
                'label' => $user->name,
                'group' => 'Team',
                'type' => Task::ASSIGNEE_USER,
                'id' => $user->id,
                'meta' => $user->email,
            ]);

        $vendors = $tenant->vendors()
            ->orderBy('service_type')
            ->orderBy('name')
            ->get()
            ->map(fn (TenantVendor $vendor) => [
                'value' => self::value(Task::ASSIGNEE_VENDOR, $vendor->id),
                'label' => $vendor->name,
                'group' => 'Vendors',
                'type' => Task::ASSIGNEE_VENDOR,
                'id' => $vendor->id,
                'meta' => trim(collect([$vendor->service_type, $vendor->email])->filter()->implode(' · ')),
            ]);

        $customer = self::customerOption($tenant, $booking);

        return collect()
            ->merge($users)
            ->merge($vendors)
            ->when($customer, fn (Collection $collection) => $collection->push($customer))
            ->values();
    }

    public static function parse(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        [$type, $id] = array_pad(explode(':', $value, 2), 2, null);

        if (! in_array($type, Task::assigneeTypes(), true) || ! ctype_digit((string) $id)) {
            return null;
        }

        return [
            'type' => $type,
            'id' => (int) $id,
        ];
    }

    public static function value(string $type, int $id): string
    {
        return sprintf('%s:%d', $type, $id);
    }

    public static function matchesTenant(Tenant $tenant, ?Booking $booking, string $type, int $id): bool
    {
        return match ($type) {
            Task::ASSIGNEE_USER => $tenant->users()->where('users.id', $id)->exists(),
            Task::ASSIGNEE_VENDOR => $tenant->vendors()->whereKey($id)->exists(),
            Task::ASSIGNEE_CUSTOMER => self::customerMatchesBooking($tenant, $booking, $id),
            default => false,
        };
    }

    public static function labelForTask(Task $task): string
    {
        return match ($task->assignee_type) {
            Task::ASSIGNEE_USER => $task->assigneeUser?->name ?? 'Unassigned',
            Task::ASSIGNEE_VENDOR => $task->assigneeVendor?->name ?? 'Unassigned',
            Task::ASSIGNEE_CUSTOMER => $task->assigneeCustomer?->full_name ?? ($task->booking?->customer_name ?: 'Customer'),
            default => 'Unassigned',
        };
    }

    public static function customerOption(Tenant $tenant, ?Booking $booking): ?array
    {
        $customer = self::resolveBookingCustomer($tenant, $booking);

        if (! $customer) {
            return null;
        }

        return [
            'value' => self::value(Task::ASSIGNEE_CUSTOMER, $customer->id),
            'label' => $customer->full_name,
            'group' => 'Customer',
            'type' => Task::ASSIGNEE_CUSTOMER,
            'id' => $customer->id,
            'meta' => $customer->email,
        ];
    }

    public static function resolveBookingCustomer(Tenant $tenant, ?Booking $booking): ?Customer
    {
        if (! $booking) {
            return null;
        }

        if ($booking->relationLoaded('customer') && $booking->customer instanceof Customer) {
            return $booking->customer;
        }

        if ($booking->customer_id) {
            return Customer::query()->where('tenant_id', $tenant->id)->find($booking->customer_id);
        }

        if (filled($booking->customer_email)) {
            return Customer::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $booking->customer_email)
                ->first();
        }

        return null;
    }

    private static function customerMatchesBooking(Tenant $tenant, ?Booking $booking, int $customerId): bool
    {
        $customer = self::resolveBookingCustomer($tenant, $booking);

        return $customer instanceof Customer && (int) $customer->id === $customerId;
    }
}
