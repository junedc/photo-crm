<?php

namespace App\Http\Controllers;

use App\Mail\AdminBookingCreatedMail;
use App\Mail\CustomerBookingCreatedMail;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\ClientPortalAccess;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Equipment;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Lead;
use App\Models\Package;
use App\Models\PackageHourlyPrice;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Tenant;
use App\Models\TenantVendor;
use App\Models\User;
use App\Services\ClientPortalService;
use App\Support\BookingAddonsPdfGenerator;
use App\Support\BookingDiscountResolver;
use App\Support\BookingPricing;
use App\Support\DateFormatter;
use App\Support\InvoiceBuilder;
use App\Support\PackagePriceResolver;
use App\Support\StripeCheckoutLinkGenerator;
use App\Support\TaskAssignees;
use App\Support\TenantStatuses;
use App\Support\TrackedEmailSender;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): View|JsonResponse
    {
        return $this->renderAdminBookingsPage($currentTenant, null, $request);
    }

    public function show(CurrentTenant $currentTenant, Booking $booking): View|JsonResponse
    {
        if (request()->expectsJson()) {
            $booking->loadMissing(['package', 'addOns', 'equipment', 'discount', 'invoice.installments']);

            return response()->json([
                'record' => $this->serializeBooking($booking),
            ]);
        }

        return $this->renderAdminBookingDetailPage($currentTenant, $booking);
    }

    public function calendar(CurrentTenant $currentTenant): View
    {
        return $this->renderAdminCalendarPage($currentTenant);
    }

    public function adminCreate(CurrentTenant $currentTenant): View
    {
        return $this->renderAdminBookingCreatePage($currentTenant);
    }

    public function quotePdf(CurrentTenant $currentTenant, Booking $booking)
    {
        abort_unless($booking->tenant_id === $currentTenant->id(), 404);

        $attachment = app(BookingAddonsPdfGenerator::class)->makeForBooking($booking);

        abort_if($attachment === null, 404);

        return response($attachment->content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$attachment->name.'"',
        ]);
    }

    public function quotes(CurrentTenant $currentTenant): View
    {
        return $this->renderAdminQuotesPage($currentTenant);
    }

    public function create(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $addOns = InventoryItem::query()
            ->where('category', 'add-on')
            ->where('is_publicly_displayed', true)
            ->latest()
            ->get();

        return view('bookings.create', [
            'tenant' => $tenant,
            'packages' => Package::query()
                ->where('is_active', true)
                ->with([
                    'equipment',
                    'addOns' => fn ($query) => $query->where('is_publicly_displayed', true),
                    'hourlyPrices',
                ])
                ->latest()
                ->get(),
            'addOns' => $addOns,
            'addOnCategories' => $addOns
                ->pluck('type')
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'discounts' => $this->availableDiscounts(),
            'leadToken' => null,
            'termsUrl' => route('bookings.terms'),
            'workspaceAddress' => $tenant?->address,
            'travelFreeKilometers' => (float) ($tenant?->travel_free_kilometers ?? config('pricing.travel_free_kilometers', 0)),
            'travelFeePerKilometer' => (float) ($tenant?->travel_fee_per_kilometer ?? config('pricing.travel_fee_per_kilometer', 0)),
            'depositPercentage' => (float) ($tenant?->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)),
            'customerPackageDiscountPercentage' => $this->customerPackageDiscountPercentage($tenant),
            'googleMapsApiKey' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
        ]);
    }

    public function terms(CurrentTenant $currentTenant): View
    {
        return view('bookings.terms', [
            'tenant' => $currentTenant->get(),
        ]);
    }

    public function respondToQuote(CurrentTenant $currentTenant, Booking $booking, string $response): View
    {
        $status = $response === 'accept' ? 'accepted' : 'rejected';
        $statusRecord = $currentTenant->get()
            ? TenantStatuses::firstOrCreateWorkspaceStatus($currentTenant->get(), TenantStatuses::SCOPE_QUOTE_RESPONSE, $status)
            : null;

        $booking->update([
            'quote_response_status_id' => $statusRecord?->id,
            'customer_response_status' => $status,
            'customer_responded_at' => now(),
        ]);
        $booking->refresh();
        $booking->loadMissing(['package', 'addOns']);

        return view('bookings.quote-response', [
            'tenant' => $currentTenant->get(),
            'booking' => $booking,
            'response' => $status,
        ]);
    }

    public function autosaveLead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_token' => ['nullable', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'event_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $lead = $this->resolveLead($data['lead_token'] ?? null);
        $hasMeaningfulInput = collect([
            $data['customer_name'] ?? null,
            $data['customer_email'] ?? null,
            $data['customer_phone'] ?? null,
            $data['event_date'] ?? null,
            $data['venue'] ?? null,
            $data['event_location'] ?? null,
            $data['notes'] ?? null,
        ])->contains(fn ($value) => filled($value));

        if (! $hasMeaningfulInput && $lead === null) {
            return response()->json([
                'saved' => false,
                'lead_token' => null,
            ]);
        }

        $lead ??= new Lead;
        $lead->fill([
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'venue' => $data['venue'] ?? null,
            'event_location' => $data['event_location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'draft',
            'last_activity_at' => now(),
        ]);
        $lead->save();

        return response()->json([
            'saved' => true,
            'lead_token' => $lead->token,
            'lead_id' => $lead->id,
            'saved_at' => optional($lead->last_activity_at)->toIso8601String(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->id();

        $data = $request->validate([
            'lead_token' => ['nullable', 'uuid'],
            'booking_kind' => ['nullable', Rule::in($this->bookingKinds())],
            'entry_name' => ['nullable', 'string', 'max:255'],
            'entry_description' => ['nullable', 'string'],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('packages', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'event_type' => ['required', Rule::in($this->eventTypes())],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/'],
            'end_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/', 'after:start_time'],
            'total_hours' => ['required', 'numeric', 'min:0.25'],
            'event_location' => ['required', 'string', 'max:255'],
            'travel_distance_km' => ['nullable', 'numeric', 'min:0'],
            'travel_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'package_hourly_price_id' => [
                'nullable',
                'integer',
                Rule::exists('package_hourly_prices', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
            'discount_id' => [
                'nullable',
                'integer',
                Rule::exists('discounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
            'add_on_ids' => ['nullable', 'array'],
            'add_on_ids.*' => [
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('category', 'add-on')),
            ],
            'add_on_discount_types' => ['nullable', 'array'],
            'add_on_discount_types.*' => ['nullable', Rule::in(['percentage', 'amount'])],
            'add_on_discount_values' => ['nullable', 'array'],
            'add_on_discount_values.*' => ['nullable', 'numeric', 'min:0'],
            'equipment_ids' => ['nullable', 'array'],
            'equipment_ids.*' => [
                'integer',
                Rule::exists('equipment', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
            'equipment_discount_types' => ['nullable', 'array'],
            'equipment_discount_types.*' => ['nullable', Rule::in(['percentage', 'amount'])],
            'equipment_discount_values' => ['nullable', 'array'],
            'equipment_discount_values.*' => ['nullable', 'numeric', 'min:0'],
            'send_quote_email' => ['nullable', 'boolean'],
            'send_admin_quote_email' => ['nullable', 'boolean'],
        ], [
            'start_time.regex' => 'Start hour must be on the hour or half hour.',
            'end_time.regex' => 'End hour must be on the hour or half hour.',
        ]);
        $sendQuoteEmail = Auth::check() ? $request->boolean('send_quote_email', true) : true;
        $sendAdminQuoteEmail = Auth::check() ? $request->boolean('send_admin_quote_email', true) : true;

        $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $addOnDiscountSelections = $this->normalizedItemDiscountSelections(
            $data['add_on_discount_types'] ?? [],
            $data['add_on_discount_values'] ?? [],
        );
        $equipmentDiscountSelections = $this->normalizedItemDiscountSelections(
            $data['equipment_discount_types'] ?? [],
            $data['equipment_discount_values'] ?? [],
        );
        $package = $this->resolvePackageForSelection($data);
        $this->ensurePackageTimingSelection($package, $data);
        $selectedEquipment = $this->resolveEquipmentSelection($equipmentIds);
        $lead = $this->resolveLead($data['lead_token'] ?? null);
        unset($data['lead_token']);
        unset($data['send_quote_email'], $data['send_admin_quote_email']);
        unset($data['add_on_ids']);
        unset($data['equipment_ids']);
        unset($data['add_on_discount_types'], $data['add_on_discount_values']);
        unset($data['equipment_discount_types'], $data['equipment_discount_values']);
        $data['booking_kind'] = $data['booking_kind'] ?? 'customer';
        $data['customer_id'] = $this->resolveCustomer($data)->id;
        $data['package_price'] = $this->resolvePackagePrice($data, $package, ! $request->expectsJson());
        $data['discount_id'] = null;
        $data['discount_amount'] = 0;

        $booking = Booking::query()->create($data);
        $booking->addOns()->sync($this->bookingItemSyncPayload($addOnIds, $addOnDiscountSelections));
        $booking->equipment()->sync($this->bookingItemSyncPayload($equipmentIds, $equipmentDiscountSelections));
        $this->syncPackageActionTasks($booking);
        $booking->load(['package', 'tenant.users', 'addOns', 'equipment']);
        $this->markLeadAsBooked($lead, $booking);
        $this->sendBookingEmails($booking, $sendQuoteEmail, $sendAdminQuoteEmail);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking created successfully.',
                'record' => $this->serializeBooking($booking),
            ]);
        }

        return redirect()
            ->route('bookings.create')
            ->with('status', 'Quote request sent successfully.')
            ->withInput($request->only([
                'customer_name',
                'customer_email',
                'customer_phone',
                'event_type',
                'venue',
                'event_date',
                'start_time',
                'end_time',
                'total_hours',
                'event_location',
                'notes',
                'lead_token',
            ]));
    }

    public function bookNow(
        Request $request,
        InvoiceBuilder $invoiceBuilder,
        StripeCheckoutLinkGenerator $stripeCheckoutLinkGenerator,
    ): RedirectResponse|JsonResponse {
        $tenantId = app(CurrentTenant::class)->id();

        $data = $request->validate([
            'lead_token' => ['nullable', 'uuid'],
            'booking_kind' => ['nullable', Rule::in($this->bookingKinds())],
            'entry_name' => ['nullable', 'string', 'max:255'],
            'entry_description' => ['nullable', 'string'],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('packages', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'event_type' => ['required', Rule::in($this->eventTypes())],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/'],
            'end_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/', 'after:start_time'],
            'total_hours' => ['required', 'numeric', 'min:0.25'],
            'event_location' => ['required', 'string', 'max:255'],
            'travel_distance_km' => ['nullable', 'numeric', 'min:0'],
            'travel_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'terms_accepted' => ['accepted'],
            'package_hourly_price_id' => [
                'nullable',
                'integer',
                Rule::exists('package_hourly_prices', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
            'discount_id' => [
                'nullable',
                'integer',
                Rule::exists('discounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
            'add_on_ids' => ['nullable', 'array'],
            'add_on_ids.*' => [
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('category', 'add-on')),
            ],
            'add_on_discount_types' => ['nullable', 'array'],
            'add_on_discount_types.*' => ['nullable', Rule::in(['percentage', 'amount'])],
            'add_on_discount_values' => ['nullable', 'array'],
            'add_on_discount_values.*' => ['nullable', 'numeric', 'min:0'],
            'equipment_ids' => ['nullable', 'array'],
            'equipment_ids.*' => [
                'integer',
                Rule::exists('equipment', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
            'equipment_discount_types' => ['nullable', 'array'],
            'equipment_discount_types.*' => ['nullable', Rule::in(['percentage', 'amount'])],
            'equipment_discount_values' => ['nullable', 'array'],
            'equipment_discount_values.*' => ['nullable', 'numeric', 'min:0'],
        ], [
            'terms_accepted.accepted' => 'Please accept the terms and conditions before continuing to payment.',
            'start_time.regex' => 'Start hour must be on the hour or half hour.',
            'end_time.regex' => 'End hour must be on the hour or half hour.',
        ]);

        $package = $this->resolvePackageForSelection($data);
        $this->ensurePackageTimingSelection($package, $data);

        [$booking, $invoice, $depositInstallment] = DB::transaction(function () use ($data, $invoiceBuilder, $package) {
            $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $addOnDiscountSelections = $this->normalizedItemDiscountSelections(
                $data['add_on_discount_types'] ?? [],
                $data['add_on_discount_values'] ?? [],
            );
            $equipmentDiscountSelections = $this->normalizedItemDiscountSelections(
                $data['equipment_discount_types'] ?? [],
                $data['equipment_discount_values'] ?? [],
            );
            $selectedEquipment = $this->resolveEquipmentSelection($equipmentIds);
            $lead = $this->resolveLead($data['lead_token'] ?? null);
            $bookingData = $data;
            unset($bookingData['lead_token'], $bookingData['terms_accepted'], $bookingData['add_on_ids'], $bookingData['equipment_ids'], $bookingData['add_on_discount_types'], $bookingData['add_on_discount_values'], $bookingData['equipment_discount_types'], $bookingData['equipment_discount_values']);
            $bookingData['booking_kind'] = $bookingData['booking_kind'] ?? 'customer';
            $quoteResponseStatus = app(CurrentTenant::class)->get()
                ? TenantStatuses::firstOrCreateWorkspaceStatus(app(CurrentTenant::class)->get(), TenantStatuses::SCOPE_QUOTE_RESPONSE, 'accepted')
                : null;
            $bookingData['quote_response_status_id'] = $quoteResponseStatus?->id;
            $bookingData['customer_response_status'] = 'accepted';
            $bookingData['customer_responded_at'] = now();
            $bookingData['customer_id'] = $this->resolveCustomer($bookingData)->id;
            $bookingData['package_price'] = $this->resolvePackagePrice($bookingData, $package, true);
            [$discountId, $discountAmount] = $this->resolveDiscountSelection($bookingData, $package, $bookingData['package_price']);
            $bookingData['discount_id'] = $discountId;
            $bookingData['discount_amount'] = $discountAmount;

            $booking = Booking::query()->create($bookingData);
            $booking->addOns()->sync($this->bookingItemSyncPayload($addOnIds, $addOnDiscountSelections));
            $booking->equipment()->sync($this->bookingItemSyncPayload($equipmentIds, $equipmentDiscountSelections));
            $this->syncPackageActionTasks($booking);
            $booking->load(['package', 'tenant.users', 'addOns', 'equipment']);
            $this->markLeadAsBooked($lead, $booking);

            $eventDate = Carbon::parse($booking->event_date);
            $firstDueDate = now()->startOfDay();
            $intervalDays = max($firstDueDate->diffInDays($eventDate, false), 1);
            $invoice = $invoiceBuilder->createForBooking(
                $booking,
                2,
                null,
                $firstDueDate,
                $intervalDays,
            );
            $depositInstallment = $invoice->installments->firstWhere('sequence', 1);

            return [
                $booking,
                $invoice,
                $depositInstallment,
            ];
        });

        $this->sendBookingEmails($booking);

        try {
            $checkoutUrl = $stripeCheckoutLinkGenerator->forInstallment($invoice, $depositInstallment);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Booking created successfully.',
                    'checkout_url' => $checkoutUrl,
                    'record' => $this->serializeBooking($booking),
                ]);
            }

            return redirect()->away($checkoutUrl);
        } catch (ValidationException $exception) {
            $message = $exception->validator->errors()->first('stripe')
                ?: 'Booking saved, but payment could not be started right now.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'record' => $this->serializeBooking($booking),
                    'booking_saved' => true,
                    'payment_pending' => true,
                ], 202);
            }

            return redirect()
                ->route('bookings.create')
                ->with('warning', sprintf(
                    'Booking %s was saved, but payment could not be started. %s',
                    $booking->quote_number,
                    $message,
                ));
        }
    }

    public function update(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->id();
        $isFullEdit = $request->hasAny([
            'booking_kind',
            'package_id',
            'customer_name',
            'customer_email',
            'customer_phone',
            'event_type',
            'venue',
            'event_date',
            'start_time',
            'end_time',
            'total_hours',
            'event_location',
            'discount_id',
            'add_on_ids',
            'equipment_ids',
        ]);

        if (! $isFullEdit) {
            $data = $request->validate([
                'booking_status_id' => ['required', Rule::exists('workspace_statuses', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('scope', TenantStatuses::SCOPE_BOOKING))],
                'notes' => ['nullable', 'string'],
            ]);

            $status = $this->workspaceStatusById($tenantId, TenantStatuses::SCOPE_BOOKING, $data['booking_status_id']);

            $booking->update([
                'booking_status_id' => $status?->id,
                'status' => $status?->name ?? $booking->status,
                'notes' => $data['notes'] ?? null,
            ]);
        } else {
            $data = $request->validate([
                'booking_status_id' => ['required', Rule::exists('workspace_statuses', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('scope', TenantStatuses::SCOPE_BOOKING))],
                'quote_response_status_id' => ['required', Rule::exists('workspace_statuses', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('scope', TenantStatuses::SCOPE_QUOTE_RESPONSE))],
                'booking_kind' => ['nullable', Rule::in($this->bookingKinds())],
                'entry_name' => ['nullable', 'string', 'max:255'],
                'entry_description' => ['nullable', 'string'],
                'package_id' => [
                    'required',
                    'integer',
                    Rule::exists('packages', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)),
                ],
                'customer_name' => ['required', 'string', 'max:255'],
                'customer_email' => ['required', 'email', 'max:255'],
                'customer_phone' => ['required', 'string', 'max:50'],
                'event_type' => ['required', Rule::in($this->eventTypes())],
                'venue' => ['nullable', 'string', 'max:255'],
                'event_date' => ['required', 'date'],
                'start_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/'],
                'end_time' => ['required', 'date_format:H:i', 'regex:/^\d{2}:(00|30)$/', 'after:start_time'],
                'total_hours' => ['required', 'numeric', 'min:0.25'],
                'event_location' => ['required', 'string', 'max:255'],
                'travel_distance_km' => ['nullable', 'numeric', 'min:0'],
                'travel_fee' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
                'package_hourly_price_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('package_hourly_prices', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)),
                ],
                'discount_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('discounts', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)),
                ],
                'add_on_ids' => ['nullable', 'array'],
                'add_on_ids.*' => [
                    'integer',
                    Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('category', 'add-on')),
                ],
                'add_on_discount_types' => ['nullable', 'array'],
                'add_on_discount_types.*' => ['nullable', Rule::in(['percentage', 'amount'])],
                'add_on_discount_values' => ['nullable', 'array'],
                'add_on_discount_values.*' => ['nullable', 'numeric', 'min:0'],
                'equipment_ids' => ['nullable', 'array'],
                'equipment_ids.*' => [
                    'integer',
                    Rule::exists('equipment', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)),
                ],
                'equipment_discount_types' => ['nullable', 'array'],
                'equipment_discount_types.*' => ['nullable', Rule::in(['percentage', 'amount'])],
                'equipment_discount_values' => ['nullable', 'array'],
                'equipment_discount_values.*' => ['nullable', 'numeric', 'min:0'],
            ], [
                'start_time.regex' => 'Start hour must be on the hour or half hour.',
                'end_time.regex' => 'End hour must be on the hour or half hour.',
            ]);

            $status = $this->workspaceStatusById($tenantId, TenantStatuses::SCOPE_BOOKING, $data['booking_status_id']);
            $data['booking_status_id'] = $status?->id;
            $data['status'] = $status?->name ?? $booking->status;
            $quoteResponseStatus = $this->workspaceStatusById($tenantId, TenantStatuses::SCOPE_QUOTE_RESPONSE, $data['quote_response_status_id']);
            $nextQuoteResponse = $quoteResponseStatus?->name ?? 'pending';
            $data['quote_response_status_id'] = $quoteResponseStatus?->id;
            $data['customer_response_status'] = $nextQuoteResponse;
            $data['customer_responded_at'] = $nextQuoteResponse === 'pending'
                ? null
                : ($booking->customer_response_status === $nextQuoteResponse && $booking->customer_responded_at
                    ? $booking->customer_responded_at
                    : now());

            if ($this->bookingInvoiceAmountLocked($booking) && $this->bookingFinancialSelectionChanged($booking, $data)) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice amounts cannot be changed after the invoice has been partially or fully paid.',
                ]);
            }

            $package = $this->resolvePackageForSelection($data);
            $this->ensurePackageTimingSelection($package, $data);
            $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $addOnDiscountSelections = $this->normalizedItemDiscountSelections(
                $data['add_on_discount_types'] ?? [],
                $data['add_on_discount_values'] ?? [],
            );
            $equipmentDiscountSelections = $this->normalizedItemDiscountSelections(
                $data['equipment_discount_types'] ?? [],
                $data['equipment_discount_values'] ?? [],
            );
            $bookingData = $data;
            unset($bookingData['add_on_ids'], $bookingData['equipment_ids'], $bookingData['add_on_discount_types'], $bookingData['add_on_discount_values'], $bookingData['equipment_discount_types'], $bookingData['equipment_discount_values']);
            $bookingData['booking_kind'] = $bookingData['booking_kind'] ?? 'customer';
            $bookingData['customer_id'] = $this->resolveCustomer($bookingData)->id;
            $bookingData['package_price'] = $this->resolvePackagePrice($bookingData, $package, true);
            [$discountId, $discountAmount] = $this->resolveDiscountSelection($bookingData, $package, $bookingData['package_price']);
            $bookingData['discount_id'] = $discountId;
            $bookingData['discount_amount'] = $discountAmount;

            DB::transaction(function () use ($booking, $bookingData, $addOnIds, $equipmentIds, $addOnDiscountSelections, $equipmentDiscountSelections): void {
                $booking->update($bookingData);
                $booking->addOns()->sync($this->bookingItemSyncPayload($addOnIds, $addOnDiscountSelections));
                $booking->equipment()->sync($this->bookingItemSyncPayload($equipmentIds, $equipmentDiscountSelections));
                $this->syncPackageActionTasks($booking);
            });
        }

        $booking->refresh();
        $booking->load(['package', 'addOns']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking updated successfully.',
                'record' => $this->serializeBooking($booking),
            ]);
        }

        return redirect()->route('admin.bookings.show', $booking)->with('status', 'Booking updated successfully.');
    }

    public function storeDocument(CurrentTenant $currentTenant, Request $request, Booking $booking): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant && $booking->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys($this->bookingDocumentTypeLabels()))],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $document = BookingDocument::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => Auth::id(),
            'document_type' => $data['document_type'],
            'title' => trim((string) $data['title']),
            'file_path' => $file->store('booking-documents', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);

        $document->load('uploader');

        return response()->json([
            'message' => 'Document added.',
            'record' => $this->serializeBookingDocument($document),
        ]);
    }

    public function destroyDocument(CurrentTenant $currentTenant, Booking $booking, BookingDocument $document): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless(
            $tenant
            && $booking->tenant_id === $tenant->id
            && $document->tenant_id === $tenant->id
            && $document->booking_id === $booking->id,
            404
        );

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Document deleted.',
        ]);
    }

    public function grantClientAccess(CurrentTenant $currentTenant, Booking $booking, ClientPortalService $clientPortalService): JsonResponse|RedirectResponse
    {
        abort_unless($currentTenant->id() === $booking->tenant_id, 404);

        $booking->loadMissing(['package', 'addOns', 'equipment', 'discount', 'invoice.installments', 'tasks.assignedUser', 'tasks.assigneeVendor', 'tasks.assigneeCustomer', 'tasks.status']);
        $clientPortalService->grantForBooking($booking, Auth::user());
        $booking->refresh();

        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Client portal access email sent.',
                'record' => $this->serializeBooking($booking),
            ]);
        }

        return redirect()->route('admin.bookings.show', $booking)->with('status', 'Client portal access email sent.');
    }

    private function renderAdminBookingsPage(CurrentTenant $currentTenant, ?Booking $selectedBooking = null, ?Request $request = null): View|JsonResponse
    {
        $request ??= request();
        $tenant = $currentTenant->get();
        $bookings = $this->paginatedBookings($request);

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $bookings->getCollection()->map(fn (Booking $booking) => $this->serializeBookingListRecord($booking))->values()->all(),
                'pagination' => $this->paginationMeta($bookings),
            ]);
        }

        $serializedBookings = $bookings->getCollection()
            ->map(fn (Booking $booking) => $this->serializeBookingListRecord($booking));

        return view('admin.app', [
            'page' => 'bookings',
            'props' => [
                'tenant' => [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'theme' => $tenant?->theme ?: 'dark',
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
                    'leads' => route('leads.index'),
                    'customers' => route('customers.index'),
                    'campaigns' => route('campaigns.index'),
                    'tasks' => route('tasks.index'),
                    'users' => route('users.index'),
                    'roles' => route('roles.index'),
                    'access' => route('access.index'),
                    'bookings' => route('admin.bookings.index'),
                    'create' => route('admin.bookings.create'),
                    'quotes' => route('admin.quotes.index'),
                    'invoices' => route('admin.invoices.index'),
                    'expenses' => route('expenses.index'),
                    'expenseStore' => route('expenses.store'),
                    'settings' => route('settings.index'),
                    'logout' => route('logout'),
                ],
                'bookingStatuses' => $this->statuses(),
                'bookingStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_BOOKING) : [],
                'quoteResponseStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_QUOTE_RESPONSE) : [],
                'bookingKinds' => $this->bookingKinds(),
                'bookings' => $serializedBookings->values()->all(),
                'pagination' => $this->paginationMeta($bookings),
            ],
        ]);
    }

    private function renderAdminBookingDetailPage(CurrentTenant $currentTenant, Booking $booking): View
    {
        $tenant = $currentTenant->get();
        $booking->loadMissing(['package.addOns', 'customer', 'addOns', 'equipment', 'discount', 'invoice.installments', 'tasks.assignedUser', 'tasks.assigneeVendor', 'tasks.assigneeCustomer', 'tasks.status', 'tasks.clientPortalUpdates', 'clientPortalTaskUpdates.task', 'documents.uploader']);
        $taskStatuses = $tenant ? $this->taskStatuses($tenant) : collect();

        return view('admin.app', [
            'page' => 'bookings-detail',
            'props' => [
                'tenant' => [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'theme' => $tenant?->theme ?: 'dark',
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
                    'leads' => route('leads.index'),
                    'customers' => route('customers.index'),
                    'campaigns' => route('campaigns.index'),
                    'tasks' => route('tasks.index'),
                    'users' => route('users.index'),
                    'roles' => route('roles.index'),
                    'access' => route('access.index'),
                    'bookings' => route('admin.bookings.index'),
                    'create' => route('admin.bookings.create'),
                    'quotes' => route('admin.quotes.index'),
                    'invoices' => route('admin.invoices.index'),
                    'expenses' => route('expenses.index'),
                    'expenseStore' => route('expenses.store'),
                    'documentStore' => route('admin.bookings.documents.store', $booking),
                    'settings' => route('settings.index'),
                    'logout' => route('logout'),
                ],
                'bookingStatuses' => $this->statuses(),
                'bookingStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_BOOKING) : [],
                'quoteResponseStatusOptions' => $tenant ? $this->workspaceStatusOptions($tenant, TenantStatuses::SCOPE_QUOTE_RESPONSE) : [],
                'bookingKinds' => $this->bookingKinds(),
                'eventTypes' => $this->eventTypes(),
                'defaultDepositPercentage' => (float) ($tenant?->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)),
                'booking' => $this->serializeBooking($booking),
                'taskAssignees' => $tenant ? TaskAssignees::optionsForTenant($tenant, $booking)->values()->all() : [],
                'taskStatuses' => $taskStatuses->values()->all(),
                'vendorOptions' => $tenant
                    ? TenantVendor::query()
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
                        ->values()
                        ->all()
                    : [],
                'userOptions' => $tenant
                    ? $tenant->users()
                        ->orderBy('name')
                        ->get()
                        ->map(fn (User $user) => [
                            'id' => $user->id,
                            'label' => $user->name,
                        ])
                        ->values()
                        ->all()
                    : [],
                'expenseCategoryOptions' => $tenant
                    ? ExpenseCategory::query()
                        ->where('tenant_id', $tenant->id)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (ExpenseCategory $category) => [
                            'id' => $category->id,
                            'label' => $category->name,
                        ])
                        ->values()
                        ->all()
                    : [],
                'taskRoutes' => [
                    'store' => route('tasks.store'),
                ],
                ...$this->adminBookingCreateProps(
                    $tenant,
                    Package::query()->where('is_active', true)->with('hourlyPrices')->latest()->get(),
                    Equipment::query()->latest()->get(),
                    InventoryItem::query()->where('category', 'add-on')->latest()->get(),
                ),
            ],
        ]);
    }

    private function renderAdminBookingCreatePage(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $packages = Package::query()
            ->where('is_active', true)
            ->with('hourlyPrices')
            ->latest()
            ->get();
        $equipment = Equipment::query()->latest()->get();
        $addOns = InventoryItem::query()
            ->where('category', 'add-on')
            ->latest()
            ->get();

        return view('admin.app', [
            'page' => 'bookings-create',
            'props' => [
                'tenant' => [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'theme' => $tenant?->theme ?: 'dark',
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
                    'leads' => route('leads.index'),
                    'customers' => route('customers.index'),
                    'campaigns' => route('campaigns.index'),
                    'tasks' => route('tasks.index'),
                    'users' => route('users.index'),
                    'roles' => route('roles.index'),
                    'access' => route('access.index'),
                    'bookings' => route('admin.bookings.index'),
                    'create' => route('admin.bookings.create'),
                    'quotes' => route('admin.quotes.index'),
                    'invoices' => route('admin.invoices.index'),
                    'settings' => route('settings.index'),
                    'logout' => route('logout'),
                ],
                'bookingKinds' => $this->bookingKinds(),
                'eventTypes' => $this->eventTypes(),
                ...$this->adminBookingCreateProps($tenant, $packages, $equipment, $addOns),
            ],
        ]);
    }

    private function adminBookingCreateProps($tenant, $packages, $equipment, $addOns): array
    {
        return [
            'bookingCreateUrl' => route('bookings.store'),
            'defaultDepositPercentage' => (float) ($tenant?->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)),
            'travelFreeKilometers' => (float) ($tenant?->travel_free_kilometers ?? config('pricing.travel_free_kilometers', 0)),
            'travelFeePerKilometer' => (float) ($tenant?->travel_fee_per_kilometer ?? config('pricing.travel_fee_per_kilometer', 0)),
            'packages' => $packages->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'base_price' => number_format((float) $package->base_price, 2, '.', ''),
                'display_price' => number_format((float) (($package->hourlyPrices()->min('price')) ?? $package->base_price), 2, '.', ''),
                'hourly_prices' => $package->hourlyPrices->map(fn (PackageHourlyPrice $hourlyPrice) => [
                    'id' => $hourlyPrice->id,
                    'hours' => number_format((float) $hourlyPrice->hours, 2, '.', ''),
                    'price' => number_format((float) $hourlyPrice->price, 2, '.', ''),
                ])->values()->all(),
            ])->values()->all(),
            'equipmentOptions' => $equipment->map(fn (Equipment $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'daily_rate' => number_format((float) $item->daily_rate, 2, '.', ''),
            ])->values()->all(),
            'addOnOptions' => $addOns->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'product_code' => $item->sku,
                'type' => $item->type,
                'addon_category' => $item->addon_category,
                'duration' => $item->duration,
                'price' => number_format($item->discountedUnitPrice(), 2, '.', ''),
                'original_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'discount_percentage' => number_format((float) ($item->discount_percentage ?? 0), 2, '.', ''),
            ])->values()->all(),
            'discountOptions' => $this->serializeDiscountOptions($this->availableDiscounts()),
        ];
    }

    private function paginatedBookings(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $bookingKind = (string) $request->query('booking_kind', 'all');
        $eventDateFrom = trim((string) $request->query('event_date_from', ''));
        $eventDateTo = trim((string) $request->query('event_date_to', ''));

        return Booking::query()
            ->with(['package', 'addOns', 'equipment', 'discount', 'bookingStatus'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('entry_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('quote_number', 'like', "%{$search}%")
                        ->orWhereHas('package', fn ($packageQuery) => $packageQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(in_array($bookingKind, $this->bookingKinds(), true), fn ($query) => $query->where('booking_kind', $bookingKind))
            ->when($eventDateFrom !== '', fn ($query) => $query->whereDate('event_date', '>=', $eventDateFrom))
            ->when($eventDateTo !== '', fn ($query) => $query->whereDate('event_date', '<=', $eventDateTo))
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

    private function renderAdminCalendarPage(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $bookings = Booking::query()
            ->with(['bookingStatus'])
            ->latest('event_date')
            ->latest()
            ->get();

        return view('admin.app', [
            'page' => 'calendar',
            'props' => [
                'tenant' => [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'theme' => $tenant?->theme ?: 'dark',
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
                    'logout' => route('logout'),
                ],
                'bookings' => $bookings->map(fn (Booking $booking) => $this->serializeCalendarBooking($booking))->values()->all(),
            ],
        ]);
    }

    private function renderAdminQuotesPage(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $quotes = Booking::query()
            ->with(['package', 'bookingStatus', 'quoteResponseStatus'])
            ->latest()
            ->get();

        return view('admin.app', [
            'page' => 'quotes',
            'props' => [
                'tenant' => [
                    'id' => $tenant?->id,
                    'name' => $tenant?->name,
                    'slug' => $tenant?->slug,
                    'theme' => $tenant?->theme ?: 'dark',
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
                    'logout' => route('logout'),
                ],
                'quoteResponseStatuses' => ['all', ...TenantStatuses::names($tenant, TenantStatuses::SCOPE_QUOTE_RESPONSE)],
                'quotes' => $quotes->map(fn (Booking $booking) => $this->serializeQuoteListBooking($booking))->values()->all(),
            ],
        ]);
    }

    private function serializeQuoteListBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'quote_number' => $booking->quote_number,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'package_name' => $booking->package?->name,
            'event_date_label' => DateFormatter::date($booking->event_date),
            'status' => $booking->status,
            'status_label' => $booking->bookingStatus?->label() ?? str($booking->status)->replace('_', ' ')->title()->toString(),
            'customer_response_status_id' => $booking->quote_response_status_id,
            'customer_response_status' => $booking->customer_response_status,
            'customer_response_label' => $booking->quoteResponseStatus?->label() ?? str($booking->customer_response_status)->replace('_', ' ')->title()->toString(),
            'customer_responded_at_label' => DateFormatter::dateTime($booking->customer_responded_at),
            'show_url' => route('admin.bookings.show', $booking),
        ];
    }

    private function serializeBookingListRecord(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'quote_number' => $booking->quote_number,
            'booking_kind' => $booking->booking_kind ?? 'customer',
            'booking_kind_label' => $this->bookingKindLabel($booking->booking_kind ?? 'customer'),
            'display_name' => $booking->entry_name ?: $booking->customer_name,
            'customer_name' => $booking->customer_name,
            'event_date_label' => DateFormatter::date($booking->event_date),
            'start_time_label' => $this->timeLabel($booking->start_time),
            'package_name' => $booking->package?->name,
            'booking_total' => number_format(app(BookingPricing::class)->totalForBooking($booking), 2, '.', ''),
            'status' => $booking->status,
            'status_label' => $booking->bookingStatus?->label() ?? str($booking->status)->replace('_', ' ')->title()->toString(),
            'show_url' => route('admin.bookings.show', $booking),
        ];
    }

    private function serializeCalendarBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_kind' => $booking->booking_kind ?? 'customer',
            'booking_kind_label' => $this->bookingKindLabel($booking->booking_kind ?? 'customer'),
            'display_name' => $booking->entry_name ?: $booking->customer_name,
            'customer_name' => $booking->customer_name,
            'event_date' => DateFormatter::inputDate($booking->event_date),
            'start_time' => $this->timeValue($booking->start_time),
            'end_time' => $this->timeValue($booking->end_time),
            'status' => $booking->status,
            'status_label' => $booking->bookingStatus?->label() ?? str($booking->status)->replace('_', ' ')->title()->toString(),
            'show_url' => route('admin.bookings.show', $booking),
        ];
    }

    private function serializeBooking(Booking $booking): array
    {
        $booking->loadMissing(['discount', 'package.hourlyPrices', 'customer', 'addOns', 'tasks.assignedUser', 'tasks.assigneeVendor', 'tasks.assigneeCustomer', 'tasks.status', 'tasks.clientPortalUpdates', 'expenses.vendor', 'expenses.expenseCategory', 'expenses.user', 'clientPortalTaskUpdates.task', 'documents.uploader']);
        $clientPortalAccess = ClientPortalAccess::query()
            ->where('tenant_id', $booking->tenant_id)
            ->where('customer_email', strtolower((string) $booking->customer_email))
            ->first();
        $package = $booking->package;
        $bookingTotal = app(BookingPricing::class)->totalForBooking($booking);
        $packagePrice = $booking->package_price !== null
            ? (float) $booking->package_price
            : $this->resolvePackagePrice([
                'package_id' => $booking->package_id,
                'total_hours' => $booking->total_hours,
            ]);
        $packageHourlyPriceId = $package?->hourlyPrices
            ?->first(fn (PackageHourlyPrice $hourlyPrice) => abs((float) $hourlyPrice->hours - (float) $booking->total_hours) < 0.001)
            ?->id;

        return [
            'id' => $booking->id,
            'quote_number' => $booking->quote_number,
            'booking_kind' => $booking->booking_kind ?? 'customer',
            'booking_kind_label' => $this->bookingKindLabel($booking->booking_kind ?? 'customer'),
            'display_name' => $booking->entry_name ?: $booking->customer_name,
            'entry_name' => $booking->entry_name,
            'entry_description' => $booking->entry_description,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'client_portal_access_granted' => $clientPortalAccess !== null,
            'client_portal_access_granted_at_label' => DateFormatter::dateTime($clientPortalAccess?->granted_at),
            'client_portal_access_url' => $clientPortalAccess?->invite_token
                ? route('client.portal.login', ['access' => $clientPortalAccess->invite_token])
                : null,
            'package_id' => $booking->package_id,
            'package_hourly_price_id' => $packageHourlyPriceId ? (string) $packageHourlyPriceId : '',
            'discount_id' => $booking->discount_id ? (string) $booking->discount_id : '',
            'package_price' => number_format($packagePrice, 2, '.', ''),
            'discount_amount' => number_format((float) ($booking->discount_amount ?? 0), 2, '.', ''),
            'event_type' => $booking->event_type,
            'event_type_label' => $booking->event_type ? str($booking->event_type)->title()->toString() : null,
            'venue' => $booking->venue,
            'event_date' => DateFormatter::inputDate($booking->event_date),
            'event_date_label' => DateFormatter::date($booking->event_date),
            'start_time' => $this->timeValue($booking->start_time),
            'start_time_label' => $this->timeLabel($booking->start_time),
            'end_time' => $this->timeValue($booking->end_time),
            'end_time_label' => $this->timeLabel($booking->end_time),
            'total_hours' => number_format((float) ($booking->total_hours ?? 0), 2, '.', ''),
            'event_location' => $booking->event_location,
            'travel_distance_km' => number_format((float) ($booking->travel_distance_km ?? 0), 2, '.', ''),
            'travel_fee' => number_format((float) ($booking->travel_fee ?? 0), 2, '.', ''),
            'discount' => $booking->discount ? [
                'id' => $booking->discount->id,
                'code' => $booking->discount->code,
                'name' => $booking->discount->name,
                'type' => $booking->discount->discount_type,
                'type_label' => $booking->discount->discount_type === 'percentage' ? 'Percentage' : 'Specific Amount',
                'value' => number_format((float) $booking->discount->discount_value, 2, '.', ''),
                'starts_at_label' => DateFormatter::date($booking->discount->starts_at),
                'ends_at_label' => DateFormatter::date($booking->discount->ends_at),
            ] : null,
            'notes' => $booking->notes,
            'status_id' => $booking->booking_status_id,
            'status' => $booking->status,
            'status_label' => $booking->bookingStatus?->label() ?? str($booking->status)->replace('_', ' ')->title()->toString(),
            'customer_response_status_id' => $booking->quote_response_status_id,
            'customer_response_status' => $booking->customer_response_status,
            'customer_response_label' => $booking->quoteResponseStatus?->label() ?? str($booking->customer_response_status)->replace('_', ' ')->title()->toString(),
            'customer_responded_at_label' => DateFormatter::dateTime($booking->customer_responded_at),
            'package_name' => $booking->package?->name,
            'booking_total' => number_format($bookingTotal, 2, '.', ''),
            'package' => $package ? [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => number_format($packagePrice, 2, '.', ''),
                'photo_url' => $package->photo_path ? Storage::disk('public')->url($package->photo_path) : null,
            ] : null,
            'addons' => $booking->addOns->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'product_code' => $item->sku,
                'name' => $item->name,
                'type' => $item->type,
                'addon_category' => $item->addon_category,
                'description' => $item->description,
                'price' => number_format($item->discountedUnitPriceForBookingSelection(
                    $item->pivot?->discount_type,
                    $item->pivot?->discount_value,
                    (float) ($item->pivot?->discount_percentage ?? 0),
                ), 2, '.', ''),
                'original_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'discount_percentage' => number_format((float) ($item->discount_percentage ?? 0), 2, '.', ''),
                'booking_discount_percentage' => number_format((float) ($item->pivot?->discount_percentage ?? 0), 2, '.', ''),
                'booking_discount_type' => $item->pivot?->discount_type ?? 'percentage',
                'booking_discount_value' => number_format((float) ($item->pivot?->discount_value ?? $item->pivot?->discount_percentage ?? 0), 2, '.', ''),
                'duration' => $item->duration,
                'photo_url' => $item->photo_path ? Storage::disk('public')->url($item->photo_path) : null,
            ])->values()->all(),
            'equipment' => $booking->equipment->map(fn (Equipment $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => number_format($item->discountedDailyRateForBooking(
                    $item->pivot?->discount_type,
                    $item->pivot?->discount_value,
                    (float) ($item->pivot?->discount_percentage ?? 0),
                ), 2, '.', ''),
                'original_price' => number_format((float) $item->daily_rate, 2, '.', ''),
                'booking_discount_percentage' => number_format((float) ($item->pivot?->discount_percentage ?? 0), 2, '.', ''),
                'booking_discount_type' => $item->pivot?->discount_type ?? 'percentage',
                'booking_discount_value' => number_format((float) ($item->pivot?->discount_value ?? $item->pivot?->discount_percentage ?? 0), 2, '.', ''),
                'photo_url' => $item->photo_path ? Storage::disk('public')->url($item->photo_path) : null,
            ])->values()->all(),
            'add_on_ids' => $booking->addOns->pluck('id')->values()->all(),
            'equipment_ids' => $booking->equipment->pluck('id')->values()->all(),
            'add_on_discount_types' => $booking->addOns->mapWithKeys(fn (InventoryItem $item) => [
                (string) $item->id => $item->pivot?->discount_type ?? 'percentage',
            ])->all(),
            'add_on_discount_values' => $booking->addOns->mapWithKeys(fn (InventoryItem $item) => [
                (string) $item->id => number_format((float) ($item->pivot?->discount_value ?? $item->pivot?->discount_percentage ?? 0), 2, '.', ''),
            ])->all(),
            'equipment_discount_types' => $booking->equipment->mapWithKeys(fn (Equipment $item) => [
                (string) $item->id => $item->pivot?->discount_type ?? 'percentage',
            ])->all(),
            'equipment_discount_values' => $booking->equipment->mapWithKeys(fn (Equipment $item) => [
                (string) $item->id => number_format((float) ($item->pivot?->discount_value ?? $item->pivot?->discount_percentage ?? 0), 2, '.', ''),
            ])->all(),
            'tasks' => $booking->tasks
                ->sortByDesc(fn (Task $task) => $task->created_at)
                ->values()
                ->map(fn (Task $task) => $this->serializeTaskRecord($task))
                ->all(),
            'expenses' => $booking->expenses
                ->sortByDesc(fn ($expense) => $expense->expense_date ?? $expense->created_at)
                ->values()
                ->map(fn ($expense) => [
                    'id' => $expense->id,
                    'expense_name' => $expense->expense_name,
                    'expense_date_label' => DateFormatter::date($expense->expense_date, 'Not set'),
                    'amount' => number_format((float) $expense->amount, 2, '.', ''),
                    'expense_category_label' => $expense->expenseCategory?->name ?: 'Not set',
                    'booking_label' => $booking->quote_number
                        ? sprintf('%s - %s', $booking->quote_number, $booking->entry_name ?: $booking->customer_name)
                        : ($booking->entry_name ?: $booking->customer_name),
                    'vendor_label' => $expense->vendor?->company_name
                        ? sprintf('%s (%s)', $expense->vendor->name, $expense->vendor->company_name)
                        : ($expense->vendor?->name ?? 'Not linked'),
                    'user_label' => $expense->user?->name ?? 'Not linked',
                    'notes' => $expense->notes ?? '',
                    'receipt_url' => $expense->receipt_path ? Storage::disk('public')->url($expense->receipt_path) : null,
                    'receipt_name' => $expense->receipt_original_name ?: ($expense->receipt_path ? basename($expense->receipt_path) : ''),
                ])
                ->all(),
            'documents' => $this->serializeBookingDocuments($booking),
            'task_assignees' => $booking->tenant ? TaskAssignees::optionsForTenant($booking->tenant, $booking)->values()->all() : [],
            'invoice' => $booking->invoice ? $this->serializeInvoice($booking->invoice) : null,
            'show_url' => route('admin.bookings.show', $booking),
            'update_url' => route('admin.bookings.update', $booking),
            'invoice_create_url' => route('admin.bookings.invoice.store', $booking),
            'grant_client_access_url' => route('admin.bookings.client-access.grant', $booking),
        ];
    }

    private function serializeTaskRecord(Task $task): array
    {
        $latestPortalUpdate = $task->clientPortalUpdates->first();

        return [
            'id' => $task->id,
            'task_name' => $task->task_name,
            'task_duration_hours' => $task->task_duration_hours,
            'assigned_to' => $task->assignee_type && $task->assignee_id ? TaskAssignees::value($task->assignee_type, $task->assignee_id) : '',
            'assigned_to_name' => TaskAssignees::labelForTask($task),
            'assignee_type' => $task->assignee_type,
            'booking_id' => $task->booking_id,
            'inventory_item_id' => $task->inventory_item_id,
            'is_booking_action' => (bool) $task->is_booking_action,
            'task_status_id' => $task->task_status_id,
            'status_name' => $task->status?->name ?? '',
            'status_label' => $task->status?->label() ?? '',
            'due_date' => DateFormatter::inputDate($task->due_date) ?? '',
            'due_date_label' => DateFormatter::date($task->due_date, 'Not set'),
            'date_started' => DateFormatter::inputDate($task->date_started) ?? '',
            'date_started_label' => DateFormatter::date($task->date_started, 'Not set'),
            'date_completed' => DateFormatter::inputDate($task->date_completed) ?? '',
            'date_completed_label' => DateFormatter::date($task->date_completed, 'Not set'),
            'remarks' => $task->remarks ?? '',
            'customer_response_note' => $latestPortalUpdate?->note ?? '',
            'customer_response_at_label' => DateFormatter::dateTime($latestPortalUpdate?->created_at, 'No reply yet'),
            'customer_response_attachments' => collect($latestPortalUpdate?->attachments ?? [])
                ->map(fn ($attachment) => [
                    'name' => $attachment['name'] ?? 'Attachment',
                    'url' => $attachment['url'] ?? null,
                ])
                ->filter(fn (array $attachment) => filled($attachment['url']))
                ->values()
                ->all(),
            'customer_response_count' => $task->clientPortalUpdates->count(),
            'update_url' => route('tasks.update', $task),
            'delete_url' => route('tasks.destroy', $task),
        ];
    }

    private function taskStatuses($tenant)
    {
        return TenantStatuses::ensureTaskRecords($tenant)
            ->map(fn (TaskStatus $status) => [
                'id' => $status->id,
                'name' => $status->name,
                'label' => $status->label(),
                'sort_order' => (int) ($status->sort_order ?? 0),
            ]);
    }

    private function syncPackageActionTasks(Booking $booking): void
    {
        $booking->loadMissing(['package.addOns']);
        $defaultTaskStatusId = $this->taskStatuses($booking->tenant)
            ->firstWhere('name', 'new')['id'] ?? null;

        $actionItems = collect($booking->package?->addOns ?? [])
            ->filter(fn (InventoryItem $item) => strcasecmp((string) $item->type, 'Action') === 0)
            ->values();

        $desiredIds = $actionItems->pluck('id')->filter()->values()->all();
        $generatedTasks = $booking->tasks()
            ->where('is_booking_action', true)
            ->get()
            ->keyBy('inventory_item_id');

        if ($desiredIds === []) {
            $booking->tasks()
                ->where('is_booking_action', true)
                ->delete();

            return;
        }

        $booking->tasks()
            ->where('is_booking_action', true)
            ->whereNotIn('inventory_item_id', $desiredIds)
            ->delete();

        foreach ($actionItems as $item) {
            $task = $generatedTasks->get($item->id) ?? new Task([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->id,
                'inventory_item_id' => $item->id,
                'is_booking_action' => true,
            ]);

            $dueDate = null;
            if ($booking->event_date && $item->due_days_before_event !== null) {
                $dueDate = $booking->event_date->copy()->subDays((int) $item->due_days_before_event);
            }

            $task->fill([
                'task_name' => $item->name,
                'inventory_item_id' => $item->id,
                'is_booking_action' => true,
                'task_status_id' => $task->task_status_id ?: $defaultTaskStatusId,
                'due_date' => $dueDate,
            ]);
            $task->save();
        }
    }

    private function bookingKinds(): array
    {
        return ['customer', 'market_stall', 'sponsored'];
    }

    private function bookingKindLabel(string $kind): string
    {
        return match ($kind) {
            'market_stall' => 'Stall',
            'sponsored' => 'Sponsored',
            default => 'Customer Booking',
        };
    }

    private function statuses(): array
    {
        return TenantStatuses::names(app(CurrentTenant::class)->get(), TenantStatuses::SCOPE_BOOKING);
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

    private function workspaceStatusById(int $tenantId, string $scope, mixed $id)
    {
        $tenant = Tenant::query()->withoutGlobalScopes()->find($tenantId);

        return $tenant ? TenantStatuses::findWorkspaceStatusById($tenant, $scope, $id) : null;
    }

    private function eventTypes(): array
    {
        return ['Wedding', 'Birthday', 'Anniversary', 'Others'];
    }

    private function resolveLead(?string $leadToken): ?Lead
    {
        if (blank($leadToken)) {
            return null;
        }

        return Lead::query()
            ->where('token', $leadToken)
            ->whereNull('booking_id')
            ->first();
    }

    private function resolveCustomer(array $data): Customer
    {
        $customer = Customer::query()
            ->where('email', $data['customer_email'])
            ->first();

        if ($customer === null) {
            $customer = new Customer;
        }

        $customer->fill([
            'full_name' => $data['customer_name'],
            'email' => $data['customer_email'],
            'phone' => $data['customer_phone'],
        ]);
        $customer->save();

        return $customer;
    }

    private function resolvePackagePrice(array $data, ?Package $package = null, bool $applyCustomerDiscount = false): float
    {
        $package ??= $this->resolvePackageForSelection($data);

        if ($package === null) {
            return 0;
        }

        $selectedHourlyPriceId = isset($data['package_hourly_price_id']) ? (int) $data['package_hourly_price_id'] : null;

        if ($selectedHourlyPriceId) {
            $package->loadMissing('hourlyPrices');
            $selectedHourlyPrice = $package->hourlyPrices->firstWhere('id', $selectedHourlyPriceId);

            if ($selectedHourlyPrice !== null) {
                $amount = (float) $selectedHourlyPrice->price;

                return $applyCustomerDiscount
                    ? app(PackagePriceResolver::class)->applyDiscount($amount, $this->customerPackageDiscountPercentage())
                    : $amount;
            }
        }

        $amount = app(PackagePriceResolver::class)->forHours($package, $data['total_hours'] ?? null);

        return $applyCustomerDiscount
            ? app(PackagePriceResolver::class)->applyDiscount($amount, $this->customerPackageDiscountPercentage())
            : $amount;
    }

    private function resolvePackageForSelection(array $data): ?Package
    {
        $packageId = isset($data['package_id']) ? (int) $data['package_id'] : null;

        if (! $packageId) {
            return null;
        }

        return Package::query()->with('hourlyPrices')->find($packageId);
    }

    private function resolveEquipmentSelection(array $equipmentIds)
    {
        if ($equipmentIds === []) {
            return collect();
        }

        return Equipment::query()
            ->whereIn('id', $equipmentIds)
            ->get();
    }

    private function resolveDiscountSelection(array $data, ?Package $package, float $packagePrice): array
    {
        $discountId = isset($data['discount_id']) ? (int) $data['discount_id'] : null;
        $packageId = $package?->id ?? (isset($data['package_id']) ? (int) $data['package_id'] : null);

        if (! $discountId) {
            return [null, 0];
        }

        $discount = Discount::query()
            ->with(['packages:id'])
            ->find($discountId);

        if (! $discount instanceof Discount) {
            throw ValidationException::withMessages([
                'discount_id' => 'Please choose a valid discount.',
            ]);
        }

        $discountResolver = app(BookingDiscountResolver::class);

        if (! $discountResolver->isActive($discount)) {
            return [null, 0];
        }

        if (! $discountResolver->appliesToSelection($discount, $packageId)) {
            return [null, 0];
        }

        return [
            $discount->id,
            number_format($discountResolver->calculateAmount($discount, $packageId, $packagePrice), 2, '.', ''),
        ];
    }

    private function normalizedItemDiscountSelections(array $discountTypes, array $discountValues): array
    {
        return collect($discountValues)
            ->mapWithKeys(function ($discountValue, $id) use ($discountTypes): array {
                $normalizedType = ($discountTypes[$id] ?? 'percentage') === 'amount' ? 'amount' : 'percentage';
                $normalizedValue = max(0, (float) $discountValue);

                if ($normalizedType === 'percentage') {
                    $normalizedValue = min(100, $normalizedValue);
                }

                return [
                    (int) $id => [
                        'discount_type' => $normalizedType,
                        'discount_value' => round($normalizedValue, 2),
                        'discount_percentage' => $normalizedType === 'percentage' ? round($normalizedValue, 2) : 0,
                    ],
                ];
            })
            ->all();
    }

    private function bookingItemSyncPayload(array $ids, array $discountSelections): array
    {
        return collect($ids)
            ->mapWithKeys(fn ($id) => [
                (int) $id => ($discountSelections[(int) $id] ?? [
                    'discount_type' => 'percentage',
                    'discount_value' => 0,
                    'discount_percentage' => 0,
                ]),
            ])
            ->all();
    }

    private function bookingInvoiceAmountLocked(Booking $booking): bool
    {
        $booking->loadMissing('invoice');

        if (! $booking->invoice instanceof Invoice) {
            return false;
        }

        return in_array($booking->invoice->status, ['partially_paid', 'paid'], true)
            || (float) ($booking->invoice->amount_paid ?? 0) > 0;
    }

    private function bookingFinancialSelectionChanged(Booking $booking, array $data): bool
    {
        $booking->loadMissing(['package.hourlyPrices', 'addOns', 'equipment']);

        $currentPackageHourlyPriceId = $booking->package
            ? $this->matchPackageTimingByDuration($booking->package, $booking->total_hours)?->id
            : null;
        $requestedAddOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
        $requestedEquipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
        $currentAddOnIds = $booking->addOns->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $currentEquipmentIds = $booking->equipment->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $requestedAddOnDiscounts = $this->normalizedItemDiscountSelections(
            $data['add_on_discount_types'] ?? [],
            $data['add_on_discount_values'] ?? [],
        );
        $requestedEquipmentDiscounts = $this->normalizedItemDiscountSelections(
            $data['equipment_discount_types'] ?? [],
            $data['equipment_discount_values'] ?? [],
        );
        $currentAddOnDiscounts = $booking->addOns->mapWithKeys(fn (InventoryItem $item) => [
            (int) $item->id => [
                'discount_type' => $item->pivot?->discount_type ?? 'percentage',
                'discount_value' => round((float) ($item->pivot?->discount_value ?? $item->pivot?->discount_percentage ?? 0), 2),
                'discount_percentage' => round((float) ($item->pivot?->discount_percentage ?? 0), 2),
            ],
        ])->all();
        $currentEquipmentDiscounts = $booking->equipment->mapWithKeys(fn (Equipment $item) => [
            (int) $item->id => [
                'discount_type' => $item->pivot?->discount_type ?? 'percentage',
                'discount_value' => round((float) ($item->pivot?->discount_value ?? $item->pivot?->discount_percentage ?? 0), 2),
                'discount_percentage' => round((float) ($item->pivot?->discount_percentage ?? 0), 2),
            ],
        ])->all();

        ksort($requestedAddOnDiscounts);
        ksort($requestedEquipmentDiscounts);
        ksort($currentAddOnDiscounts);
        ksort($currentEquipmentDiscounts);

        return (int) ($data['package_id'] ?? 0) !== (int) ($booking->package_id ?? 0)
            || (int) ($data['package_hourly_price_id'] ?? 0) !== (int) ($currentPackageHourlyPriceId ?? 0)
            || number_format((float) ($data['total_hours'] ?? 0), 2, '.', '') !== number_format((float) ($booking->total_hours ?? 0), 2, '.', '')
            || (int) ($data['discount_id'] ?? 0) !== (int) ($booking->discount_id ?? 0)
            || $requestedAddOnIds !== $currentAddOnIds
            || $requestedEquipmentIds !== $currentEquipmentIds
            || $requestedAddOnDiscounts !== $currentAddOnDiscounts
            || $requestedEquipmentDiscounts !== $currentEquipmentDiscounts;
    }

    private function ensurePackageTimingSelection(?Package $package, array &$data): void
    {
        if ($package === null) {
            return;
        }

        $package->loadMissing('hourlyPrices');

        if ($package->hourlyPrices->isEmpty()) {
            return;
        }

        $selectedHourlyPriceId = isset($data['package_hourly_price_id']) ? (int) $data['package_hourly_price_id'] : null;

        if (! $selectedHourlyPriceId) {
            $matchedHourlyPrice = $this->matchPackageTimingByDuration($package, $data['total_hours'] ?? null);

            if ($matchedHourlyPrice instanceof PackageHourlyPrice) {
                $data['package_hourly_price_id'] = $matchedHourlyPrice->id;
                $selectedHourlyPriceId = $matchedHourlyPrice->id;
            }
        }

        if (! $selectedHourlyPriceId) {
            throw ValidationException::withMessages([
                'package_hourly_price_id' => 'Please choose a package timing and price option.',
            ]);
        }

        $selectedHourlyPrice = $package->hourlyPrices->firstWhere('id', $selectedHourlyPriceId);

        if (! $selectedHourlyPrice instanceof PackageHourlyPrice) {
            throw ValidationException::withMessages([
                'package_hourly_price_id' => 'Please choose a valid timing option for the selected package.',
            ]);
        }
    }

    private function matchPackageTimingByDuration(Package $package, mixed $totalHours): ?PackageHourlyPrice
    {
        $package->loadMissing('hourlyPrices');

        if ($package->hourlyPrices->isEmpty() || ! is_numeric($totalHours)) {
            return null;
        }

        $normalizedHours = number_format((float) $totalHours, 2, '.', '');

        return $package->hourlyPrices->first(
            fn (PackageHourlyPrice $hourlyPrice) => number_format((float) $hourlyPrice->hours, 2, '.', '') === $normalizedHours
        );
    }

    private function availableDiscounts()
    {
        $tenantId = app(CurrentTenant::class)->id();

        return Discount::query()
            ->where('tenant_id', $tenantId)
            ->with(['packages:id,name'])
            ->latest()
            ->get();
    }

    private function serializeDiscountOptions($discounts): array
    {
        return $discounts->map(fn (Discount $discount) => [
            'id' => $discount->id,
            'code' => $discount->code,
            'name' => $discount->name,
            'discount_type' => $discount->discount_type,
            'discount_type_label' => $discount->discount_type === 'percentage' ? 'Percentage' : 'Specific Amount',
            'discount_value' => number_format((float) $discount->discount_value, 2, '.', ''),
            'starts_at' => DateFormatter::inputDate($discount->starts_at),
            'ends_at' => DateFormatter::inputDate($discount->ends_at),
            'starts_at_label' => DateFormatter::date($discount->starts_at),
            'ends_at_label' => DateFormatter::date($discount->ends_at),
            'package_ids' => $discount->packages->pluck('id')->values()->all(),
        ])->values()->all();
    }

    private function customerPackageDiscountPercentage($tenant = null): float
    {
        $tenant ??= app(CurrentTenant::class)->get();

        return max(0, min(100, (float) ($tenant?->customer_package_discount_percentage ?? 0)));
    }

    private function markLeadAsBooked(?Lead $lead, Booking $booking): void
    {
        if ($lead === null) {
            return;
        }

        $lead->fill([
            'booking_id' => $booking->id,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'event_date' => $booking->event_date,
            'venue' => $booking->venue,
            'event_location' => $booking->event_location,
            'notes' => $booking->notes,
            'status' => 'booked',
            'last_activity_at' => now(),
        ]);
        $lead->save();
    }

    private function sendBookingEmails(Booking $booking, bool $sendCustomerEmail = true, bool $sendAdminEmail = true): void
    {
        $addonsPdf = app(BookingAddonsPdfGenerator::class)->makeForBooking($booking);
        $adminRecipients = $booking->tenant?->users
            ->filter(fn (User $user) => $user->pivot?->role === 'owner')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];
        $trackedEmailSender = app(TrackedEmailSender::class);

        if ($sendAdminEmail && $adminRecipients !== []) {
            $trackedEmailSender->send(
                new AdminBookingCreatedMail($booking, $addonsPdf),
                $adminRecipients,
                [],
                [
                    'tenant' => $booking->tenant,
                    'context' => $booking,
                    'attachments' => $addonsPdf ? [[
                        'name' => $addonsPdf->name,
                        'mime' => 'application/pdf',
                        'content' => $addonsPdf->content,
                    ]] : [],
                ],
            );
        }

        if ($sendCustomerEmail) {
            $trackedEmailSender->send(
                new CustomerBookingCreatedMail($booking, $addonsPdf),
                [[
                    'email' => $booking->customer_email,
                    'name' => $booking->customer_name,
                ]],
                [],
                [
                    'tenant' => $booking->tenant,
                    'context' => $booking,
                    'attachments' => $addonsPdf ? [[
                        'name' => $addonsPdf->name,
                        'mime' => 'application/pdf',
                        'content' => $addonsPdf->content,
                    ]] : [],
                ],
            );
        }
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
            'status' => $invoice->status,
            'issued_at' => DateFormatter::inputDate($invoice->issued_at),
            'issued_at_label' => DateFormatter::date($invoice->issued_at),
            'amounts_are' => $invoice->amounts_are ?: 'tax_exclusive',
            'amounts_are_label' => $this->amountsAreLabel($invoice->amounts_are ?: 'tax_exclusive'),
            'line_description' => $invoice->line_description ?: trim(($invoice->booking?->customer_name ?: 'Customer').' booking package'),
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
                'status' => $installment->status,
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

    private function serializeBookingDocuments(Booking $booking): array
    {
        $documents = collect();

        $quoteAttachment = app(BookingAddonsPdfGenerator::class)->makeForBooking($booking);
        if ($quoteAttachment !== null) {
            $documents->push([
                'id' => 'quote-'.$booking->id,
                'title' => $booking->quote_number ?: 'Quote',
                'document_type' => 'quote',
                'document_type_label' => $this->bookingDocumentTypeLabels()['quote'],
                'source_label' => 'Generated quote',
                'uploaded_by_label' => 'System',
                'notes' => 'Quote PDF attached to the booking email.',
                'created_at_label' => DateFormatter::dateTime($booking->created_at),
                'created_at_sort' => optional($booking->created_at)->toIso8601String(),
                'file_name' => $quoteAttachment->name,
                'file_size_label' => null,
                'url' => route('admin.bookings.quote-pdf', $booking),
                'delete_url' => null,
                'can_delete' => false,
            ]);
        }

        if ($booking->invoice) {
            $documents->push([
                'id' => 'invoice-'.$booking->invoice->id,
                'title' => $booking->invoice->invoice_number ?: 'Invoice',
                'document_type' => 'invoice',
                'document_type_label' => $this->bookingDocumentTypeLabels()['invoice'],
                'source_label' => 'Generated invoice',
                'uploaded_by_label' => 'System',
                'notes' => 'Customer invoice page for this booking.',
                'created_at_label' => DateFormatter::dateTime($booking->invoice->issued_at ?: $booking->invoice->created_at),
                'created_at_sort' => optional($booking->invoice->issued_at ?: $booking->invoice->created_at)->toIso8601String(),
                'file_name' => ($booking->invoice->invoice_number ?: 'invoice').'.pdf',
                'file_size_label' => null,
                'url' => route('admin.bookings.invoice.pdf', $booking),
                'delete_url' => null,
                'can_delete' => false,
            ]);
        }

        foreach ($booking->expenses as $expense) {
            if (! $expense->receipt_path) {
                continue;
            }

            $documents->push([
                'id' => 'expense-receipt-'.$expense->id,
                'title' => ($expense->expense_name ?: 'Expense').' receipt',
                'document_type' => 'receipt',
                'document_type_label' => $this->bookingDocumentTypeLabels()['receipt'],
                'source_label' => 'Expense receipt',
                'uploaded_by_label' => $expense->user?->name ?: 'User',
                'notes' => $expense->notes ?: 'Receipt attached to a booking expense.',
                'created_at_label' => DateFormatter::dateTime($expense->created_at),
                'created_at_sort' => optional($expense->created_at)->toIso8601String(),
                'file_name' => $expense->receipt_original_name ?: basename($expense->receipt_path),
                'file_size_label' => null,
                'url' => Storage::disk('public')->url($expense->receipt_path),
                'delete_url' => null,
                'can_delete' => false,
            ]);
        }

        foreach ($booking->clientPortalTaskUpdates as $update) {
            foreach ((array) ($update->attachments ?? []) as $index => $attachment) {
                if (! filled($attachment['url'] ?? null)) {
                    continue;
                }

                $documents->push([
                    'id' => 'client-update-'.$update->id.'-'.$index,
                    'title' => ($update->task?->task_name ?: 'Client file').' attachment',
                    'document_type' => 'client_file',
                    'document_type_label' => $this->bookingDocumentTypeLabels()['client_file'],
                    'source_label' => 'Client portal upload',
                    'uploaded_by_label' => $update->customer_email ?: 'Client',
                    'notes' => $update->note ?: 'File uploaded from the client portal.',
                    'created_at_label' => DateFormatter::dateTime($update->created_at),
                    'created_at_sort' => optional($update->created_at)->toIso8601String(),
                    'file_name' => $attachment['name'] ?? 'Attachment',
                    'file_size_label' => null,
                    'url' => $attachment['url'],
                    'delete_url' => null,
                    'can_delete' => false,
                ]);
            }
        }

        foreach ($booking->documents as $document) {
            $documents->push($this->serializeBookingDocument($document));
        }

        return $documents
            ->sortByDesc(fn (array $document) => strtotime((string) ($document['created_at_sort'] ?? '')) ?: 0)
            ->values()
            ->map(function (array $document): array {
                unset($document['created_at_sort']);

                return $document;
            })
            ->all();
    }

    private function serializeBookingDocument(BookingDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'document_type' => $document->document_type,
            'document_type_label' => $this->bookingDocumentTypeLabels()[$document->document_type] ?? str($document->document_type)->replace('_', ' ')->title()->toString(),
            'source_label' => 'Booking upload',
            'uploaded_by_label' => $document->uploader?->name ?: 'User',
            'notes' => $document->notes ?: '',
            'created_at_label' => DateFormatter::dateTime($document->created_at),
            'created_at_sort' => optional($document->created_at)->toIso8601String(),
            'file_name' => $document->original_name,
            'file_size_label' => $this->humanFileSize($document->file_size),
            'url' => Storage::disk('public')->url($document->file_path),
            'delete_url' => route('admin.bookings.documents.destroy', [$document->booking_id, $document]),
            'can_delete' => true,
        ];
    }

    private function bookingDocumentTypeLabels(): array
    {
        return [
            'quote' => 'Quote',
            'invoice' => 'Invoice',
            'receipt' => 'Receipt',
            'client_file' => 'Client File',
            'user_file' => 'User File',
            'other' => 'Other',
        ];
    }

    private function humanFileSize(?int $bytes): ?string
    {
        if (! $bytes || $bytes < 1) {
            return null;
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function timeValue(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function timeLabel(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return DateFormatter::time($time);
    }
}
