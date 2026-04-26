<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantVendor;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $search = trim((string) $request->query('search', ''));

        $vendors = TenantVendor::query()
            ->where('tenant_id', $tenant->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('service_type', 'like', "%{$search}%");
                });
            })
            ->orderBy('company_name')
            ->orderBy('name')
            ->get()
            ->map(fn (TenantVendor $vendor) => $this->serializeVendor($vendor))
            ->values();

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $vendors,
            ]);
        }

        return view('admin.app', [
            'page' => 'vendors',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'vendors' => route('vendors.index'),
                    'store' => route('vendors.store'),
                ],
                'vendors' => $vendors,
            ],
        ]);
    }

    public function store(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);

        $vendor = TenantVendor::query()->create([
            'tenant_id' => $tenant->id,
            ...$this->validateVendor($request),
        ]);

        return response()->json([
            'message' => 'Vendor added.',
            'record' => $this->serializeVendor($vendor),
        ]);
    }

    public function update(CurrentTenant $currentTenant, Request $request, TenantVendor $vendor): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        abort_unless($vendor->tenant_id === $tenant->id, 404);

        $vendor->update($this->validateVendor($request));

        return response()->json([
            'message' => 'Vendor updated.',
            'record' => $this->serializeVendor($vendor->fresh()),
        ]);
    }

    public function destroy(CurrentTenant $currentTenant, TenantVendor $vendor): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        abort_unless($vendor->tenant_id === $tenant->id, 404);

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

    private function requireTenant(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
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
            'services_offered' => collect($vendor->services_offered ?? [])->map(fn ($service) => (string) $service)->filter()->values()->all(),
            'services_offered_label' => collect($vendor->services_offered ?? [])->map(fn ($service) => (string) $service)->filter()->implode(', '),
            'is_active' => (bool) $vendor->is_active,
            'email' => $vendor->email,
            'update_url' => route('vendors.update', $vendor),
            'delete_url' => route('vendors.destroy', $vendor),
        ];
    }

    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
            'theme' => $tenant->theme ?: 'dark',
        ];
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
            'vendors' => route('vendors.index'),
            'campaigns' => route('campaigns.index'),
            'tasks' => route('tasks.index'),
            'users' => route('users.index'),
            'roles' => route('roles.index'),
            'access' => route('access.index'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
    }
}
