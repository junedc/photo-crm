<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        return $this->renderCustomersPage($currentTenant, $request);
    }

    public function create(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();

        return view('admin.app', [
            'page' => 'customers-create',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'store' => route('customers.store'),
                    'create' => route('customers.create'),
                    'customers' => route('customers.index'),
                    'campaigns' => route('campaigns.index'),
                ],
            ],
        ]);
    }

    public function show(CurrentTenant $currentTenant, Customer $customer): View
    {
        $tenant = $currentTenant->get();
        $customer->loadCount('bookings');

        return view('admin.app', [
            'page' => 'customers-detail',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'store' => route('customers.store'),
                    'create' => route('customers.create'),
                    'customers' => route('customers.index'),
                    'campaigns' => route('campaigns.index'),
                ],
                'customer' => $this->serializeCustomer($customer),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::query()->create($this->validateCustomer($request));
        $customer->loadCount('bookings');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Customer added.',
                'record' => $this->serializeCustomer($customer),
            ]);
        }

        return redirect()->route('customers.show', $customer)->with('status', 'Customer added.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $customer->update($this->validateCustomer($request));
        $customer->refresh();
        $customer->loadCount('bookings');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Customer updated.',
                'record' => $this->serializeCustomer($customer),
            ]);
        }

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $customer->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Customer deleted.',
            ]);
        }

        return redirect()->route('customers.index')->with('status', 'Customer deleted.');
    }

    private function renderCustomersPage(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        $tenant = $currentTenant->get();
        $customers = $this->paginatedCustomers($request);

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $customers->getCollection()->map(fn (Customer $customer) => $this->serializeCustomer($customer))->values()->all(),
                'pagination' => $this->paginationMeta($customers),
            ]);
        }

        return view('admin.app', [
            'page' => 'customers',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'store' => route('customers.store'),
                    'create' => route('customers.create'),
                    'customers' => route('customers.index'),
                    'campaigns' => route('campaigns.index'),
                ],
                'customers' => $customers->getCollection()->map(fn (Customer $customer) => $this->serializeCustomer($customer))->values()->all(),
                'pagination' => $this->paginationMeta($customers),
            ],
        ]);
    }

    private function paginatedCustomers(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        return Customer::query()
            ->withCount('bookings')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
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

    private function validateCustomer(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
        ]);
    }

    private function serializeCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'email' => $customer->email,
            'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
            'date_of_birth_label' => $customer->date_of_birth?->format('d M Y'),
            'address' => $customer->address,
            'phone' => $customer->phone,
            'bookings_count' => $customer->bookings_count ?? $customer->bookings()->count(),
            'created_at' => $customer->created_at?->format('d M Y'),
            'show_url' => route('customers.show', $customer),
            'update_url' => route('customers.update', $customer),
            'delete_url' => route('customers.destroy', $customer),
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
            'theme' => $tenant->theme ?: 'dark',
            'contact_email' => $tenant->contact_email,
            'contact_phone' => $tenant->contact_phone,
            'address' => $tenant->address,
            'invoice_deposit_percentage' => number_format((float) ($tenant->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)), 2, '.', ''),
            'travel_free_kilometers' => number_format((float) ($tenant->travel_free_kilometers ?? config('pricing.travel_free_kilometers', 0)), 2, '.', ''),
            'travel_fee_per_kilometer' => number_format((float) ($tenant->travel_fee_per_kilometer ?? config('pricing.travel_fee_per_kilometer', 0)), 2, '.', ''),
            'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
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
            'users' => route('users.index'),
            'roles' => route('roles.index'),
            'access' => route('access.index'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
    }
}
