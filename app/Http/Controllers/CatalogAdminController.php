<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\InventoryItemCategory;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Lead;
use App\Models\Package;
use App\Models\PackageHourlyPrice;
use App\Models\Task;
use App\Models\Tenant;
use App\Support\DateFormatter;
use App\Support\TenantStatuses;
use App\Tenancy\CurrentTenant;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogAdminController extends Controller
{
    public function index(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $packages = Package::query()->latest()->get();
        $equipment = Equipment::query()->latest()->get();
        $addOns = InventoryItem::query()->latest()->get();
        $bookings = Booking::query()->latest()->get();
        $leads = Lead::query()->latest()->get();
        $tasks = Task::query()
            ->with(['booking', 'status'])
            ->latest('due_date')
            ->latest('created_at')
            ->get();
        $upcomingBookings = Booking::query()
            ->with('package')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->where('status', '!=', 'pending')
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(3)
            ->get();

        return $this->renderAdminPage('overview', [
            'tenant' => $this->serializeTenant($tenant),
            'counts' => [
                'packages' => $packages->count(),
                'activePackages' => $packages->where('is_active', true)->count(),
                'equipment' => $equipment->count(),
                'addons' => $addOns->count(),
                'bookings' => $bookings->count(),
                'leads' => $leads->count(),
                'customers' => \App\Models\Customer::query()->count(),
            ],
            'upcomingBookings' => $upcomingBookings->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'status' => $booking->status,
                'event_date_label' => DateFormatter::date($booking->event_date),
                'start_time_label' => $booking->start_time ? substr($booking->start_time, 0, 5) : null,
                'package_name' => $booking->package?->name,
                'show_url' => route('admin.bookings.show', $booking),
            ])->values()->all(),
            'taskBuckets' => [
                'unassigned' => [
                    'count' => $tasks->filter(fn (Task $task) => blank($task->assignee_type) || blank($task->assignee_id))->count(),
                    'tasks' => $tasks
                        ->filter(fn (Task $task) => blank($task->assignee_type) || blank($task->assignee_id))
                        ->take(4)
                        ->map(fn (Task $task) => [
                            'id' => $task->id,
                            'task_name' => $task->task_name,
                            'booking_label' => $task->booking?->quote_number
                                ? sprintf('%s - %s', $task->booking->quote_number, $task->booking->entry_name ?: $task->booking->customer_name)
                                : ($task->booking?->entry_name ?: $task->booking?->customer_name),
                            'due_date_label' => DateFormatter::date($task->due_date, 'No due date'),
                        ])
                        ->values()
                        ->all(),
                ],
                'no_status' => [
                    'count' => $tasks->whereNull('task_status_id')->count(),
                    'tasks' => $tasks
                        ->whereNull('task_status_id')
                        ->take(4)
                        ->map(fn (Task $task) => [
                            'id' => $task->id,
                            'task_name' => $task->task_name,
                            'booking_label' => $task->booking?->quote_number
                                ? sprintf('%s - %s', $task->booking->quote_number, $task->booking->entry_name ?: $task->booking->customer_name)
                                : ($task->booking?->entry_name ?: $task->booking?->customer_name),
                            'due_date_label' => DateFormatter::date($task->due_date, 'No due date'),
                        ])
                        ->values()
                        ->all(),
                ],
            ],
            'routes' => $this->baseRoutes(),
        ]);
    }

    public function packagesIndex(CurrentTenant $currentTenant): View
    {
        return $this->renderPackagesPage($currentTenant);
    }

    public function packagesCreate(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $equipment = Equipment::query()->latest()->get();
        $addOns = InventoryItem::query()->where('category', 'add-on')->latest()->get();

        return $this->renderAdminPage('packages-create', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('packages.store'),
                'create' => route('packages.create'),
            ],
            'equipmentOptions' => $this->serializeCollection($equipment, fn (Equipment $asset) => $this->serializeEquipmentOption($asset)),
            'addOnOptions' => $this->serializeCollection($addOns, fn (InventoryItem $addon) => $this->serializeAddOnOption($addon)),
            'packageStatuses' => $this->packageStatuses(),
            'packageStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_PACKAGE) : [],
        ]);
    }

    public function packagesShow(CurrentTenant $currentTenant, Package $package): View
    {
        $tenant = $currentTenant->get();
        $package->load(['equipment', 'addOns']);
        $equipment = Equipment::query()->latest()->get();
        $addOns = InventoryItem::query()->where('category', 'add-on')->latest()->get();

        return $this->renderAdminPage('packages-detail', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('packages.store'),
                'create' => route('packages.create'),
            ],
            'equipmentOptions' => $this->serializeCollection($equipment, fn (Equipment $asset) => $this->serializeEquipmentOption($asset)),
            'addOnOptions' => $this->serializeCollection($addOns, fn (InventoryItem $addon) => $this->serializeAddOnOption($addon)),
            'package' => $this->serializePackage($package),
            'packageStatuses' => $this->packageStatuses(),
        ]);
    }

    public function equipmentIndex(CurrentTenant $currentTenant): View
    {
        return $this->renderEquipmentPage($currentTenant);
    }

    public function equipmentCreate(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();

        return $this->renderAdminPage('equipment-create', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('equipment.store'),
                'create' => route('equipment.create'),
            ],
            'maintenanceStatuses' => $this->equipmentStatuses(),
            'maintenanceStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_EQUIPMENT) : [],
        ]);
    }

    public function equipmentShow(CurrentTenant $currentTenant, Equipment $equipment): View
    {
        $tenant = $currentTenant->get();
        $equipment->load('package');

        return $this->renderAdminPage('equipment-detail', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('equipment.store'),
                'create' => route('equipment.create'),
            ],
            'maintenanceStatuses' => $this->equipmentStatuses(),
            'maintenanceStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_EQUIPMENT) : [],
            'equipmentRecord' => $this->serializeEquipment($equipment),
        ]);
    }

    public function addOnsIndex(CurrentTenant $currentTenant): View
    {
        return $this->renderAddOnsPage($currentTenant);
    }

    public function addOnsCreate(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();

        return $this->renderAdminPage('addons-create', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('addons.store'),
                'create' => route('addons.create'),
                'addons' => route('addons.index'),
            ],
            'addOnTypes' => $this->addOnTypes(),
            'inventoryItemCategoryOptions' => $this->inventoryItemCategoryOptions($tenant),
        ]);
    }

    public function addOnsShow(CurrentTenant $currentTenant, InventoryItem $addon): View
    {
        $tenant = $currentTenant->get();

        return $this->renderAdminPage('addons-detail', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('addons.store'),
                'create' => route('addons.create'),
                'addons' => route('addons.index'),
            ],
            'addon' => $this->serializeAddOn($addon),
            'addOnTypes' => $this->addOnTypes(),
            'inventoryItemCategoryOptions' => $this->inventoryItemCategoryOptions($tenant),
        ]);
    }

    public function leadsIndex(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        return $this->renderLeadsPage($currentTenant, $request);
    }

    public function leadsCreate(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();

        return $this->renderAdminPage('leads-create', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('leads.store'),
                'create' => route('leads.create'),
                'leads' => route('leads.index'),
                'bulk_delete' => route('leads.bulk-destroy'),
            ],
            'leadStatuses' => $this->leadStatuses(),
        ]);
    }

    public function leadsShow(CurrentTenant $currentTenant, Lead $lead): View
    {
        $tenant = $currentTenant->get();
        $lead->load(['booking.package']);

        return $this->renderAdminPage('leads-detail', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('leads.store'),
                'create' => route('leads.create'),
                'leads' => route('leads.index'),
                'bulk_delete' => route('leads.bulk-destroy'),
            ],
            'leadStatuses' => $this->leadStatuses(),
            'lead' => $this->serializeLead($lead),
        ]);
    }

    public function storePackage(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validatePackage($request);
        $data['photo_path'] = $this->storePhoto($request->file('photo'));
        $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        unset($data['equipment_ids']);
        unset($data['add_on_ids']);

        $hourlyPrices = $data['hourly_prices'] ?? [];
        unset($data['hourly_prices']);

        $package = Package::query()->create($data);
        $this->syncPackageEquipment($package, $equipmentIds);
        $package->addOns()->sync($addOnIds);
        $this->syncPackageHourlyPrices($package, $hourlyPrices);
        $package->load(['equipment', 'addOns', 'hourlyPrices']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Package added.',
                'record' => $this->serializePackage($package),
            ]);
        }

        return redirect()->route('packages.show', $package)->with('status', 'Package added.');
    }

    public function updatePackage(Request $request, Package $package): RedirectResponse|JsonResponse
    {
        $data = $this->validatePackage($request);
        $data['photo_path'] = $this->replacePhoto($request->file('photo'), $package->photo_path);
        $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        unset($data['equipment_ids']);
        unset($data['add_on_ids']);

        $hourlyPrices = $data['hourly_prices'] ?? [];
        unset($data['hourly_prices']);

        $package->update($data);
        $this->syncPackageEquipment($package, $equipmentIds);
        $package->addOns()->sync($addOnIds);
        $this->syncPackageHourlyPrices($package, $hourlyPrices);
        $package->refresh();
        $package->load(['equipment', 'addOns', 'hourlyPrices']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Package updated.',
                'record' => $this->serializePackage($package),
            ]);
        }

        return redirect()->route('packages.show', $package)->with('status', 'Package updated.');
    }

    public function destroyPackage(Request $request, Package $package): RedirectResponse|JsonResponse
    {
        if (Booking::query()->where('package_id', $package->id)->exists()) {
            return $this->deleteBlockedResponse(
                $request,
                'This package cannot be deleted because it is already used by bookings.',
                route('packages.show', $package),
            );
        }

        Equipment::query()
            ->where('tenant_id', $package->tenant_id)
            ->where('package_id', $package->id)
            ->update(['package_id' => null]);

        $package->addOns()->detach();
        $package->hourlyPrices()->delete();

        if ($package->photo_path) {
            Storage::disk('public')->delete($package->photo_path);
        }

        $package->delete();

        return $this->deleteSuccessResponse($request, 'Package deleted.', route('packages.index'));
    }

    public function storeEquipment(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validateEquipment($request);
        $data['photo_path'] = $this->storePhoto($request->file('photo'));

        $equipment = Equipment::query()->create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Equipment added.',
                'record' => $this->serializeEquipment($equipment),
            ]);
        }

        return redirect()->route('equipment.show', $equipment)->with('status', 'Equipment added.');
    }

    public function updateEquipment(Request $request, Equipment $equipment): RedirectResponse|JsonResponse
    {
        $data = $this->validateEquipment($request);
        $data['photo_path'] = $this->replacePhoto($request->file('photo'), $equipment->photo_path);

        $equipment->update($data);
        $equipment->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Equipment updated.',
                'record' => $this->serializeEquipment($equipment),
            ]);
        }

        return redirect()->route('equipment.show', $equipment)->with('status', 'Equipment updated.');
    }

    public function destroyEquipment(Request $request, Equipment $equipment): RedirectResponse|JsonResponse
    {
        if ($equipment->photo_path) {
            Storage::disk('public')->delete($equipment->photo_path);
        }

        $equipment->delete();

        return $this->deleteSuccessResponse($request, 'Equipment deleted.', route('equipment.index'));
    }

    public function storeAddOn(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validateAddOn($request);
        $data['photo_path'] = $this->storePhoto($request->file('photo'));

        $addon = InventoryItem::query()->create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Add-on added.',
                'record' => $this->serializeAddOn($addon),
            ]);
        }

        return redirect()->route('addons.show', $addon)->with('status', 'Add-on added.');
    }

    public function updateAddOn(Request $request, InventoryItem $addon): RedirectResponse|JsonResponse
    {
        $data = $this->validateAddOn($request);
        $data['photo_path'] = $this->replacePhoto($request->file('photo'), $addon->photo_path);

        $addon->update($data);
        $addon->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Add-on updated.',
                'record' => $this->serializeAddOn($addon),
            ]);
        }

        return redirect()->route('addons.show', $addon)->with('status', 'Add-on updated.');
    }

    public function destroyAddOn(Request $request, InventoryItem $addon): RedirectResponse|JsonResponse
    {
        $addon->packages()->detach();
        DB::table('booking_inventory_item')
            ->where('inventory_item_id', $addon->id)
            ->delete();

        if ($addon->photo_path) {
            Storage::disk('public')->delete($addon->photo_path);
        }

        $addon->delete();

        return $this->deleteSuccessResponse($request, 'Add-on deleted.', route('addons.index'));
    }

    public function storeLead(Request $request): RedirectResponse|JsonResponse
    {
        $lead = Lead::query()->create([
            ...$this->validateLead($request),
            'last_activity_at' => now(),
        ]);
        $lead->load(['booking.package']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Lead added.',
                'record' => $this->serializeLead($lead),
            ]);
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Lead added.');
    }

    public function updateLead(Request $request, Lead $lead): RedirectResponse|JsonResponse
    {
        $lead->update([
            ...$this->validateLead($request),
            'last_activity_at' => now(),
        ]);
        $lead->refresh();
        $lead->load(['booking.package']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Lead updated.',
                'record' => $this->serializeLead($lead),
            ]);
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Lead updated.');
    }

    public function destroyLead(Request $request, Lead $lead): RedirectResponse|JsonResponse
    {
        $lead->delete();

        return $this->deleteSuccessResponse($request, 'Lead deleted.', route('leads.index'));
    }

    public function destroyLeads(CurrentTenant $currentTenant, Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = $currentTenant->get()?->id;

        $data = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', Rule::exists('leads', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ]);

        $leadIds = collect($data['lead_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        Lead::query()
            ->whereIn('id', $leadIds)
            ->delete();

        $count = $leadIds->count();
        $message = $count === 1 ? '1 lead deleted.' : "{$count} leads deleted.";

        return $this->deleteSuccessResponse($request, $message, route('leads.index'));
    }

    private function validatePackage(Request $request): array
    {
        $tenant = app(CurrentTenant::class)->get();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'package_status_id' => ['required', Rule::exists('workspace_statuses', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenant?->id)
                ->where('scope', TenantStatuses::SCOPE_PACKAGE))],
            'hourly_prices' => ['nullable', 'array'],
            'hourly_prices.*.hours' => ['nullable', 'numeric', 'min:0.25'],
            'hourly_prices.*.price' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'equipment_ids' => ['nullable', 'array'],
            'equipment_ids.*' => ['integer', 'exists:equipment,id'],
            'add_on_ids' => ['nullable', 'array'],
            'add_on_ids.*' => ['integer', 'exists:inventory_items,id'],
        ]);

        $status = $tenant ? TenantStatuses::findWorkspaceStatusById($tenant, TenantStatuses::SCOPE_PACKAGE, $data['package_status_id']) : null;
        $data['package_status_id'] = $status?->id;
        $data['status'] = $status?->name ?? 'inactive';
        $data['is_active'] = $data['status'] === 'active';

        return $data;
    }

    private function validateEquipment(Request $request): array
    {
        $tenant = app(CurrentTenant::class)->get();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'maintenance_status_id' => ['required', Rule::exists('workspace_statuses', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenant?->id)
                ->where('scope', TenantStatuses::SCOPE_EQUIPMENT))],
            'last_maintained_at' => ['nullable', 'date'],
            'maintenance_notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $status = $tenant ? TenantStatuses::findWorkspaceStatusById($tenant, TenantStatuses::SCOPE_EQUIPMENT, $data['maintenance_status_id']) : null;
        $data['maintenance_status_id'] = $status?->id;
        $data['maintenance_status'] = $status?->name ?? 'ready';

        return $data;
    }

    private function validateAddOn(Request $request): array
    {
        $tenant = app(CurrentTenant::class)->get();
        $defaultMaintenanceStatus = $this->equipmentStatuses()[0] ?? 'ready';

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($this->addOnTypes())],
            'inventory_item_category_id' => ['nullable', Rule::exists('inventory_item_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant?->id))],
            'is_publicly_displayed' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'duration' => ['nullable', 'string', 'max:255'],
            'due_days_before_event' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]) + [
            'category' => 'add-on',
            'is_publicly_displayed' => $request->boolean('is_publicly_displayed'),
            'quantity' => 1,
            'maintenance_status' => $defaultMaintenanceStatus,
            'maintenance_status_id' => $tenant
                ? TenantStatuses::firstOrCreateWorkspaceStatus($tenant, TenantStatuses::SCOPE_EQUIPMENT, $defaultMaintenanceStatus)?->id
                : null,
            'last_maintained_at' => null,
            'maintenance_notes' => null,
        ];

        $data['discount_percentage'] = (float) ($data['discount_percentage'] ?? 0);
        $category = ! empty($data['inventory_item_category_id'])
            ? InventoryItemCategory::query()->find($data['inventory_item_category_id'])
            : null;
        $data['addon_category'] = $category?->name;

        if (($data['type'] ?? null) !== 'Action') {
            $data['due_days_before_event'] = null;
        }

        return $data;
    }

    private function validateLead(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255', 'required_without:customer_phone'],
            'customer_phone' => ['nullable', 'string', 'max:50', 'required_without:customer_email'],
            'event_date' => ['nullable', 'date'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in($this->leadStatuses())],
        ]);
    }

    private function storePhoto(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        return $file->store('catalog', 'public');
    }

    private function replacePhoto(?UploadedFile $file, ?string $existingPath): ?string
    {
        if ($file === null) {
            return $existingPath;
        }

        if ($existingPath !== null) {
            Storage::disk('public')->delete($existingPath);
        }

        return $this->storePhoto($file);
    }

    private function equipmentStatuses(): array
    {
        return TenantStatuses::names(app(CurrentTenant::class)->get(), TenantStatuses::SCOPE_EQUIPMENT);
    }

    private function packageStatuses(): array
    {
        return TenantStatuses::names(app(CurrentTenant::class)->get(), TenantStatuses::SCOPE_PACKAGE);
    }

    private function workspaceStatusOptions($tenant, string $scope): array
    {
        return TenantStatuses::ensureWorkspaceRecords($tenant, $scope)
            ->map(fn ($status) => [
                'id' => $status->id,
                'name' => $status->name,
                'label' => $status->label(),
            ])
            ->values()
            ->all();
    }

    private function leadStatuses(): array
    {
        return ['draft', 'new', 'contacted', 'qualified', 'quoted', 'campaign', 'booked', 'lost'];
    }

    private function renderPackagesPage(CurrentTenant $currentTenant, ?Package $selectedPackage = null): View
    {
        $tenant = $currentTenant->get();
        $packages = Package::query()->with(['equipment', 'addOns', 'hourlyPrices'])->latest()->get();
        $equipment = Equipment::query()->latest()->get();
        $addOns = InventoryItem::query()->where('category', 'add-on')->latest()->get();

        return $this->renderAdminPage('packages', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('packages.store'),
                'create' => route('packages.create'),
            ],
            'packages' => $this->serializeCollection($packages, fn (Package $package) => $this->serializePackage($package)),
            'equipmentOptions' => $this->serializeCollection($equipment, fn (Equipment $asset) => $this->serializeEquipmentOption($asset)),
            'addOnOptions' => $this->serializeCollection($addOns, fn (InventoryItem $addon) => $this->serializeAddOnOption($addon)),
            'packageStatuses' => $this->packageStatuses(),
        ]);
    }

    private function renderEquipmentPage(CurrentTenant $currentTenant, ?Equipment $selectedEquipment = null): View
    {
        $tenant = $currentTenant->get();
        $equipment = Equipment::query()->with('package')->latest()->get();

        return $this->renderAdminPage('equipment', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('equipment.store'),
                'create' => route('equipment.create'),
            ],
            'maintenanceStatuses' => $this->equipmentStatuses(),
            'maintenanceStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_EQUIPMENT) : [],
            'equipment' => $this->serializeCollection($equipment, fn (Equipment $asset) => $this->serializeEquipment($asset)),
        ]);
    }

    private function renderAddOnsPage(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $addons = InventoryItem::query()->latest()->get();

        return $this->renderAdminPage('addons', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('addons.store'),
                'create' => route('addons.create'),
                'addons' => route('addons.index'),
            ],
            'addons' => $this->serializeCollection($addons, fn (InventoryItem $addon) => $this->serializeAddOn($addon)),
            'addOnTypes' => $this->addOnTypes(),
        ]);
    }

    private function addOnTypes(): array
    {
        return ['Action', 'Items'];
    }

    private function inventoryItemCategoryOptions(?Tenant $tenant): array
    {
        if (! $tenant instanceof Tenant) {
            return [];
        }

        return $tenant->inventoryItemCategories()
            ->get()
            ->map(fn (InventoryItemCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();
    }

    private function renderLeadsPage(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        $tenant = $currentTenant->get();
        $leads = $this->paginatedLeads($request);

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $leads->getCollection()->map(fn (Lead $lead) => $this->serializeLead($lead))->values()->all(),
                'pagination' => $this->paginationMeta($leads),
            ]);
        }

        return $this->renderAdminPage('leads', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('leads.store'),
                'create' => route('leads.create'),
                'leads' => route('leads.index'),
                'bulk_delete' => route('leads.bulk-destroy'),
            ],
            'leadStatuses' => $this->leadStatuses(),
            'leads' => $this->serializeCollection($leads->getCollection(), fn (Lead $lead) => $this->serializeLead($lead)),
            'pagination' => $this->paginationMeta($leads),
        ]);
    }

    private function paginatedLeads(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        return Lead::query()
            ->with(['booking.package'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('event_location', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('last_activity_at')
            ->latest()
            ->paginate(10);
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
        ];
    }

    private function renderAdminPage(string $page, array $props): View
    {
        return view('admin.app', [
            'page' => $page,
            'props' => $props,
        ]);
    }

    private function baseRoutes(): array
    {
        return [
            'dashboard' => route('dashboard'),
            'calendar' => route('admin.calendar.index'),
            'packages' => route('packages.index'),
            'equipment' => route('equipment.index'),
            'addons' => route('addons.index'),
            'discounts' => route('discounts.index'),
            'bookings' => route('admin.bookings.index'),
            'quotes' => route('admin.quotes.index'),
            'invoices' => route('admin.invoices.index'),
            'leads' => route('leads.index'),
            'customers' => route('customers.index'),
            'campaigns' => route('campaigns.index'),
            'tasks' => route('tasks.index'),
            'users' => route('users.index'),
            'roles' => route('roles.index'),
            'access' => route('access.index'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
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
            'logo_url' => $tenant->logo_path ? $this->publicStorageUrl($tenant->logo_path) : null,
            'theme' => $tenant->theme ?: 'dark',
            'contact_email' => $tenant->contact_email,
            'contact_phone' => $tenant->contact_phone,
            'address' => $tenant->address,
            'invoice_deposit_percentage' => number_format((float) ($tenant->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)), 2, '.', ''),
            'travel_free_kilometers' => number_format((float) ($tenant->travel_free_kilometers ?? config('pricing.travel_free_kilometers', 0)), 2, '.', ''),
            'travel_fee_per_kilometer' => number_format((float) ($tenant->travel_fee_per_kilometer ?? config('pricing.travel_fee_per_kilometer', 0)), 2, '.', ''),
            'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
            'quote_prefix' => $tenant->quote_prefix ?? 'QT',
            'invoice_prefix' => $tenant->invoice_prefix ?? 'INV',
            'customer_package_discount_percentage' => number_format((float) ($tenant->customer_package_discount_percentage ?? 0), 2, '.', ''),
        ];
    }

    private function serializePackage(Package $package): array
    {
        $package->loadMissing('hourlyPrices');
        $displayPrice = $package->hourlyPrices->min('price') ?? $package->base_price;

        return [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'base_price' => number_format((float) $package->base_price, 2, '.', ''),
            'display_price' => number_format((float) $displayPrice, 2, '.', ''),
            'photo_url' => $package->photo_path ? $this->publicStorageUrl($package->photo_path) : null,
            'status_id' => $package->package_status_id,
            'status' => $package->status ?: ($package->is_active ? 'active' : 'inactive'),
            'status_label' => $package->packageStatus?->label() ?? str($package->status ?: ($package->is_active ? 'active' : 'inactive'))->replace('_', ' ')->title()->toString(),
            'is_active' => $package->is_active,
            'created_at' => DateFormatter::date($package->created_at),
            'equipment_ids' => $package->relationLoaded('equipment')
                ? $package->equipment->pluck('id')->values()->all()
                : $package->equipment()->pluck('id')->values()->all(),
            'add_on_ids' => $package->relationLoaded('addOns')
                ? $package->addOns->pluck('id')->values()->all()
                : $package->addOns()->pluck('inventory_items.id')->values()->all(),
            'equipment' => $package->relationLoaded('equipment')
                ? $package->equipment->map(fn (Equipment $asset) => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'category' => $asset->category,
                    'daily_rate' => number_format((float) $asset->daily_rate, 2, '.', ''),
                    'photo_url' => $asset->photo_path ? $this->publicStorageUrl($asset->photo_path) : null,
                ])->values()->all()
                : [],
            'add_ons' => $package->relationLoaded('addOns')
                ? $package->addOns->map(fn (InventoryItem $addon) => [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'type' => $addon->type,
                    'addon_category' => $addon->addon_category,
                    'product_code' => $addon->sku,
                    'description' => $addon->description,
                    'duration' => $addon->duration,
                    'price' => number_format($addon->discountedUnitPrice(), 2, '.', ''),
                    'original_price' => number_format((float) $addon->unit_price, 2, '.', ''),
                    'discount_percentage' => number_format((float) ($addon->discount_percentage ?? 0), 2, '.', ''),
                    'photo_url' => $addon->photo_path ? $this->publicStorageUrl($addon->photo_path) : null,
                ])->values()->all()
                : [],
            'hourly_prices' => $package->hourlyPrices->map(fn (PackageHourlyPrice $tier) => [
                'id' => $tier->id,
                'hours' => number_format((float) $tier->hours, 2, '.', ''),
                'hours_label' => rtrim(rtrim(number_format((float) $tier->hours, 2, '.', ''), '0'), '.').' hours',
                'price' => number_format((float) $tier->price, 2, '.', ''),
            ])->values()->all(),
            'show_url' => route('packages.show', $package),
            'update_url' => route('packages.update', $package),
            'delete_url' => route('packages.destroy', $package),
        ];
    }

    private function serializeEquipment(Equipment $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'category' => $asset->category,
            'serial_number' => $asset->serial_number,
            'description' => $asset->description,
            'daily_rate' => number_format((float) $asset->daily_rate, 2, '.', ''),
            'maintenance_status_id' => $asset->maintenance_status_id,
            'maintenance_status' => $asset->maintenance_status,
            'maintenance_status_label' => $asset->maintenanceStatusRecord?->label() ?? str($asset->maintenance_status)->replace('_', ' ')->title()->toString(),
            'last_maintained_at' => DateFormatter::inputDate($asset->last_maintained_at),
            'last_maintained_label' => DateFormatter::date($asset->last_maintained_at),
            'maintenance_notes' => $asset->maintenance_notes,
            'photo_url' => $asset->photo_path ? $this->publicStorageUrl($asset->photo_path) : null,
            'package_id' => $asset->package_id,
            'package_name' => $asset->relationLoaded('package') ? $asset->package?->name : null,
            'show_url' => route('equipment.show', $asset),
            'update_url' => route('equipment.update', $asset),
            'delete_url' => route('equipment.destroy', $asset),
        ];
    }

    private function serializeAddOn(InventoryItem $addon): array
    {
        return [
            'id' => $addon->id,
            'product_code' => $addon->sku,
            'name' => $addon->name,
            'type' => $addon->type,
            'inventory_item_category_id' => $addon->inventory_item_category_id,
            'inventory_item_category_name' => $addon->inventoryItemCategory?->name ?? $addon->addon_category,
            'addon_category' => $addon->addon_category,
            'is_publicly_displayed' => (bool) $addon->is_publicly_displayed,
            'description' => $addon->description,
            'unit_price' => number_format((float) $addon->unit_price, 2, '.', ''),
            'price' => number_format($addon->discountedUnitPrice(), 2, '.', ''),
            'original_price' => number_format((float) $addon->unit_price, 2, '.', ''),
            'discount_percentage' => number_format((float) ($addon->discount_percentage ?? 0), 2, '.', ''),
            'duration' => $addon->duration,
            'due_days_before_event' => $addon->due_days_before_event,
            'photo_url' => $addon->photo_path ? $this->publicStorageUrl($addon->photo_path) : null,
            'created_at' => DateFormatter::date($addon->created_at),
            'show_url' => route('addons.show', $addon),
            'update_url' => route('addons.update', $addon),
            'delete_url' => route('addons.destroy', $addon),
        ];
    }

    private function serializeLead(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'customer_name' => $lead->customer_name,
            'customer_email' => $lead->customer_email,
            'customer_phone' => $lead->customer_phone,
            'event_date' => DateFormatter::inputDate($lead->event_date),
            'event_date_label' => DateFormatter::date($lead->event_date),
            'event_location' => $lead->event_location,
            'notes' => $lead->notes,
            'status' => $lead->status,
            'booking_id' => $lead->booking_id,
            'booking_show_url' => $lead->booking_id ? route('admin.bookings.show', $lead->booking_id) : null,
            'booking_package_name' => $lead->relationLoaded('booking') ? $lead->booking?->package?->name : null,
            'created_at' => DateFormatter::date($lead->created_at),
            'last_activity_at' => DateFormatter::iso($lead->last_activity_at),
            'last_activity_label' => DateFormatter::dateTime($lead->last_activity_at),
            'show_url' => route('leads.show', $lead),
            'update_url' => route('leads.update', $lead),
            'delete_url' => route('leads.destroy', $lead),
        ];
    }

    private function serializeEquipmentOption(Equipment $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'category' => $asset->category,
            'assigned_package_id' => $asset->package_id,
        ];
    }

    private function serializeAddOnOption(InventoryItem $addon): array
    {
        return [
            'id' => $addon->id,
            'name' => $addon->name,
            'product_code' => $addon->sku,
            'type' => $addon->type,
            'inventory_item_category_id' => $addon->inventory_item_category_id,
            'inventory_item_category_name' => $addon->inventoryItemCategory?->name ?? $addon->addon_category,
            'addon_category' => $addon->addon_category,
            'is_publicly_displayed' => (bool) $addon->is_publicly_displayed,
            'duration' => $addon->duration,
            'due_days_before_event' => $addon->due_days_before_event,
            'unit_price' => number_format((float) $addon->unit_price, 2, '.', ''),
            'price' => number_format($addon->discountedUnitPrice(), 2, '.', ''),
            'original_price' => number_format((float) $addon->unit_price, 2, '.', ''),
            'discount_percentage' => number_format((float) ($addon->discount_percentage ?? 0), 2, '.', ''),
        ];
    }

    private function publicStorageUrl(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    private function deleteSuccessResponse(Request $request, string $message, string $redirectRoute): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ]);
        }

        return redirect($redirectRoute)->with('status', $message);
    }

    private function deleteBlockedResponse(Request $request, string $message, string $redirectRoute): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 422);
        }

        return redirect($redirectRoute)->withErrors([
            'delete' => $message,
        ]);
    }

    /**
     * @param  array<int, int>  $equipmentIds
     */
    private function syncPackageEquipment(Package $package, array $equipmentIds): void
    {
        Equipment::query()
            ->where('tenant_id', $package->tenant_id)
            ->where('package_id', $package->id)
            ->whereNotIn('id', $equipmentIds)
            ->update(['package_id' => null]);

        if ($equipmentIds === []) {
            return;
        }

        Equipment::query()
            ->where('tenant_id', $package->tenant_id)
            ->whereIn('id', $equipmentIds)
            ->update(['package_id' => $package->id]);
    }

    /**
     * @param  array<int, array{hours?: mixed, price?: mixed}>  $hourlyPrices
     */
    private function syncPackageHourlyPrices(Package $package, array $hourlyPrices): void
    {
        $normalized = collect($hourlyPrices)
            ->filter(fn ($tier) => filled($tier['hours'] ?? null) && filled($tier['price'] ?? null))
            ->map(fn ($tier) => [
                'hours' => number_format((float) $tier['hours'], 2, '.', ''),
                'price' => number_format((float) $tier['price'], 2, '.', ''),
            ])
            ->unique('hours')
            ->values();

        $package->hourlyPrices()->delete();

        foreach ($normalized as $tier) {
            $package->hourlyPrices()->create([
                'tenant_id' => $package->tenant_id,
                'hours' => $tier['hours'],
                'price' => $tier['price'],
            ]);
        }
    }

    /**
     * @param  Collection<int, Arrayable|mixed>  $collection
     * @param  callable(mixed): array  $transformer
     * @return array<int, array>
     */
    private function serializeCollection(Collection $collection, callable $transformer): array
    {
        return $collection->map($transformer)->values()->all();
    }
}
