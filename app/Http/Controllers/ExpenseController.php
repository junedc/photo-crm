<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\TenantVendor;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $search = trim((string) $request->query('search', ''));

        $expenses = Expense::query()
            ->with(['booking', 'vendor', 'expenseCategory', 'user'])
            ->where('tenant_id', $tenant->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('expense_name', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('booking', function ($bookingQuery) use ($search): void {
                            $bookingQuery
                                ->where('quote_number', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%")
                                ->orWhere('entry_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vendor', function ($vendorQuery) use ($search): void {
                            $vendorQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('company_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('expenseCategory', function ($categoryQuery) use ($search): void {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('expense_date')
            ->latest('created_at')
            ->get()
            ->map(fn (Expense $expense) => $this->serializeExpense($expense))
            ->values();

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $expenses,
            ]);
        }

        return view('admin.app', [
            'page' => 'expenses',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'expenses' => route('expenses.index'),
                    'store' => route('expenses.store'),
                ],
                'expenses' => $expenses,
                'bookingOptions' => Booking::query()
                    ->where('tenant_id', $tenant->id)
                    ->latest('event_date')
                    ->latest('created_at')
                    ->get()
                    ->map(fn (Booking $booking) => [
                        'id' => $booking->id,
                        'label' => $booking->quote_number
                            ? sprintf('%s - %s', $booking->quote_number, $booking->entry_name ?: $booking->customer_name)
                            : ($booking->entry_name ?: $booking->customer_name),
                    ])
                    ->values(),
                'vendorOptions' => TenantVendor::query()
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('company_name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (TenantVendor $vendor) => [
                        'id' => $vendor->id,
                        'label' => $vendor->company_name
                            ? sprintf('%s (%s)', $vendor->name, $vendor->company_name)
                            : $vendor->name,
                    ])
                    ->values(),
                'expenseCategoryOptions' => ExpenseCategory::query()
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (ExpenseCategory $category) => [
                        'id' => $category->id,
                        'label' => $category->name,
                    ])
                    ->values(),
                'userOptions' => $tenant->users()
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'label' => $user->name,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function store(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $data = $this->validateExpense($request, $tenant);
        $data['tenant_id'] = $tenant->id;
        $data['receipt_path'] = $this->storeReceipt($request->file('receipt'));
        $data['receipt_original_name'] = $request->file('receipt')?->getClientOriginalName();

        $expense = Expense::query()->create($data);
        $expense->load(['booking', 'vendor', 'expenseCategory', 'user']);

        return response()->json([
            'message' => 'Expense added.',
            'record' => $this->serializeExpense($expense),
        ]);
    }

    public function update(CurrentTenant $currentTenant, Request $request, Expense $expense): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        abort_unless($expense->tenant_id === $tenant->id, 404);

        $data = $this->validateExpense($request, $tenant);
        $receipt = $request->file('receipt');

        if ($request->boolean('remove_receipt')) {
            $this->deleteReceipt($expense->receipt_path);
            $data['receipt_path'] = null;
            $data['receipt_original_name'] = null;
        }

        if ($receipt instanceof UploadedFile) {
            $this->deleteReceipt($expense->receipt_path);
            $data['receipt_path'] = $this->storeReceipt($receipt);
            $data['receipt_original_name'] = $receipt->getClientOriginalName();
        }

        $expense->update($data);
        $expense->load(['booking', 'vendor', 'expenseCategory', 'user']);

        return response()->json([
            'message' => 'Expense updated.',
            'record' => $this->serializeExpense($expense->fresh(['booking', 'vendor', 'expenseCategory', 'user'])),
        ]);
    }

    public function destroy(CurrentTenant $currentTenant, Expense $expense): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        abort_unless($expense->tenant_id === $tenant->id, 404);

        $this->deleteReceipt($expense->receipt_path);
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted.',
        ]);
    }

    private function validateExpense(Request $request, Tenant $tenant): array
    {
        $data = $request->validate([
            'expense_name' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'booking_id' => [
                'nullable',
                'integer',
                Rule::exists('bookings', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant_vendors', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'expense_category_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant_user', 'user_id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'remove_receipt' => ['nullable', 'boolean'],
        ]);

        return [
            'expense_name' => trim((string) $data['expense_name']),
            'expense_date' => $data['expense_date'],
            'amount' => $data['amount'],
            'booking_id' => $data['booking_id'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null,
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ];
    }

    private function storeReceipt(?UploadedFile $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store('expenses', 'public');
    }

    private function deleteReceipt(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function serializeExpense(Expense $expense): array
    {
        $bookingLabel = $expense->booking?->quote_number
            ? sprintf('%s - %s', $expense->booking->quote_number, $expense->booking->entry_name ?: $expense->booking->customer_name)
            : ($expense->booking?->entry_name ?: $expense->booking?->customer_name);
        $vendorLabel = $expense->vendor?->company_name
            ? sprintf('%s (%s)', $expense->vendor->name, $expense->vendor->company_name)
            : $expense->vendor?->name;

        return [
            'id' => $expense->id,
            'expense_name' => $expense->expense_name,
            'expense_date' => optional($expense->expense_date)->format('Y-m-d') ?? '',
            'expense_date_label' => optional($expense->expense_date)->format('d M Y') ?? 'Not set',
            'amount' => number_format((float) $expense->amount, 2, '.', ''),
            'booking_id' => $expense->booking_id,
            'booking_label' => $bookingLabel ?: 'Not linked',
            'vendor_id' => $expense->vendor_id,
            'vendor_label' => $vendorLabel ?: 'Not linked',
            'expense_category_id' => $expense->expense_category_id,
            'expense_category_label' => $expense->expenseCategory?->name ?: 'Not set',
            'user_id' => $expense->user_id,
            'user_label' => $expense->user?->name ?: 'Not linked',
            'notes' => $expense->notes ?? '',
            'receipt_url' => $expense->receipt_path ? Storage::disk('public')->url($expense->receipt_path) : null,
            'receipt_name' => $expense->receipt_original_name ?: ($expense->receipt_path ? basename($expense->receipt_path) : ''),
            'update_url' => route('expenses.update', $expense),
            'delete_url' => route('expenses.destroy', $expense),
        ];
    }

    private function requireTenant(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
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
            'expenses' => route('expenses.index'),
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
