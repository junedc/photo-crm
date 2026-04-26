<?php

namespace App\Support;

use Illuminate\Http\Request;

class AdminAccess
{
    public static function screens(): array
    {
        return [
            ['key' => 'overview', 'label' => 'Overview'],
            ['key' => 'calendar', 'label' => 'Calendar'],
            ['key' => 'bookings', 'label' => 'Bookings'],
            ['key' => 'quotes', 'label' => 'Quotes'],
            ['key' => 'invoices', 'label' => 'Invoices'],
            ['key' => 'expenses', 'label' => 'Expenses'],
            ['key' => 'leads', 'label' => 'Leads'],
            ['key' => 'customers', 'label' => 'Customers'],
            ['key' => 'vendors', 'label' => 'Vendors'],
            ['key' => 'campaigns', 'label' => 'Campaigns'],
            ['key' => 'email_tracking', 'label' => 'Email Tracking'],
            ['key' => 'tasks', 'label' => 'Tasks'],
            ['key' => 'packages', 'label' => 'Packages'],
            ['key' => 'equipment', 'label' => 'Equipment'],
            ['key' => 'addons', 'label' => 'Add-Ons'],
            ['key' => 'discounts', 'label' => 'Discounts'],
            ['key' => 'users', 'label' => 'Users'],
            ['key' => 'roles', 'label' => 'Roles'],
            ['key' => 'access', 'label' => 'Access Control'],
            ['key' => 'support', 'label' => 'Support'],
            ['key' => 'referrals', 'label' => 'Referrals'],
            ['key' => 'settings', 'label' => 'Settings'],
        ];
    }

    public static function screenKeys(): array
    {
        return array_column(self::screens(), 'key');
    }

    public static function screenForRequest(Request $request): ?string
    {
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            $routeName === 'dashboard' => 'overview',
            str_starts_with($routeName, 'admin.calendar.') => 'calendar',
            str_starts_with($routeName, 'admin.bookings.') => 'bookings',
            str_starts_with($routeName, 'admin.quotes.') => 'quotes',
            str_starts_with($routeName, 'admin.invoices.') => 'invoices',
            str_starts_with($routeName, 'expenses.') => 'expenses',
            str_starts_with($routeName, 'leads.') => 'leads',
            str_starts_with($routeName, 'customers.') => 'customers',
            str_starts_with($routeName, 'vendors.') => 'vendors',
            str_starts_with($routeName, 'campaigns.') => 'campaigns',
            str_starts_with($routeName, 'email-tracking.') => 'email_tracking',
            str_starts_with($routeName, 'tasks.') => 'tasks',
            str_starts_with($routeName, 'packages.') => 'packages',
            str_starts_with($routeName, 'equipment.') => 'equipment',
            str_starts_with($routeName, 'addons.') => 'addons',
            str_starts_with($routeName, 'discounts.') => 'discounts',
            str_starts_with($routeName, 'users.') => 'users',
            str_starts_with($routeName, 'roles.') => 'roles',
            str_starts_with($routeName, 'access.') => 'access',
            str_starts_with($routeName, 'support.') => 'support',
            str_starts_with($routeName, 'referrals.') => 'referrals',
            str_starts_with($routeName, 'settings.') => 'settings',
            default => null,
        };
    }
}
