<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Package;
use App\Models\Tenant;
use App\Support\DateFormatter;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiscountController extends Controller
{
    public function index(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $discounts = Discount::query()->with(['packages'])->latest()->get();

        return view('admin.app', [
            'page' => 'discounts',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'store' => route('discounts.store'),
                    'create' => route('discounts.create'),
                    'discounts' => route('discounts.index'),
                ],
                'discounts' => $discounts->map(fn (Discount $discount) => $this->serializeDiscount($discount))->values()->all(),
            ],
        ]);
    }

    public function create(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();

        return view('admin.app', [
            'page' => 'discounts-create',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'store' => route('discounts.store'),
                    'create' => route('discounts.create'),
                    'discounts' => route('discounts.index'),
                ],
                'discountTypes' => $this->discountTypes(),
                'packageOptions' => Package::query()->latest()->get()->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                ])->values()->all(),
            ],
        ]);
    }

    public function show(CurrentTenant $currentTenant, Discount $discount): View
    {
        $tenant = $currentTenant->get();
        $discount->load(['packages']);

        return view('admin.app', [
            'page' => 'discounts-detail',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'store' => route('discounts.store'),
                    'create' => route('discounts.create'),
                    'discounts' => route('discounts.index'),
                ],
                'discountTypes' => $this->discountTypes(),
                'packageOptions' => Package::query()->latest()->get()->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                ])->values()->all(),
                'discount' => $this->serializeDiscount($discount),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validateDiscount($request);
        $packageIds = $data['package_ids'] ?? [];
        unset($data['package_ids']);

        $discount = Discount::query()->create($data);
        $discount->packages()->sync($packageIds);
        $discount->load(['packages']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Discount added.',
                'record' => $this->serializeDiscount($discount),
            ]);
        }

        return redirect()->route('discounts.show', $discount)->with('status', 'Discount added.');
    }

    public function update(Request $request, Discount $discount): RedirectResponse|JsonResponse
    {
        $data = $this->validateDiscount($request, $discount);
        $packageIds = $data['package_ids'] ?? [];
        unset($data['package_ids']);

        $discount->update($data);
        $discount->packages()->sync($packageIds);
        $discount->refresh();
        $discount->load(['packages']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Discount updated.',
                'record' => $this->serializeDiscount($discount),
            ]);
        }

        return redirect()->route('discounts.show', $discount)->with('status', 'Discount updated.');
    }

    public function destroy(Request $request, Discount $discount): RedirectResponse|JsonResponse
    {
        $discount->packages()->detach();
        $discount->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Discount deleted.',
            ]);
        }

        return redirect()->route('discounts.index')->with('status', 'Discount deleted.');
    }

    private function validateDiscount(Request $request, ?Discount $discount = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('discounts', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))->ignore($discount?->id)],
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'discount_type' => ['required', Rule::in(array_keys($this->discountTypes()))],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', Rule::exists('packages', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ]);
    }

    private function serializeDiscount(Discount $discount): array
    {
        return [
            'id' => $discount->id,
            'code' => $discount->code,
            'name' => $discount->name,
            'starts_at' => DateFormatter::inputDate($discount->starts_at),
            'starts_at_label' => DateFormatter::date($discount->starts_at),
            'ends_at' => DateFormatter::inputDate($discount->ends_at),
            'ends_at_label' => DateFormatter::date($discount->ends_at),
            'discount_type' => $discount->discount_type,
            'discount_type_label' => $this->discountTypes()[$discount->discount_type] ?? $discount->discount_type,
            'discount_value' => number_format((float) $discount->discount_value, 2, '.', ''),
            'package_ids' => $discount->packages->pluck('id')->values()->all(),
            'packages' => $discount->packages->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
            ])->values()->all(),
            'show_url' => route('discounts.show', $discount),
            'update_url' => route('discounts.update', $discount),
            'delete_url' => route('discounts.destroy', $discount),
        ];
    }

    private function discountTypes(): array
    {
        return [
            'percentage' => 'Discount Percentage',
            'fixed_amount' => 'Specific Amount',
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
            'logo_url' => $tenant->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
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
}
