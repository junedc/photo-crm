<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    private Tenant $sourceTenant;

    private Tenant $targetTenant;

    /**
     * @var array<string, array<int, int>>
     */
    private array $ids = [];

    public function run(): void
    {
        $sourceSlug = env('DEMO_SOURCE_TENANT_SLUG', 'm');
        $targetSlug = env('DEMO_TARGET_TENANT_SLUG', 'demo');

        DB::transaction(function () use ($sourceSlug, $targetSlug): void {
            $this->sourceTenant = Tenant::query()
                ->where('slug', $sourceSlug)
                ->firstOrFail();

            $this->targetTenant = Tenant::query()->firstOrCreate(
                ['slug' => $targetSlug],
                [
                    'name' => 'Demo',
                    'referral_code' => Str::upper(Str::random(10)),
                    'timezone' => $this->sourceTenant->timezone ?: config('app.timezone', 'UTC'),
                    'contact_email' => $this->sourceTenant->contact_email,
                    'contact_phone' => $this->sourceTenant->contact_phone,
                    'address' => $this->sourceTenant->address,
                    'invoice_deposit_percentage' => $this->sourceTenant->invoice_deposit_percentage,
                    'travel_free_kilometers' => $this->sourceTenant->travel_free_kilometers,
                    'travel_fee_per_kilometer' => $this->sourceTenant->travel_fee_per_kilometer,
                    'stripe_currency' => $this->sourceTenant->stripe_currency ?: 'aud',
                    'quote_prefix' => 'DEMO-QT',
                    'booking_number_prefix' => 'DEMO-BK',
                    'invoice_prefix' => 'DEMO-INV',
                    'customer_package_discount_percentage' => $this->sourceTenant->customer_package_discount_percentage,
                ]
            );

            $this->targetTenant->forceFill([
                'timezone' => $this->sourceTenant->timezone ?: $this->targetTenant->timezone,
                'invoice_deposit_percentage' => $this->sourceTenant->invoice_deposit_percentage,
                'travel_free_kilometers' => $this->sourceTenant->travel_free_kilometers,
                'travel_fee_per_kilometer' => $this->sourceTenant->travel_fee_per_kilometer,
                'stripe_currency' => $this->sourceTenant->stripe_currency ?: $this->targetTenant->stripe_currency,
                'quote_prefix' => 'DEMO-QT',
                'booking_number_prefix' => 'DEMO-BK',
                'invoice_prefix' => 'DEMO-INV',
                'customer_package_discount_percentage' => $this->sourceTenant->customer_package_discount_percentage,
            ])->save();

            TenantStatusSeeder::seedTenant($this->targetTenant);
            $this->clearTargetTenantData();

            $this->copyReferences();
            $this->copyCatalog();
            $this->copyCustomersAndVendors();
            $this->copyDiscounts();
            $this->copyBookings();
            $this->copyInvoices();
            $this->copyTasksAndActivity();
            $this->copyMarketingShell();
        });
    }

    private function copyReferences(): void
    {
        $this->copyTenantReference('workspace_statuses', ['scope', 'name']);
        $this->copyTenantReference('task_statuses', ['name']);
        $this->copyTenantReference('inventory_item_categories', ['name']);
        $this->copyTenantReference('expense_categories', ['name']);
        $this->copyTenantReference('service_offerings', ['name']);
        $this->copyTenantReference('event_types', ['name']);
    }

    private function copyCatalog(): void
    {
        $this->copyTenantRows('packages', [
            'package_status_id' => 'workspace_statuses',
        ], function (array $row, object $source): array {
            if (! empty($source->photo_path)) {
                $row['photo_path'] = $source->photo_path;
            }

            return $row;
        });

        $this->copyTenantRows('package_hourly_prices', [
            'package_id' => 'packages',
        ]);

        $this->copyTenantRows('equipment', [
            'package_id' => 'packages',
            'maintenance_status_id' => 'workspace_statuses',
        ]);

        $this->copyTenantRows('inventory_items', [
            'inventory_item_category_id' => 'inventory_item_categories',
        ]);

        $this->copyPivotRows('inventory_item_package', [
            'package_id' => 'packages',
            'inventory_item_id' => 'inventory_items',
        ], 'package_id');
    }

    private function copyCustomersAndVendors(): void
    {
        $this->copyTenantRows('customers');
        $this->copyTenantRows('tenant_vendors');
        $this->copyTenantRows('templates');
    }

    private function copyDiscounts(): void
    {
        $this->copyTenantRows('discounts');

        $this->copyPivotRows('discount_package', [
            'discount_id' => 'discounts',
            'package_id' => 'packages',
        ], 'discount_id');

        $this->copyPivotRows('discount_equipment', [
            'discount_id' => 'discounts',
            'equipment_id' => 'equipment',
        ], 'discount_id');

        $this->copyPivotRows('discount_inventory_item', [
            'discount_id' => 'discounts',
            'inventory_item_id' => 'inventory_items',
        ], 'discount_id');
    }

    private function copyBookings(): void
    {
        $this->copyTenantRows('bookings', [
            'customer_id' => 'customers',
            'package_id' => 'packages',
            'discount_id' => 'discounts',
            'booking_status_id' => 'workspace_statuses',
            'quote_response_status_id' => 'workspace_statuses',
        ], function (array $row, object $source): array {
            if (array_key_exists('quote_number', $row) && $source->quote_number !== null) {
                $row['quote_number'] = $this->demoNumber('QT', (int) $source->id);
            }

            if (array_key_exists('quote_token', $row) && $source->quote_token !== null) {
                $row['quote_token'] = (string) Str::uuid();
            }

            return $row;
        });

        $this->copyPivotRows('booking_equipment', [
            'booking_id' => 'bookings',
            'equipment_id' => 'equipment',
        ], 'booking_id');

        $this->copyPivotRows('booking_inventory_item', [
            'booking_id' => 'bookings',
            'inventory_item_id' => 'inventory_items',
        ], 'booking_id');

        $this->copyTenantRows('booking_contacts', [
            'booking_id' => 'bookings',
        ]);

        $this->copyTenantRows('booking_documents', [
            'booking_id' => 'bookings',
        ], fn (array $row): array => $this->mapUserColumn($row, 'uploaded_by_user_id'));

        $this->copyTenantRows('leads', [
            'booking_id' => 'bookings',
        ], function (array $row, object $source): array {
            if (array_key_exists('token', $row) && $source->token !== null) {
                $row['token'] = (string) Str::uuid();
            }

            return $row;
        });

        $this->copyTenantRows('client_portal_accesses', [
            'booking_id' => 'bookings',
        ], fn (array $row): array => $this->mapUserColumn($row, 'granted_by_user_id'));

        $this->copyChildRows('client_portal_codes', 'client_portal_access_id', 'client_portal_accesses');

        $this->copyTenantRows('client_portal_designs', [
            'booking_id' => 'bookings',
        ]);
    }

    private function copyInvoices(): void
    {
        $this->copyTenantRows('invoices', [
            'booking_id' => 'bookings',
            'invoice_status_id' => 'workspace_statuses',
        ], function (array $row, object $source): array {
            $row['invoice_number'] = $this->demoNumber('INV', (int) $source->id);
            $row['token'] = Str::random(40);

            return $row;
        });

        $this->copyChildRows('invoice_installments', 'invoice_id', 'invoices', [
            'invoice_installment_status_id' => 'workspace_statuses',
        ]);
    }

    private function copyTasksAndActivity(): void
    {
        $this->copyTenantRows('expenses', [
            'booking_id' => 'bookings',
            'expense_category_id' => 'expense_categories',
            'vendor_id' => 'tenant_vendors',
        ], fn (array $row): array => $this->mapUserColumn($row, 'user_id'));

        $this->copyTenantRows('tasks', [
            'booking_id' => 'bookings',
            'inventory_item_id' => 'inventory_items',
            'task_status_id' => 'task_statuses',
        ], function (array $row): array {
            if (($row['assignee_type'] ?? null) === 'vendor') {
                $row['assignee_id'] = $this->mappedId('tenant_vendors', $row['assignee_id'] ?? null);
            }

            if (($row['assignee_type'] ?? null) === 'user') {
                $row['assignee_id'] = $this->targetOwnerId();
            }

            return $row;
        });

        $this->copyTenantRows('client_portal_task_updates', [
            'booking_id' => 'bookings',
            'task_id' => 'tasks',
            'task_status_id' => 'task_statuses',
        ]);

        $this->copyTenantRows('tenant_notifications', [
            'booking_id' => 'bookings',
            'task_id' => 'tasks',
        ], fn (array $row): array => $this->mapUserColumn($row, 'user_id'));
    }

    private function copyMarketingShell(): void
    {
        $this->copyTenantRows('subscriber_groups');

        $this->copyTenantRows('campaigns', [
            'campaign_status_id' => 'workspace_statuses',
            'template_id' => 'templates',
        ]);

        $this->copyChildRows('campaign_recipients', 'subscriber_group_id', 'subscriber_groups', [], function (array $row): array {
            $recipientType = $row['recipient_type'] ?? null;

            if ($recipientType === 'customer' || $recipientType === 'App\\Models\\Customer') {
                $row['recipient_id'] = $this->mappedId('customers', $row['recipient_id'] ?? null);
            }

            if ($recipientType === 'lead' || $recipientType === 'App\\Models\\Lead') {
                $row['recipient_id'] = $this->mappedId('leads', $row['recipient_id'] ?? null);
            }

            return $row;
        });

        $this->copyChildRows('campaign_results', 'campaign_id', 'campaigns', [
            'campaign_recipient_id' => 'campaign_recipients',
        ], function (array $row, object $source): array {
            if (array_key_exists('token', $row) && $source->token !== null) {
                $row['token'] = 'demo-'.Str::random(32);
            }

            return $row;
        });
    }

    /**
     * @param  array<int, string>  $uniqueColumns
     */
    private function copyTenantReference(string $table, array $uniqueColumns): void
    {
        DB::table($table)
            ->where('tenant_id', $this->sourceTenant->id)
            ->orderBy('id')
            ->get()
            ->each(function (object $source) use ($table, $uniqueColumns): void {
                $attributes = ['tenant_id' => $this->targetTenant->id];

                foreach ($uniqueColumns as $column) {
                    $attributes[$column] = $source->{$column};
                }

                $row = $this->rowForInsert($table, $source);

                unset($row['id']);
                $row['tenant_id'] = $this->targetTenant->id;

                DB::table($table)->updateOrInsert($attributes, $row);

                $target = DB::table($table)
                    ->where($attributes)
                    ->first();

                if ($target !== null) {
                    $this->ids[$table][(int) $source->id] = (int) $target->id;
                }
            });
    }

    /**
     * @param  array<string, string>  $foreignKeys
     */
    private function copyTenantRows(string $table, array $foreignKeys = [], ?callable $mutate = null): void
    {
        DB::table($table)
            ->where('tenant_id', $this->sourceTenant->id)
            ->orderBy('id')
            ->get()
            ->each(function (object $source) use ($table, $foreignKeys, $mutate): void {
                $row = $this->rowForInsert($table, $source);
                $oldId = (int) $source->id;

                unset($row['id']);
                $row['tenant_id'] = $this->targetTenant->id;
                $row = $this->mapForeignKeys($row, $foreignKeys);

                if ($mutate !== null) {
                    $row = $mutate($row, $source);
                }

                $this->ids[$table][$oldId] = (int) DB::table($table)->insertGetId($row);
            });
    }

    /**
     * @param  array<string, string>  $foreignKeys
     */
    private function copyChildRows(
        string $table,
        string $parentColumn,
        string $parentTable,
        array $foreignKeys = [],
        ?callable $mutate = null,
    ): void {
        $sourceParentIds = array_keys($this->ids[$parentTable] ?? []);

        if ($sourceParentIds === []) {
            return;
        }

        DB::table($table)
            ->whereIn($parentColumn, $sourceParentIds)
            ->orderBy('id')
            ->get()
            ->each(function (object $source) use ($table, $parentColumn, $parentTable, $foreignKeys, $mutate): void {
                $row = $this->rowForInsert($table, $source);
                $oldId = property_exists($source, 'id') ? (int) $source->id : null;

                unset($row['id']);
                $row[$parentColumn] = $this->mappedId($parentTable, $source->{$parentColumn});
                $row = $this->mapForeignKeys($row, $foreignKeys);

                if ($mutate !== null) {
                    $row = $mutate($row, $source);
                }

                $newId = DB::table($table)->insertGetId($row);

                if ($oldId !== null) {
                    $this->ids[$table][$oldId] = (int) $newId;
                }
            });
    }

    /**
     * @param  array<string, string>  $foreignKeys
     */
    private function copyPivotRows(string $table, array $foreignKeys, string $sourceFilterColumn): void
    {
        $sourceIds = array_keys($this->ids[$foreignKeys[$sourceFilterColumn]] ?? []);

        if ($sourceIds === []) {
            return;
        }

        DB::table($table)
            ->whereIn($sourceFilterColumn, $sourceIds)
            ->get()
            ->each(function (object $source) use ($table, $foreignKeys): void {
                $row = $this->rowForInsert($table, $source);

                unset($row['id']);

                $row = $this->mapForeignKeys($row, $foreignKeys);

                DB::table($table)->insert($row);
            });
    }

    /**
     * @param  array<string, string>  $foreignKeys
     * @return array<string, mixed>
     */
    private function mapForeignKeys(array $row, array $foreignKeys): array
    {
        foreach ($foreignKeys as $column => $table) {
            if (! array_key_exists($column, $row)) {
                continue;
            }

            $row[$column] = $this->mappedId($table, $row[$column]);
        }

        return $row;
    }

    private function mappedId(string $table, mixed $sourceId): ?int
    {
        if ($sourceId === null) {
            return null;
        }

        return $this->ids[$table][(int) $sourceId] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowForInsert(string $table, object $source): array
    {
        $row = (array) $source;
        $columns = DB::getSchemaBuilder()->getColumnListing($table);

        return array_intersect_key($row, array_flip($columns));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUserColumn(array $row, string $column): array
    {
        if (array_key_exists($column, $row) && $row[$column] !== null) {
            $row[$column] = $this->targetOwnerId();
        }

        return $row;
    }

    private function targetOwnerId(): ?int
    {
        $owner = DB::table('tenant_user')
            ->where('tenant_id', $this->targetTenant->id)
            ->where('role', 'owner')
            ->orderBy('user_id')
            ->first();

        return $owner?->user_id;
    }

    private function demoNumber(string $prefix, int $sourceId): string
    {
        return sprintf('%s-%s-%05d', $prefix, Str::upper($this->targetTenant->slug), $sourceId);
    }

    private function clearTargetTenantData(): void
    {
        $bookingIds = $this->targetIds('bookings');
        $invoiceIds = $this->targetIds('invoices');
        $accessIds = $this->targetIds('client_portal_accesses');
        $campaignIds = $this->targetIds('campaigns');
        $groupIds = $this->targetIds('subscriber_groups');
        $recipientIds = $groupIds === [] ? [] : DB::table('campaign_recipients')->whereIn('subscriber_group_id', $groupIds)->pluck('id')->all();
        $discountIds = $this->targetIds('discounts');
        $packageIds = $this->targetIds('packages');
        $inventoryItemIds = $this->targetIds('inventory_items');

        $this->deleteWhereIn('client_portal_codes', 'client_portal_access_id', $accessIds);
        $this->deleteWhereIn('campaign_results', 'campaign_id', $campaignIds);
        $this->deleteWhereIn('campaign_results', 'campaign_recipient_id', $recipientIds);
        $this->deleteWhereIn('campaign_recipients', 'subscriber_group_id', $groupIds);
        $this->deleteWhereIn('invoice_installments', 'invoice_id', $invoiceIds);
        $this->deleteWhereIn('booking_equipment', 'booking_id', $bookingIds);
        $this->deleteWhereIn('booking_inventory_item', 'booking_id', $bookingIds);
        $this->deleteWhereIn('discount_package', 'discount_id', $discountIds);
        $this->deleteWhereIn('discount_equipment', 'discount_id', $discountIds);
        $this->deleteWhereIn('discount_inventory_item', 'discount_id', $discountIds);
        $this->deleteWhereIn('inventory_item_package', 'package_id', $packageIds);
        $this->deleteWhereIn('inventory_item_package', 'inventory_item_id', $inventoryItemIds);

        foreach ([
            'tenant_notifications',
            'client_portal_task_updates',
            'support_ticket_replies',
            'support_tickets',
            'booking_documents',
            'booking_contacts',
            'expenses',
            'tasks',
            'client_portal_designs',
            'client_portal_accesses',
            'invoices',
            'leads',
            'bookings',
            'campaigns',
            'subscriber_groups',
            'templates',
            'discounts',
            'equipment',
            'package_hourly_prices',
            'packages',
            'inventory_items',
            'customers',
            'tenant_vendors',
        ] as $table) {
            DB::table($table)->where('tenant_id', $this->targetTenant->id)->delete();
        }

        foreach ([
            'task_statuses',
            'inventory_item_categories',
            'expense_categories',
            'service_offerings',
            'event_types',
        ] as $table) {
            DB::table($table)->where('tenant_id', $this->targetTenant->id)->delete();
        }

        $this->ids = [];
    }

    /**
     * @return array<int, int>
     */
    private function targetIds(string $table): array
    {
        return DB::table($table)
            ->where('tenant_id', $this->targetTenant->id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, mixed>  $ids
     */
    private function deleteWhereIn(string $table, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::table($table)->whereIn($column, $ids)->delete();
    }
}
