<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceIssuedMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Support\InvoiceBuilder;
use App\Models\User;
use App\Support\StripeCheckoutLinkGenerator;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
                    'settings' => route('settings.index'),
                    'logout' => route('logout'),
                ],
                'invoiceStatuses' => ['all', 'draft', 'issued', 'partially_paid', 'paid', 'cancelled'],
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
            'deposit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'first_due_date' => ['required', 'date'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $invoice = $invoiceBuilder->createForBooking(
            $booking,
            (int) $data['installment_count'],
            isset($data['deposit_percentage']) ? (float) $data['deposit_percentage'] : null,
            Carbon::parse($data['first_due_date']),
            (int) $data['interval_days'],
        );

        return response()->json([
            'message' => 'Invoice created successfully.',
            'record' => $this->serializeInvoice($invoice),
        ]);
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
                'paid_at_label' => $installment->paid_at?->format('d M Y g:i A'),
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

    public function send(CurrentTenant $currentTenant, Booking $booking, StripeCheckoutLinkGenerator $stripeCheckoutLinkGenerator): JsonResponse
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

        Mail::to($booking->customer_email)
            ->cc($adminRecipients)
            ->send(new InvoiceIssuedMail($invoice, $installment, $stripeCheckoutUrl));

        $invoice->load('installments');

        return response()->json([
            'message' => 'Invoice email sent successfully.',
            'record' => $this->serializeInvoice($invoice),
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
            $installment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        $invoice->load('installments');
        $amountPaid = (float) $invoice->installments->where('status', 'paid')->sum('amount');
        $invoice->update([
            'amount_paid' => number_format($amountPaid, 2, '.', ''),
            'status' => $amountPaid >= (float) $invoice->total_amount ? 'paid' : 'partially_paid',
        ]);

        $booking = $invoice->booking;

        if ($booking !== null && in_array($booking->status, ['pending', 'confirmed'], true)) {
            $booking->update([
                'status' => $amountPaid >= (float) $invoice->total_amount ? 'completed' : 'confirmed',
            ]);
        }
    }

    private function serializeInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'total_amount' => number_format((float) $invoice->total_amount, 2, '.', ''),
            'amount_paid' => number_format((float) $invoice->amount_paid, 2, '.', ''),
            'balance_due' => number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2, '.', ''),
            'public_url' => route('invoices.show', $invoice),
            'send_url' => route('admin.bookings.invoice.send', $invoice->booking),
            'installments' => $invoice->installments->map(fn (InvoiceInstallment $installment) => [
                'id' => $installment->id,
                'label' => $installment->label,
                'amount' => number_format((float) $installment->amount, 2, '.', ''),
                'due_date' => $installment->due_date?->format('Y-m-d'),
                'due_date_label' => $installment->due_date?->format('d M Y'),
                'status' => $installment->status,
                'paid_at_label' => $installment->paid_at?->format('d M Y g:i A'),
            ])->values()->all(),
        ];
    }

    private function serializeInvoiceListRecord(Invoice $invoice): array
    {
        $booking = $invoice->booking;
        $nextInstallment = $invoice->installments->firstWhere('status', 'pending');

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'status_label' => str($invoice->status)->replace('_', ' ')->title()->toString(),
            'total_amount' => number_format((float) $invoice->total_amount, 2, '.', ''),
            'amount_paid' => number_format((float) $invoice->amount_paid, 2, '.', ''),
            'balance_due' => number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2, '.', ''),
            'issued_at_label' => $invoice->issued_at?->format('d M Y'),
            'customer_name' => $booking?->customer_name,
            'customer_email' => $booking?->customer_email,
            'package_name' => $booking?->package?->name,
            'event_date_label' => $booking?->event_date?->format('d M Y'),
            'next_due_label' => $nextInstallment?->due_date?->format('d M Y'),
            'public_url' => route('invoices.show', $invoice),
            'booking_show_url' => $booking ? route('admin.bookings.show', $booking) : null,
        ];
    }
}
