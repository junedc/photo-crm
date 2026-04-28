<?php

namespace App\Http\Controllers;

use App\Models\InventoryItemCategory;
use App\Models\EventType;
use App\Models\ExpenseCategory;
use App\Models\ServiceOffering;
use App\Models\TaskStatus;
use App\Models\Tenant;
use App\Models\TenantFont;
use App\Models\TenantVendor;
use App\Models\Task;
use App\Models\Subscription;
use App\Models\TenantSubscriptionCharge;
use App\Models\WorkspaceStatus;
use App\Services\PlatformSubscriptionBillingService;
use App\Support\DateFormatter;
use App\Support\PlatformSubscriptionCheckoutLinkGenerator;
use App\Support\TenantStatuses;
use App\Tenancy\CurrentTenant;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(
        CurrentTenant $currentTenant,
        Request $request,
        PlatformSubscriptionBillingService $billingService,
    ): View
    {
        $tenant = $currentTenant->get();
        $user = $request->user();

        if ($tenant instanceof Tenant && $request->query('subscription_payment') === 'success') {
            $billingService->syncPaidCheckoutSession((string) $request->query('session_id', ''), $tenant);
        }

        return view('admin.app', [
            'page' => 'settings',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'subscriptions' => Subscription::query()
                    ->where('is_active', true)
                    ->where('billing_period', '!=', Subscription::BILLING_FREE_FOR_LIFE)
                    ->orderBy('price')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Subscription $subscription): array => $this->serializeSubscription($subscription))
                    ->values(),
                'user' => [
                    'name' => $user?->name,
                    'email' => $user?->email,
                ],
                'timezoneOptions' => $this->timezoneOptions(),
                'routes' => [
                    'dashboard' => route('dashboard'),
                    'calendar' => route('admin.calendar.index'),
                    'packages' => route('packages.index'),
                    'equipment' => route('equipment.index'),
                    'addons' => route('addons.index'),
                    'discounts' => route('discounts.index'),
                    'leads' => route('leads.index'),
                    'customers' => route('customers.index'),
                    'campaigns' => route('campaigns.index'),
                    'tasks' => route('tasks.index'),
                    'users' => route('users.index'),
                    'roles' => route('roles.index'),
                    'access' => route('access.index'),
                    'bookings' => route('admin.bookings.index'),
                    'quotes' => route('admin.quotes.index'),
                    'invoices' => route('admin.invoices.index'),
                    'settings' => route('settings.index'),
                    'workspaceUpdate' => route('settings.workspace.update'),
                    'subscriptionPay' => route('settings.subscription.pay'),
                    'accountUpdate' => route('settings.account.update'),
                    'fontStore' => route('settings.fonts.store'),
                    'maintenanceStore' => route('settings.maintenance.store'),
                    'maintenanceTaskStore' => route('settings.maintenance.tasks.store'),
                    'inventoryItemCategoryStore' => route('settings.inventory-item-categories.store'),
                    'expenseCategoryStore' => route('settings.expense-categories.store'),
                    'serviceOfferingStore' => route('settings.service-offerings.store'),
                    'eventTypeStore' => route('settings.event-types.store'),
                    'tenantStripeWebhook' => route('stripe.webhook'),
                    'logout' => route('logout'),
                ],
                'maintenance' => [
                    TenantStatuses::SCOPE_INVOICE => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_INVOICE),
                    TenantStatuses::SCOPE_INVOICE_INSTALLMENT => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_INVOICE_INSTALLMENT),
                    TenantStatuses::SCOPE_TASK => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_TASK),
                    TenantStatuses::SCOPE_BOOKING => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_BOOKING),
                    TenantStatuses::SCOPE_QUOTE_RESPONSE => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_QUOTE_RESPONSE),
                    TenantStatuses::SCOPE_PACKAGE => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_PACKAGE),
                    TenantStatuses::SCOPE_EQUIPMENT => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_EQUIPMENT),
                    TenantStatuses::SCOPE_CAMPAIGN => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_CAMPAIGN),
                    TenantStatuses::SCOPE_SUPPORT => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_SUPPORT),
                    TenantStatuses::SCOPE_REFERRAL => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_REFERRAL),
                    TenantStatuses::SCOPE_EMAIL_TRACKING => $this->serializeStatusRecords($tenant, TenantStatuses::SCOPE_EMAIL_TRACKING),
                    'inventory_item_category' => $this->serializeInventoryItemCategories($tenant),
                    'expense_category' => $this->serializeExpenseCategories($tenant),
                    'service_offered' => $this->serializeServiceOfferings($tenant),
                    'event_type' => $this->serializeEventTypes($tenant),
                ],
                'maintenanceLabels' => [
                    ...TenantStatuses::scopes(),
                    'inventory_item_category' => 'Inventory Item Category',
                    'expense_category' => 'Expense Category',
                    'service_offered' => 'Services Offered',
                    'event_type' => 'Event Type',
                ],
            ],
        ]);
    }

    public function updateWorkspace(CurrentTenant $currentTenant, Request $request): RedirectResponse|JsonResponse
    {
        $tenant = $currentTenant->get();

        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'abn' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', Rule::in(['dark', 'light'])],
            'timezone' => ['required', 'timezone'],
            'subscription_id' => ['nullable', 'integer'],
            'invoice_deposit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'travel_free_kilometers' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'travel_fee_per_kilometer' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'packages_api_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'stripe_currency' => ['nullable', 'string', 'size:3'],
            'quote_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'customer_package_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $subscription = $this->validateTenantSelectedSubscription($data['subscription_id'] ?? null, $tenant);

        $tenant->fill([
            'name' => $data['name'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'abn' => $data['abn'] ?? null,
            'address' => $data['address'] ?? null,
            'theme' => $data['theme'] ?? $tenant->theme ?? 'dark',
            'timezone' => $data['timezone'] ?? $tenant->timezone ?? config('app.timezone', 'UTC'),
            'subscription_id' => $subscription?->id,
            'invoice_deposit_percentage' => $data['invoice_deposit_percentage'] ?? null,
            'travel_free_kilometers' => $data['travel_free_kilometers'] ?? null,
            'travel_fee_per_kilometer' => $data['travel_fee_per_kilometer'] ?? null,
            'packages_api_key' => $data['packages_api_key'] ?? null,
            'stripe_secret' => filled($data['stripe_secret'] ?? null) ? $data['stripe_secret'] : $tenant->stripe_secret,
            'stripe_webhook_secret' => filled($data['stripe_webhook_secret'] ?? null) ? $data['stripe_webhook_secret'] : $tenant->stripe_webhook_secret,
            'stripe_currency' => strtolower($data['stripe_currency'] ?? 'aud') ?: null,
            'quote_prefix' => $data['quote_prefix'] ?? null,
            'invoice_prefix' => $data['invoice_prefix'] ?? null,
            'customer_package_discount_percentage' => $data['customer_package_discount_percentage'] ?? null,
            'logo_path' => $this->replaceLogo($request->file('logo'), $tenant->logo_path),
        ]);
        $tenant->save();

        $record = $this->serializeTenant($tenant->fresh());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Workspace settings updated.',
                'record' => $record,
            ]);
        }

        return redirect()->route('settings.index')->with('status', 'Workspace settings updated.');
    }

    public function paySubscription(
        CurrentTenant $currentTenant,
        PlatformSubscriptionCheckoutLinkGenerator $checkoutLinkGenerator,
    ): RedirectResponse {
        $tenant = $currentTenant->get();

        abort_unless($tenant instanceof Tenant, 404);

        $tenant->load('subscription');

        if (! $tenant->subscription instanceof Subscription) {
            return redirect()->route('settings.index')
                ->withErrors(['subscription' => 'Choose a subscription before paying.']);
        }

        $checkoutUrl = $checkoutLinkGenerator->checkoutUrlFor($tenant, $tenant->subscription);

        return redirect()->away($checkoutUrl);
    }

    public function updateAccount(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        $record = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Account settings updated.',
                'record' => $record,
            ]);
        }

        return redirect()->route('settings.index')->with('status', 'Account settings updated.');
    }

    public function storeFont(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'family' => ['required', 'string', 'max:120'],
            'weight' => ['required', Rule::in(['400', '700'])],
            'style' => ['required', Rule::in(['normal', 'italic'])],
            'file' => ['required', 'file', 'mimes:woff,woff2,ttf,otf', 'max:2048'],
        ]);

        $family = trim((string) $data['family']);
        $weight = (int) $data['weight'];
        $style = (string) $data['style'];

        $existing = TenantFont::query()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(family) = ?', [mb_strtolower($family)])
            ->where('weight', $weight)
            ->where('style', $style)
            ->first();

        $path = $data['file']->store("tenant-fonts/{$tenant->id}", 'public');

        if ($existing?->file_path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $font = TenantFont::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'family' => $family,
                'weight' => $weight,
                'style' => $style,
            ],
            [
                'file_name' => $data['file']->getClientOriginalName(),
                'file_path' => $path,
                'extension' => strtolower((string) $data['file']->getClientOriginalExtension()),
            ],
        );

        return response()->json([
            'message' => 'Font uploaded.',
            'record' => $this->serializeTenantFont($font->fresh()),
        ]);
    }

    public function destroyFont(CurrentTenant $currentTenant, TenantFont $font): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $font->tenant_id === $tenant->id, 404);

        if ($font->file_path) {
            Storage::disk('public')->delete($font->file_path);
        }

        $font->delete();

        return response()->json([
            'message' => 'Font deleted.',
        ]);
    }

    public function storeVendor(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $vendor = TenantVendor::query()->create([
            'tenant_id' => $tenant->id,
            ...$this->validateVendor($request),
        ]);

        return response()->json([
            'message' => 'Vendor added.',
            'record' => $this->serializeVendor($vendor),
        ]);
    }

    public function updateVendor(CurrentTenant $currentTenant, Request $request, TenantVendor $vendor): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $vendor->tenant_id === $tenant->id, 404);

        $vendor->update($this->validateVendor($request));

        return response()->json([
            'message' => 'Vendor updated.',
            'record' => $this->serializeVendor($vendor->fresh()),
        ]);
    }

    public function destroyVendor(CurrentTenant $currentTenant, TenantVendor $vendor): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $vendor->tenant_id === $tenant->id, 404);

        Task::query()
            ->where('tenant_id', $tenant->id)
            ->where('assignee_type', Task::ASSIGNEE_VENDOR)
            ->where('assignee_id', $vendor->id)
            ->update([
                'assignee_type' => null,
                'assignee_id' => null,
            ]);

        $vendor->delete();

        return response()->json([
            'message' => 'Vendor deleted.',
        ]);
    }

    public function storeMaintenanceStatus(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'scope' => ['required', Rule::in([
                TenantStatuses::SCOPE_BOOKING,
                TenantStatuses::SCOPE_INVOICE,
                TenantStatuses::SCOPE_INVOICE_INSTALLMENT,
                TenantStatuses::SCOPE_QUOTE_RESPONSE,
                TenantStatuses::SCOPE_PACKAGE,
                TenantStatuses::SCOPE_EQUIPMENT,
                TenantStatuses::SCOPE_CAMPAIGN,
                TenantStatuses::SCOPE_SUPPORT,
                TenantStatuses::SCOPE_REFERRAL,
                TenantStatuses::SCOPE_EMAIL_TRACKING,
            ])],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $status = WorkspaceStatus::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'scope' => $data['scope'],
            'name' => trim((string) $data['name']),
        ], [
            'system' => false,
            'sort_order' => $data['sort_order'] ?? $this->nextWorkspaceStatusSortOrder($tenant, (string) $data['scope']),
        ]);

        if (filled($data['sort_order'] ?? null) && (int) $status->sort_order !== (int) $data['sort_order']) {
            $status->forceFill([
                'sort_order' => (int) $data['sort_order'],
            ])->save();
        }

        return response()->json([
            'message' => 'Status added.',
            'record' => $this->serializeWorkspaceStatus($status),
        ]);
    }

    public function updateMaintenanceStatus(CurrentTenant $currentTenant, Request $request, WorkspaceStatus $status): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $status->tenant_id === $tenant->id, 404);
        $rules = [
            'sort_order' => ['required', 'integer', 'min:1'],
        ];

        if (! $status->system) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        $payload = [
            'sort_order' => (int) $data['sort_order'],
        ];

        if (! $status->system) {
            $payload['name'] = trim((string) $data['name']);
        }

        $status->update($payload);

        return response()->json([
            'message' => 'Status updated.',
            'record' => $this->serializeWorkspaceStatus($status->fresh()),
        ]);
    }

    public function destroyMaintenanceStatus(CurrentTenant $currentTenant, Request $request, WorkspaceStatus $status): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $status->tenant_id === $tenant->id, 404);
        abort_if($status->system, 422, 'System statuses cannot be deleted.');
        $status->delete();

        return response()->json([
            'message' => 'Status deleted.',
        ]);
    }

    public function storeInventoryItemCategory(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $category = InventoryItemCategory::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $data['name']),
        ], [
            'sort_order' => $data['sort_order'] ?? $this->nextInventoryItemCategorySortOrder($tenant),
        ]);

        if (filled($data['sort_order'] ?? null) && (int) $category->sort_order !== (int) $data['sort_order']) {
            $category->forceFill([
                'sort_order' => (int) $data['sort_order'],
            ])->save();
        }

        return response()->json([
            'message' => 'Inventory item category added.',
            'record' => $this->serializeInventoryItemCategory($category),
        ]);
    }

    public function storeExpenseCategory(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $category = ExpenseCategory::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $data['name']),
        ], [
            'sort_order' => $data['sort_order'] ?? $this->nextExpenseCategorySortOrder($tenant),
        ]);

        if (filled($data['sort_order'] ?? null) && (int) $category->sort_order !== (int) $data['sort_order']) {
            $category->forceFill([
                'sort_order' => (int) $data['sort_order'],
            ])->save();
        }

        return response()->json([
            'message' => 'Expense category added.',
            'record' => $this->serializeExpenseCategory($category),
        ]);
    }

    public function storeServiceOffering(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $serviceOffering = ServiceOffering::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $data['name']),
        ], [
            'sort_order' => $data['sort_order'] ?? $this->nextServiceOfferingSortOrder($tenant),
        ]);

        if (filled($data['sort_order'] ?? null) && (int) $serviceOffering->sort_order !== (int) $data['sort_order']) {
            $serviceOffering->forceFill([
                'sort_order' => (int) $data['sort_order'],
            ])->save();
        }

        return response()->json([
            'message' => 'Service offering added.',
            'record' => $this->serializeServiceOffering($serviceOffering),
        ]);
    }

    public function storeEventType(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $eventType = EventType::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $data['name']),
        ], [
            'sort_order' => $data['sort_order'] ?? $this->nextEventTypeSortOrder($tenant),
        ]);

        if (filled($data['sort_order'] ?? null) && (int) $eventType->sort_order !== (int) $data['sort_order']) {
            $eventType->forceFill([
                'sort_order' => (int) $data['sort_order'],
            ])->save();
        }

        return response()->json([
            'message' => 'Event type added.',
            'record' => $this->serializeEventType($eventType),
        ]);
    }

    public function updateExpenseCategory(CurrentTenant $currentTenant, Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $expenseCategory->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $expenseCategory->update([
            'name' => trim((string) $data['name']),
            'sort_order' => (int) $data['sort_order'],
        ]);

        return response()->json([
            'message' => 'Expense category updated.',
            'record' => $this->serializeExpenseCategory($expenseCategory->fresh()),
        ]);
    }

    public function updateServiceOffering(CurrentTenant $currentTenant, Request $request, ServiceOffering $serviceOffering): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $serviceOffering->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $serviceOffering->update([
            'name' => trim((string) $data['name']),
            'sort_order' => (int) $data['sort_order'],
        ]);

        return response()->json([
            'message' => 'Service offering updated.',
            'record' => $this->serializeServiceOffering($serviceOffering->fresh()),
        ]);
    }

    public function updateEventType(CurrentTenant $currentTenant, Request $request, EventType $eventType): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $eventType->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $eventType->update([
            'name' => trim((string) $data['name']),
            'sort_order' => (int) $data['sort_order'],
        ]);

        return response()->json([
            'message' => 'Event type updated.',
            'record' => $this->serializeEventType($eventType->fresh()),
        ]);
    }

    public function destroyExpenseCategory(CurrentTenant $currentTenant, ExpenseCategory $expenseCategory): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $expenseCategory->tenant_id === $tenant->id, 404);

        \App\Models\Expense::query()
            ->where('tenant_id', $tenant->id)
            ->where('expense_category_id', $expenseCategory->id)
            ->update([
                'expense_category_id' => null,
            ]);

        $expenseCategory->delete();

        return response()->json([
            'message' => 'Expense category deleted.',
        ]);
    }

    public function destroyServiceOffering(CurrentTenant $currentTenant, ServiceOffering $serviceOffering): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $serviceOffering->tenant_id === $tenant->id, 404);

        $serviceName = $serviceOffering->name;

        TenantVendor::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->each(function (TenantVendor $vendor) use ($serviceName): void {
                $services = collect($vendor->services_offered ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter(fn ($value) => $value !== '' && strcasecmp($value, $serviceName) !== 0)
                    ->values()
                    ->all();

                $vendor->update([
                    'services_offered' => $services,
                    'service_type' => $services[0] ?? null,
                ]);
            });

        $serviceOffering->delete();

        return response()->json([
            'message' => 'Service offering deleted.',
        ]);
    }

    public function destroyEventType(CurrentTenant $currentTenant, EventType $eventType): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $eventType->tenant_id === $tenant->id, 404);

        $eventType->delete();

        return response()->json([
            'message' => 'Event type deleted.',
        ]);
    }

    public function updateInventoryItemCategory(CurrentTenant $currentTenant, Request $request, InventoryItemCategory $inventoryItemCategory): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $inventoryItemCategory->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $inventoryItemCategory->update([
            'name' => trim((string) $data['name']),
            'sort_order' => (int) $data['sort_order'],
        ]);

        \App\Models\InventoryItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('inventory_item_category_id', $inventoryItemCategory->id)
            ->update([
                'addon_category' => $inventoryItemCategory->name,
            ]);

        return response()->json([
            'message' => 'Inventory item category updated.',
            'record' => $this->serializeInventoryItemCategory($inventoryItemCategory->fresh()),
        ]);
    }

    public function destroyInventoryItemCategory(CurrentTenant $currentTenant, InventoryItemCategory $inventoryItemCategory): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $inventoryItemCategory->tenant_id === $tenant->id, 404);

        \App\Models\InventoryItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('inventory_item_category_id', $inventoryItemCategory->id)
            ->update([
                'inventory_item_category_id' => null,
                'addon_category' => null,
            ]);

        $inventoryItemCategory->delete();

        return response()->json([
            'message' => 'Inventory item category deleted.',
        ]);
    }

    public function storeTaskStatus(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $status = TaskStatus::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => trim((string) $data['name']),
        ], [
            'system' => false,
            'sort_order' => $data['sort_order'] ?? $this->nextTaskStatusSortOrder($tenant),
        ]);

        if (filled($data['sort_order'] ?? null) && (int) $status->sort_order !== (int) $data['sort_order']) {
            $status->forceFill([
                'sort_order' => (int) $data['sort_order'],
            ])->save();
        }

        return response()->json([
            'message' => 'Task status added.',
            'record' => $this->serializeTaskStatus($status),
        ]);
    }

    public function updateTaskStatus(CurrentTenant $currentTenant, Request $request, TaskStatus $status): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $status->tenant_id === $tenant->id, 404);
        $rules = [
            'sort_order' => ['required', 'integer', 'min:1'],
        ];

        if (! $status->system) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        $payload = [
            'sort_order' => (int) $data['sort_order'],
        ];

        if (! $status->system) {
            $payload['name'] = trim((string) $data['name']);
        }

        $status->update($payload);

        return response()->json([
            'message' => 'Task status updated.',
            'record' => $this->serializeTaskStatus($status->fresh()),
        ]);
    }

    public function destroyTaskStatus(CurrentTenant $currentTenant, Request $request, TaskStatus $status): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $status->tenant_id === $tenant->id, 404);
        abort_if($status->system, 422, 'System statuses cannot be deleted.');
        $status->delete();

        return response()->json([
            'message' => 'Task status deleted.',
        ]);
    }

    private function validateTenantSelectedSubscription(mixed $subscriptionId, Tenant $tenant): ?Subscription
    {
        if (blank($subscriptionId)) {
            return null;
        }

        $subscription = Subscription::query()
            ->whereKey($subscriptionId)
            ->where('is_active', true)
            ->first();

        if (! $subscription instanceof Subscription) {
            throw ValidationException::withMessages([
                'subscription_id' => 'Choose an active subscription.',
            ]);
        }

        if (
            $subscription->billing_period === Subscription::BILLING_FREE_FOR_LIFE
            && (int) $tenant->subscription_id !== (int) $subscription->id
        ) {
            throw ValidationException::withMessages([
                'subscription_id' => 'Free for life plans can only be assigned by platform admin.',
            ]);
        }

        return $subscription;
    }

    private function replaceLogo(?UploadedFile $file, ?string $existingPath): ?string
    {
        if ($file === null) {
            return $existingPath;
        }

        if ($existingPath !== null) {
            Storage::disk('public')->delete($existingPath);
        }

        return $file->store('settings', 'public');
    }

    private function serializeTenant(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? Storage::disk('public')->url($tenant->logo_path) : null,
            'theme' => $tenant->theme ?: 'dark',
            'timezone' => $tenant->timezone ?: config('app.timezone', 'UTC'),
            'subscription_id' => $tenant->subscription_id,
            'subscription_enabled' => (bool) $tenant->subscription_enabled,
            'subscription_disabled_at' => $tenant->subscription_disabled_at?->toIso8601String(),
            'subscription' => $tenant->subscription ? $this->serializeSubscription($tenant->subscription) : null,
            'subscription_charges' => $tenant->subscriptionCharges()
                ->latest('period_starts_at')
                ->limit(6)
                ->get()
                ->map(fn (TenantSubscriptionCharge $charge): array => $this->serializeSubscriptionCharge($charge))
                ->values(),
            'contact_email' => $tenant->contact_email,
            'contact_phone' => $tenant->contact_phone,
            'abn' => $tenant->abn,
            'address' => $tenant->address,
            'invoice_deposit_percentage' => number_format((float) ($tenant->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)), 2, '.', ''),
            'travel_free_kilometers' => number_format((float) ($tenant->travel_free_kilometers ?? config('pricing.travel_free_kilometers', 0)), 2, '.', ''),
            'travel_fee_per_kilometer' => number_format((float) ($tenant->travel_fee_per_kilometer ?? config('pricing.travel_fee_per_kilometer', 0)), 2, '.', ''),
            'packages_api_key' => $tenant->packages_api_key,
            'stripe_secret' => '',
            'stripe_webhook_secret' => '',
            'stripe_secret_configured' => filled($tenant->stripe_secret),
            'stripe_webhook_secret_configured' => filled($tenant->stripe_webhook_secret),
            'stripe_currency' => strtolower($tenant->stripe_currency ?: 'aud'),
            'quote_prefix' => $tenant->quote_prefix ?? 'QT',
            'invoice_prefix' => $tenant->invoice_prefix ?? 'INV',
            'customer_package_discount_percentage' => number_format((float) ($tenant->customer_package_discount_percentage ?? 0), 2, '.', ''),
            'fonts' => $tenant->fonts()
                ->get()
                ->map(fn (TenantFont $font): array => $this->serializeTenantFont($font))
                ->values(),
        ];
    }

    private function serializeTenantFont(TenantFont $font): array
    {
        return [
            'id' => $font->id,
            'family' => $font->family,
            'weight' => $font->weight,
            'style' => $font->style,
            'file_name' => $font->file_name,
            'extension' => $font->extension,
            'url' => $this->publicStorageUrl($font->file_path),
            'css_format' => $this->fontCssFormat($font->extension),
            'label' => trim($font->family.' '.$this->fontVariantLabel($font->weight, $font->style)),
            'delete_url' => route('settings.fonts.destroy', $font),
        ];
    }

    private function fontVariantLabel(int $weight, string $style): string
    {
        return match (true) {
            $weight >= 700 && $style === 'italic' => 'Bold Italic',
            $weight >= 700 => 'Bold',
            $style === 'italic' => 'Italic',
            default => 'Regular',
        };
    }

    private function fontCssFormat(?string $extension): string
    {
        return match (strtolower((string) $extension)) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => 'woff2',
        };
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
    }

    private function serializeSubscription(Subscription $subscription): array
    {
        $validity = $subscription->billing_period === Subscription::BILLING_FREE_FOR_LIFE
            ? 'No expiry'
            : trim(($subscription->validity_count ? $subscription->validity_count.' ' : '').($subscription->validity_unit ?: ''));

        if ($validity === '') {
            $validity = 'No expiry';
        }

        return [
            'id' => $subscription->id,
            'name' => $subscription->name,
            'billing_period' => $subscription->billing_period,
            'price' => number_format((float) $subscription->price, 2, '.', ''),
            'currency' => strtoupper($subscription->currency ?: 'AUD'),
            'validity_count' => $subscription->validity_count,
            'validity_unit' => $subscription->validity_unit,
            'validity_label' => $validity,
            'description' => $subscription->description,
            'is_active' => (bool) $subscription->is_active,
        ];
    }

    private function serializeSubscriptionCharge(TenantSubscriptionCharge $charge): array
    {
        return [
            'id' => $charge->id,
            'subscription_name' => $charge->subscription_name,
            'billing_period' => $charge->billing_period,
            'period_starts_at' => DateFormatter::date($charge->period_starts_at),
            'period_ends_at' => DateFormatter::date($charge->period_ends_at),
            'amount' => number_format((float) $charge->amount, 2, '.', ''),
            'currency' => strtoupper($charge->currency ?: 'USD'),
            'status' => $charge->status,
            'paid_at' => DateFormatter::dateTime($charge->paid_at),
        ];
    }

    private function serializeStatusRecords(?Tenant $tenant, string $scope): array
    {
        return TenantStatuses::records($tenant, $scope)
            ->map(function (array $status) use ($scope): array {
                $updateUrl = null;
                $deleteUrl = null;

                if ($status['id']) {
                    if ($scope === TenantStatuses::SCOPE_TASK) {
                        $updateUrl = route('settings.maintenance.tasks.update', $status['id']);
                        $deleteUrl = route('settings.maintenance.tasks.destroy', $status['id']);
                    } else {
                        $updateUrl = route('settings.maintenance.update', $status['id']);
                        $deleteUrl = route('settings.maintenance.destroy', $status['id']);
                    }
                }

                return [
                    ...$status,
                    'label' => str($status['name'])->replace('_', ' ')->title()->toString(),
                    'system' => (bool) ($status['system'] ?? false),
                    'update_url' => $updateUrl,
                    'delete_url' => $deleteUrl,
                ];
            })
            ->values()
            ->all();
    }

    private function timezoneOptions(): array
    {
        return collect(DateTimeZone::listIdentifiers())
            ->map(fn (string $timezone): array => [
                'value' => $timezone,
                'label' => str($timezone)->replace('_', ' ')->toString(),
            ])
            ->values()
            ->all();
    }

    private function serializeWorkspaceStatus(WorkspaceStatus $status): array
    {
        return [
            'id' => $status->id,
            'scope' => $status->scope,
            'name' => $status->name,
            'label' => $status->label(),
            'sort_order' => (int) ($status->sort_order ?? 0),
            'system' => (bool) $status->system,
            'update_url' => route('settings.maintenance.update', $status),
            'delete_url' => route('settings.maintenance.destroy', $status),
        ];
    }

    private function serializeInventoryItemCategories(?Tenant $tenant)
    {
        if (! $tenant instanceof Tenant) {
            return collect();
        }

        return $tenant->inventoryItemCategories()
            ->get()
            ->map(fn (InventoryItemCategory $category): array => $this->serializeInventoryItemCategory($category))
            ->values();
    }

    private function serializeInventoryItemCategory(InventoryItemCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => (int) ($category->sort_order ?? 0),
            'system' => false,
            'update_url' => route('settings.inventory-item-categories.update', $category),
            'delete_url' => route('settings.inventory-item-categories.destroy', $category),
        ];
    }

    private function nextInventoryItemCategorySortOrder(Tenant $tenant): int
    {
        return ((int) $tenant->inventoryItemCategories()->max('sort_order')) + 1;
    }

    private function serializeExpenseCategories(?Tenant $tenant)
    {
        if (! $tenant instanceof Tenant) {
            return collect();
        }

        return $tenant->expenseCategories()
            ->get()
            ->map(fn (ExpenseCategory $category): array => $this->serializeExpenseCategory($category))
            ->values();
    }

    private function serializeExpenseCategory(ExpenseCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => (int) ($category->sort_order ?? 0),
            'system' => false,
            'update_url' => route('settings.expense-categories.update', $category),
            'delete_url' => route('settings.expense-categories.destroy', $category),
        ];
    }

    private function nextExpenseCategorySortOrder(Tenant $tenant): int
    {
        return ((int) $tenant->expenseCategories()->max('sort_order')) + 1;
    }

    private function serializeEventTypes(?Tenant $tenant)
    {
        if (! $tenant instanceof Tenant) {
            return collect();
        }

        return $tenant->eventTypes()
            ->get()
            ->map(fn (EventType $eventType): array => $this->serializeEventType($eventType))
            ->values();
    }

    private function serializeEventType(EventType $eventType): array
    {
        return [
            'id' => $eventType->id,
            'name' => $eventType->name,
            'sort_order' => (int) ($eventType->sort_order ?? 0),
            'system' => false,
            'update_url' => route('settings.event-types.update', $eventType),
            'delete_url' => route('settings.event-types.destroy', $eventType),
        ];
    }

    private function nextEventTypeSortOrder(Tenant $tenant): int
    {
        return ((int) $tenant->eventTypes()->max('sort_order')) + 1;
    }

    private function serializeServiceOfferings(?Tenant $tenant)
    {
        if (! $tenant instanceof Tenant) {
            return collect();
        }

        return $tenant->serviceOfferings()
            ->get()
            ->map(fn (ServiceOffering $serviceOffering): array => $this->serializeServiceOffering($serviceOffering))
            ->values();
    }

    private function serializeServiceOffering(ServiceOffering $serviceOffering): array
    {
        return [
            'id' => $serviceOffering->id,
            'name' => $serviceOffering->name,
            'sort_order' => (int) ($serviceOffering->sort_order ?? 0),
            'system' => false,
            'update_url' => route('settings.service-offerings.update', $serviceOffering),
            'delete_url' => route('settings.service-offerings.destroy', $serviceOffering),
        ];
    }

    private function nextServiceOfferingSortOrder(Tenant $tenant): int
    {
        return ((int) $tenant->serviceOfferings()->max('sort_order')) + 1;
    }

    private function serializeTaskStatus(TaskStatus $status): array
    {
        return [
            'id' => $status->id,
            'scope' => TenantStatuses::SCOPE_TASK,
            'name' => $status->name,
            'label' => $status->label(),
            'sort_order' => (int) ($status->sort_order ?? 0),
            'system' => (bool) $status->system,
            'update_url' => route('settings.maintenance.tasks.update', $status),
            'delete_url' => route('settings.maintenance.tasks.destroy', $status),
        ];
    }

    private function nextWorkspaceStatusSortOrder(Tenant $tenant, string $scope): int
    {
        return ((int) $tenant->workspaceStatuses()->where('scope', $scope)->max('sort_order')) + 1;
    }

    private function nextTaskStatusSortOrder(Tenant $tenant): int
    {
        return ((int) $tenant->taskStatuses()->max('sort_order')) + 1;
    }

    private function validateVendor(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'services_offered' => ['required', 'array', 'min:1'],
            'services_offered.*' => ['required', 'string', 'max:120'],
        ]);

        $services = collect($data['services_offered'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($services->isEmpty()) {
            throw ValidationException::withMessages([
                'services_offered' => 'Add at least one service offered.',
            ]);
        }

        return [
            'name' => trim((string) $data['name']),
            'company_name' => filled($data['company_name'] ?? null) ? trim((string) $data['company_name']) : null,
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'mobile_number' => filled($data['mobile_number'] ?? null) ? trim((string) $data['mobile_number']) : null,
            'email' => $data['email'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'services_offered' => $services->all(),
            'service_type' => $services->first(),
            'phone' => filled($data['mobile_number'] ?? null) ? trim((string) $data['mobile_number']) : null,
        ];
    }

    private function serializeVendor(TenantVendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'name' => $vendor->name,
            'company_name' => $vendor->company_name,
            'address' => $vendor->address,
            'mobile_number' => $vendor->mobile_number ?: $vendor->phone,
            'service_type' => $vendor->service_type,
            'services_offered' => collect($vendor->services_offered ?? [])
                ->map(fn ($service) => (string) $service)
                ->filter()
                ->values()
                ->all(),
            'services_offered_label' => collect($vendor->services_offered ?? [])
                ->map(fn ($service) => (string) $service)
                ->filter()
                ->implode(', '),
            'is_active' => (bool) $vendor->is_active,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'update_url' => route('settings.vendors.update', $vendor),
            'delete_url' => route('settings.vendors.destroy', $vendor),
        ];
    }
}
