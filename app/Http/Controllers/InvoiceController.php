<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceIssuedMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Support\DateFormatter;
use App\Support\InvoiceBuilder;
use App\Support\TenantStatuses;
use App\Models\User;
use App\Support\StripeCheckoutLinkGenerator;
use App\Support\TrackedEmailSender;
use App\Tenancy\CurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        $tenant = $currentTenant->get();
        $invoices = $this->paginatedInvoices($request);

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $invoices->getCollection()->map(fn (Invoice $invoice) => $this->serializeInvoiceListRecord($invoice))->values()->all(),
                'pagination' => $this->paginationMeta($invoices),
            ]);
        }

        return view('admin.app', [
            'page' => 'invoices',
            'props' => [
                'tenant' => [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'theme' => $tenant?->theme ?: 'dark',
                    'logo_url' => $tenant?->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
                    'contact_email' => $tenant?->contact_email,
                    'contact_phone' => $tenant?->contact_phone,
                    'address' => $tenant?->address,
                    'invoice_deposit_percentage' => number_format((float) ($tenant?->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)), 2, '.', ''),
                    'travel_free_kilometers' => number_format((float) ($tenant?->travel_free_kilometers ?? config('pricing.travel_free_kilometers', 0)), 2, '.', ''),
                    'travel_fee_per_kilometer' => number_format((float) ($tenant?->travel_fee_per_kilometer ?? config('pricing.travel_fee_per_kilometer', 0)), 2, '.', ''),
                    'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
                ],
                'routes' => [
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
                ],
                'invoiceStatuses' => ['all', ...TenantStatuses::names($tenant, TenantStatuses::SCOPE_INVOICE)],
                'invoices' => $invoices->getCollection()->map(fn (Invoice $invoice) => $this->serializeInvoiceListRecord($invoice))->values()->all(),
                'pagination' => $this->paginationMeta($invoices),
            ],
        ]);
    }

    private function paginatedInvoices(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        return Invoice::query()
            ->with(['booking.package', 'installments'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('booking', function ($bookingQuery) use ($search): void {
                            $bookingQuery
                                ->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('booking.package', fn ($packageQuery) => $packageQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('issued_at')
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

    public function store(Request $request, Booking $booking, InvoiceBuilder $invoiceBuilder): JsonResponse
    {
        $data = $request->validate([
            'installment_count' => ['required', 'integer', 'min:1', 'max:12'],
            'amounts_are' => ['nullable', 'in:tax_exclusive,tax_inclusive,no_tax'],
            'line_description' => ['nullable', 'string', 'max:5000'],
            'tax_rate' => ['nullable', 'in:bas_excluded,gst_free_income,gst_on_income'],
            'deposit_type' => ['nullable', 'in:percentage,amount'],
            'deposit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'first_due_date' => ['required', 'date'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $depositType = $data['deposit_type'] ?? 'percentage';

        if ($depositType === 'amount' && ! isset($data['deposit_amount'])) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'Deposit amount is required.',
            ]);
        }

        $invoice = $invoiceBuilder->createForBooking(
            $booking,
            (int) $data['installment_count'],
            $depositType === 'percentage' && isset($data['deposit_percentage']) ? (float) $data['deposit_percentage'] : null,
            Carbon::parse($data['first_due_date']),
            (int) $data['interval_days'],
            $depositType === 'amount' && isset($data['deposit_amount']) ? (float) $data['deposit_amount'] : null,
        );
        $invoice->update($this->editableInvoiceData($data, $booking));

        return response()->json([
            'message' => 'Invoice created successfully.',
            'record' => $this->serializeInvoice($invoice->refresh()->load('installments', 'booking')),
        ]);
    }

    public function update(Request $request, Booking $booking, InvoiceBuilder $invoiceBuilder): JsonResponse
    {
        $invoice = $booking->invoice()->with('installments')->firstOrFail();
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255', 'unique:invoices,invoice_number,'.$invoice->id],
            'issue_date' => ['nullable', 'date'],
            'amounts_are' => ['required', 'in:tax_exclusive,tax_inclusive,no_tax'],
            'line_description' => ['nullable', 'string', 'max:5000'],
            'tax_rate' => ['nullable', 'in:bas_excluded,gst_free_income,gst_on_income'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:12'],
            'deposit_type' => ['required', 'in:percentage,amount'],
            'deposit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:'.$invoice->total_amount],
            'first_due_date' => ['required', 'date'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        if ($data['deposit_type'] === 'percentage' && ! isset($data['deposit_percentage'])) {
            throw ValidationException::withMessages([
                'deposit_percentage' => 'Deposit percentage is required.',
            ]);
        }

        if ($data['deposit_type'] === 'amount' && ! isset($data['deposit_amount'])) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'Deposit amount is required.',
            ]);
        }

        if ($data['amounts_are'] !== 'no_tax' && ! isset($data['tax_rate'])) {
            throw ValidationException::withMessages([
                'tax_rate' => 'Tax rate is required.',
            ]);
        }

        $invoice->update([
            'invoice_number' => $data['invoice_number'],
            'issued_at' => isset($data['issue_date']) ? Carbon::parse($data['issue_date']) : $invoice->issued_at,
            ...$this->editableInvoiceData($data, $booking),
        ]);

        $invoice = $invoiceBuilder->rebuildInstallments(
            $invoice,
            (int) $data['installment_count'],
            $data['deposit_type'] === 'percentage' ? (float) $data['deposit_percentage'] : null,
            Carbon::parse($data['first_due_date']),
            (int) $data['interval_days'],
            $data['deposit_type'] === 'amount' ? (float) $data['deposit_amount'] : null,
        );

        return response()->json([
            'message' => 'Invoice updated successfully.',
            'record' => $this->serializeInvoice($invoice->load('invoiceStatus', 'installments.installmentStatus', 'booking')),
        ]);
    }

    private function editableInvoiceData(array $data, Booking $booking): array
    {
        $amountsAre = $data['amounts_are'] ?? 'tax_exclusive';

        return [
            'amounts_are' => $amountsAre,
            'line_description' => $data['line_description'] ?? $this->defaultInvoiceLineDescription($booking),
            'tax_rate' => $amountsAre === 'no_tax' ? null : ($data['tax_rate'] ?? 'gst_free_income'),
        ];
    }

    private function defaultInvoiceLineDescription(Booking $booking): string
    {
        $booking->loadMissing(['package.equipment', 'package.addOns']);

        $packageName = trim((string) ($booking->package?->name ?: 'Booking package'));
        $hoursLabel = filled($booking->total_hours) ? ' - '.$this->formatHoursLabel($booking->total_hours).' hrs' : '';
        $packageHeading = $packageName.$hoursLabel;
        $inclusions = collect([
            ...$booking->package?->equipment?->pluck('name')->filter()->all() ?? [],
            ...$booking->package?->addOns?->pluck('name')->filter()->all() ?? [],
        ])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($inclusions->isEmpty()) {
            return $packageHeading;
        }

        return $packageHeading.' inclusions:'."\n".implode("\n", $inclusions->map(fn ($item) => '- '.$item)->all());
    }

    public function show(CurrentTenant $currentTenant, Request $request, Invoice $invoice): View
    {
        if ($request->query('payment') === 'success') {
            $this->syncTenantCheckoutSession(
                $invoice,
                (string) $request->query('session_id', ''),
                (int) $request->query('installment', 0),
            );
        }

        $invoice->loadMissing(['booking.package', 'booking.addOns', 'booking.discount', 'installments']);

        return view('invoices.show', [
            'tenant' => $currentTenant->get(),
            'invoice' => $invoice,
        ]);
    }

    public function pdf(CurrentTenant $currentTenant, Booking $booking)
    {
        abort_unless($booking->tenant_id === $currentTenant->id(), 404);

        $invoice = $booking->invoice()
            ->with(['installments', 'booking.package', 'booking.addOns', 'booking.equipment', 'booking.discount'])
            ->firstOrFail();

        $tenant = $currentTenant->get();
        $businessLines = collect([
            $tenant?->name,
            $tenant?->abn ? 'ABN '.$tenant->abn : null,
            $tenant?->contact_email,
            $tenant?->contact_phone,
            $tenant?->address,
        ])->filter()->values()->all();

        $pdf = Pdf::loadView('pdf.invoices.show', [
            'tenant' => $tenant,
            'booking' => $booking,
            'invoice' => $invoice,
            'logo_data_uri' => $this->imageDataUri($tenant?->logo_path),
            'business_lines' => $businessLines,
            'line_description' => $invoice->line_description ?: $this->defaultInvoiceLineDescription($booking),
            'invoice_items' => $this->invoicePdfItems($booking),
            'amounts_are_label' => $this->amountsAreLabel($invoice->amounts_are ?: 'tax_exclusive'),
            'tax_rate_label' => $this->taxRateLabel($invoice->tax_rate),
        ])->setPaper('a4');

        $filename = str($invoice->invoice_number ?: 'invoice')->slug().'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function status(CurrentTenant $currentTenant, Request $request, Invoice $invoice): JsonResponse
    {
        $this->syncTenantCheckoutSession(
            $invoice,
            (string) $request->query('session_id', ''),
            (int) $request->query('installment', 0),
        );

        $invoice = Invoice::query()
            ->with(['booking', 'installments'])
            ->whereKey($invoice->getKey())
            ->firstOrFail();

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'amount_paid' => number_format((float) $invoice->amount_paid, 2, '.', ''),
                'balance_due' => number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2, '.', ''),
            ],
            'installments' => $invoice->installments->map(fn (InvoiceInstallment $installment) => [
                'id' => $installment->id,
                'status' => $installment->status,
                'paid_at_label' => DateFormatter::dateTime($installment->paid_at),
            ])->values()->all(),
        ]);
    }

    public function pay(CurrentTenant $currentTenant, Invoice $invoice, InvoiceInstallment $installment, StripeCheckoutLinkGenerator $stripeCheckoutLinkGenerator): RedirectResponse
    {
        abort_unless($installment->invoice_id === $invoice->id, 404);
        abort_if($installment->status === 'paid', 422, 'This installment has already been paid.');

        $invoice->loadMissing(['booking.package', 'booking.addOns']);
        $checkoutUrl = $stripeCheckoutLinkGenerator->forInstallment($invoice, $installment);

        return redirect()->away($checkoutUrl);
    }

    public function send(CurrentTenant $currentTenant, Booking $booking, StripeCheckoutLinkGenerator $stripeCheckoutLinkGenerator, TrackedEmailSender $trackedEmailSender): JsonResponse
    {
        $booking->loadMissing(['invoice.installments', 'package', 'addOns', 'tenant.users']);

        $invoice = $booking->invoice;

        if ($invoice === null) {
            throw ValidationException::withMessages([
                'invoice' => 'Create an invoice before sending it.',
            ]);
        }

        $installment = $invoice->installments->firstWhere('status', 'pending');

        if ($installment === null) {
            throw ValidationException::withMessages([
                'invoice' => 'All installments have already been paid.',
            ]);
        }

        $stripeCheckoutUrl = $stripeCheckoutLinkGenerator->forInstallment($invoice, $installment);
        $adminRecipients = $booking->tenant?->users
            ->filter(fn (User $user) => $user->pivot?->role === 'owner')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        $trackedEmailSender->send(
            new InvoiceIssuedMail($invoice, $installment, $stripeCheckoutUrl),
            [[
                'email' => $booking->customer_email,
                'name' => $booking->customer_name,
            ]],
            $adminRecipients,
            ['tenant' => $booking->tenant, 'context' => $invoice],
        );

        $invoice->load('installments');

        return response()->json([
            'message' => 'Invoice email sent successfully.',
            'record' => $this->serializeInvoice($invoice),
        ]);
    }

    public function recordManualPayment(CurrentTenant $currentTenant, Request $request, Booking $booking, InvoiceInstallment $installment): JsonResponse
    {
        $invoice = $booking->invoice()->with(['installments', 'tenant'])->firstOrFail();
        abort_unless($installment->invoice_id === $invoice->id && $booking->tenant_id === $currentTenant->id(), 404);
        abort_if($installment->status === 'paid', 422, 'This installment has already been paid.');

        $data = $request->validate([
            'payment_method' => ['required', 'in:bank_transfer,cash,other'],
            'paid_at' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $paidInstallmentStatus = $invoice->tenant
            ? TenantStatuses::firstOrCreateWorkspaceStatus($invoice->tenant, TenantStatuses::SCOPE_INVOICE_INSTALLMENT, 'paid')
            : null;

        $installment->update([
            'invoice_installment_status_id' => $paidInstallmentStatus?->id,
            'status' => $paidInstallmentStatus?->name ?? 'paid',
            'paid_at' => Carbon::parse($data['paid_at']),
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_notes' => $data['payment_notes'] ?? null,
        ]);

        $invoice = $this->syncInvoiceAndBookingAfterPayment($invoice);

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'record' => $this->serializeInvoice($invoice->load('invoiceStatus', 'installments.installmentStatus', 'booking')),
        ]);
    }

    private function syncTenantCheckoutSession(Invoice $invoice, string $sessionId, int $installmentId): void
    {
        if ($sessionId === '' || $installmentId <= 0) {
            return;
        }

        $invoice->loadMissing(['tenant', 'installments', 'booking']);
        $secretKey = (string) $invoice->tenant?->stripe_secret;

        if ($secretKey === '') {
            return;
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        if ($response->failed() || $response->json('payment_status') !== 'paid') {
            return;
        }

        $metadata = $response->json('metadata') ?? [];
        $sessionInvoiceId = isset($metadata['invoice_id']) ? (int) $metadata['invoice_id'] : null;
        $sessionInstallmentId = isset($metadata['installment_id']) ? (int) $metadata['installment_id'] : null;

        if ($sessionInvoiceId !== (int) $invoice->id || $sessionInstallmentId !== $installmentId) {
            return;
        }

        $installment = $invoice->installments->firstWhere('id', $installmentId);

        if (! $installment instanceof InvoiceInstallment) {
            return;
        }

        if ($installment->status !== 'paid') {
            $paidInstallmentStatus = $invoice->tenant
                ? TenantStatuses::firstOrCreateWorkspaceStatus($invoice->tenant, TenantStatuses::SCOPE_INVOICE_INSTALLMENT, 'paid')
                : null;

            $installment->update([
                'invoice_installment_status_id' => $paidInstallmentStatus?->id,
                'status' => $paidInstallmentStatus?->name ?? 'paid',
                'paid_at' => now(),
            ]);
        }

        $invoice->load('installments');
        $this->syncInvoiceAndBookingAfterPayment($invoice);
    }

    private function syncInvoiceAndBookingAfterPayment(Invoice $invoice): Invoice
    {
        $invoice->load(['tenant', 'installments', 'booking']);
        $amountPaid = (float) $invoice->installments->where('status', 'paid')->sum('amount');
        $invoiceStatusName = $amountPaid >= (float) $invoice->total_amount ? 'paid' : 'partially_paid';
        $invoiceStatus = $invoice->tenant
            ? TenantStatuses::firstOrCreateWorkspaceStatus($invoice->tenant, TenantStatuses::SCOPE_INVOICE, $invoiceStatusName)
            : null;
        $invoice->update([
            'amount_paid' => number_format($amountPaid, 2, '.', ''),
            'invoice_status_id' => $invoiceStatus?->id,
            'status' => $invoiceStatus?->name ?? $invoiceStatusName,
        ]);

        $booking = $invoice->booking;

        if ($booking !== null && in_array($booking->status, ['pending', 'confirmed'], true)) {
            $bookingStatusName = $amountPaid >= (float) $invoice->total_amount ? 'completed' : 'confirmed';
            $bookingStatus = TenantStatuses::firstOrCreateWorkspaceStatus($booking->tenant, TenantStatuses::SCOPE_BOOKING, $bookingStatusName);
            $booking->update([
                'booking_status_id' => $bookingStatus?->id,
                'status' => $bookingStatus?->name ?? $bookingStatusName,
            ]);
        }

        return $invoice->refresh()->load(['tenant', 'installments.installmentStatus', 'booking']);
    }

    private function serializeInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['installments', 'booking']);
        $firstInstallment = $invoice->installments->first();
        $secondInstallment = $invoice->installments->skip(1)->first();
        $depositAmount = (float) ($firstInstallment?->amount ?? 0);
        $totalAmount = (float) $invoice->total_amount;

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status_id' => $invoice->invoice_status_id,
            'status' => $invoice->status,
            'status_label' => $invoice->invoiceStatus?->label() ?? str($invoice->status)->replace('_', ' ')->title()->toString(),
            'issued_at' => DateFormatter::inputDate($invoice->issued_at),
            'issued_at_label' => DateFormatter::date($invoice->issued_at),
            'amounts_are' => $invoice->amounts_are ?: 'tax_exclusive',
            'amounts_are_label' => $this->amountsAreLabel($invoice->amounts_are ?: 'tax_exclusive'),
            'line_description' => $invoice->line_description ?: $this->defaultInvoiceLineDescription($invoice->booking),
            'tax_rate' => $invoice->tax_rate,
            'tax_rate_label' => $this->taxRateLabel($invoice->tax_rate),
            'total_amount' => number_format((float) $invoice->total_amount, 2, '.', ''),
            'amount_paid' => number_format((float) $invoice->amount_paid, 2, '.', ''),
            'balance_due' => number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2, '.', ''),
            'deposit_amount' => number_format($depositAmount, 2, '.', ''),
            'deposit_percentage' => $totalAmount > 0 ? number_format(($depositAmount / $totalAmount) * 100, 2, '.', '') : '0.00',
            'installment_count' => $invoice->installments->count(),
            'first_due_date' => DateFormatter::inputDate($firstInstallment?->due_date),
            'interval_days' => $firstInstallment && $secondInstallment
                ? max(1, $firstInstallment->due_date->diffInDays($secondInstallment->due_date))
                : 30,
            'public_url' => route('invoices.show', $invoice),
            'update_url' => route('admin.bookings.invoice.update', $invoice->booking),
            'send_url' => route('admin.bookings.invoice.send', $invoice->booking),
            'installments' => $invoice->installments->map(fn (InvoiceInstallment $installment) => [
                'id' => $installment->id,
                'label' => $installment->label,
                'amount' => number_format((float) $installment->amount, 2, '.', ''),
                'due_date' => DateFormatter::inputDate($installment->due_date),
                'due_date_label' => DateFormatter::date($installment->due_date),
                'status_id' => $installment->invoice_installment_status_id,
                'status' => $installment->status,
                'status_label' => $installment->installmentStatus?->label() ?? str($installment->status)->replace('_', ' ')->title()->toString(),
                'paid_at_label' => DateFormatter::dateTime($installment->paid_at),
                'payment_method' => $installment->payment_method,
                'payment_method_label' => $this->paymentMethodLabel($installment->payment_method),
                'payment_reference' => $installment->payment_reference,
                'payment_notes' => $installment->payment_notes,
                'record_payment_url' => route('admin.bookings.invoice.installments.manual-payment', [$invoice->booking, $installment]),
            ])->values()->all(),
        ];
    }

    private function paymentMethodLabel(?string $value): string
    {
        return [
            'bank_transfer' => 'Bank transfer',
            'cash' => 'Cash',
            'other' => 'Other',
        ][$value] ?? '';
    }

    private function amountsAreLabel(string $value): string
    {
        return [
            'tax_exclusive' => 'Tax exclusive',
            'tax_inclusive' => 'Tax inclusive',
            'no_tax' => 'No Tax',
        ][$value] ?? 'Tax exclusive';
    }

    private function taxRateLabel(?string $value): string
    {
        return [
            'bas_excluded' => 'BAS Excluded',
            'gst_free_income' => 'GST Free Income',
            'gst_on_income' => 'GST on Income',
        ][$value] ?? 'No tax';
    }

    private function formatHoursLabel(mixed $value): string
    {
        $hours = (float) $value;

        if (fmod($hours, 1.0) === 0.0) {
            return (string) (int) $hours;
        }

        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    private function invoicePdfItems(Booking $booking): array
    {
        $booking->loadMissing(['package', 'addOns.inventoryItemCategory', 'equipment', 'discount']);

        $items = [];
        $packageAmount = (float) ($booking->package_price ?? 0);

        if ($booking->package || $packageAmount > 0) {
            $items[] = [
                'type' => 'package',
                'name' => $booking->package?->name ?: 'Package',
                'description' => $booking->package?->description ?: $this->defaultInvoiceLineDescription($booking),
                'description_lines' => [],
                'price' => $packageAmount,
                'quantity' => 1,
                'discount_label' => 'No discount',
                'amount' => $packageAmount,
            ];
        }

        foreach ($booking->equipment as $equipment) {
            $items[] = [
                'type' => 'equipment',
                'name' => $equipment->name,
                'description' => $equipment->description ?: ($equipment->category ?: 'Equipment'),
                'description_lines' => [],
                'price' => (float) ($equipment->daily_rate ?? 0),
                'quantity' => 1,
                'discount_label' => $this->invoicePdfDiscountLabel(
                    $equipment->pivot?->discount_type,
                    $equipment->pivot?->discount_value,
                    (float) ($equipment->pivot?->discount_percentage ?? 0),
                ),
                'amount' => (float) $equipment->discountedDailyRateForBooking(
                    $equipment->pivot?->discount_type,
                    $equipment->pivot?->discount_value,
                    (float) ($equipment->pivot?->discount_percentage ?? 0),
                ),
            ];
        }

        foreach ($booking->addOns as $addOn) {
            $items[] = [
                'type' => 'add_on',
                'name' => $addOn->name,
                'description' => $addOn->description ?: ($addOn->inventoryItemCategory?->name ?: 'Add-on'),
                'description_lines' => array_values(array_filter([
                    $addOn->duration ? 'Duration: '.$addOn->duration : null,
                    filled($addOn->description) ? trim((string) $addOn->description) : null,
                ])),
                'price' => (float) ($addOn->unit_price ?? 0),
                'quantity' => 1,
                'discount_label' => $this->invoicePdfDiscountLabel(
                    $addOn->pivot?->discount_type,
                    $addOn->pivot?->discount_value,
                    (float) ($addOn->pivot?->discount_percentage ?? 0),
                ),
                'amount' => (float) $addOn->discountedUnitPriceForBookingSelection(
                    $addOn->pivot?->discount_type,
                    $addOn->pivot?->discount_value,
                    (float) ($addOn->pivot?->discount_percentage ?? 0),
                ),
            ];
        }

        if ((float) ($booking->travel_fee ?? 0) > 0) {
            $items[] = [
                'type' => 'travel_fee',
                'name' => 'Travel Fee',
                'description' => 'Travel fee for '.number_format((float) ($booking->travel_distance_km ?? 0), 2).' km',
                'description_lines' => [],
                'price' => (float) $booking->travel_fee,
                'quantity' => 1,
                'discount_label' => 'No discount',
                'amount' => (float) $booking->travel_fee,
            ];
        }

        if ((float) ($booking->discount_amount ?? 0) > 0) {
            $items[] = [
                'type' => 'booking_discount',
                'name' => 'Booking Discount',
                'description' => $booking->discount
                    ? $booking->discount->code.' - '.$booking->discount->name
                    : 'Applied booking discount',
                'description_lines' => [],
                'price' => -(float) $booking->discount_amount,
                'quantity' => 1,
                'discount_label' => 'Included',
                'amount' => -(float) $booking->discount_amount,
            ];
        }

        return $items;
    }

    private function invoicePdfDiscountLabel(mixed $discountType = null, mixed $discountValue = null, float $legacyPercentage = 0): string
    {
        if ($discountType === 'amount' && (float) ($discountValue ?? 0) > 0) {
            return '$'.number_format((float) $discountValue, 2);
        }

        $percentage = (float) ($legacyPercentage > 0 ? $legacyPercentage : ($discountValue ?? 0));

        if ($percentage > 0) {
            return number_format($percentage, 2).'%';
        }

        return 'No discount';
    }

    private function imageDataUri(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $content = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }

    private function serializeInvoiceListRecord(Invoice $invoice): array
    {
        $booking = $invoice->booking;
        $nextInstallment = $invoice->installments->firstWhere('status', 'pending');

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status_id' => $invoice->invoice_status_id,
            'status' => $invoice->status,
            'status_label' => $invoice->invoiceStatus?->label() ?? str($invoice->status)->replace('_', ' ')->title()->toString(),
            'total_amount' => number_format((float) $invoice->total_amount, 2, '.', ''),
            'amount_paid' => number_format((float) $invoice->amount_paid, 2, '.', ''),
            'balance_due' => number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2, '.', ''),
            'issued_at_label' => DateFormatter::date($invoice->issued_at),
            'customer_name' => $booking?->customer_name,
            'customer_email' => $booking?->customer_email,
            'package_name' => $booking?->package?->name,
            'event_date_label' => DateFormatter::date($booking?->event_date),
            'next_due_label' => DateFormatter::date($nextInstallment?->due_date),
            'public_url' => route('invoices.show', $invoice),
            'booking_show_url' => $booking ? route('admin.bookings.show', $booking) : null,
        ];
    }
}
