<?php

namespace App\Http\Controllers;

use App\Mail\AdminBookingCreatedMail;
use App\Mail\CustomerBookingCreatedMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InventoryItem;
use App\Models\Lead;
use App\Models\Package;
use App\Models\PackageHourlyPrice;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Support\BookingAddonsPdfGenerator;
use App\Support\BookingDiscountResolver;
use App\Support\BookingPricing;
use App\Support\InvoiceBuilder;
use App\Support\PackagePriceResolver;
use App\Support\TenantStatuses;
use App\Support\StripeCheckoutLinkGenerator;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
                ->pluck('addon_category')
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

        $booking->update([
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
            'event_location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $lead = $this->resolveLead($data['lead_token'] ?? null);
        $hasMeaningfulInput = collect([
            $data['customer_name'] ?? null,
            $data['customer_email'] ?? null,
            $data['customer_phone'] ?? null,
            $data['event_date'] ?? null,
            $data['event_location'] ?? null,
            $data['notes'] ?? null,
        ])->contains(fn ($value) => filled($value));

        if (! $hasMeaningfulInput && $lead === null) {
            return response()->json([
                'saved' => false,
                'lead_token' => null,
            ]);
        }

        $lead ??= new Lead();
        $lead->fill([
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'event_date' => $data['event_date'] ?? null,
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
            'equipment_ids' => ['nullable', 'array'],
            'equipment_ids.*' => [
                'integer',
                Rule::exists('equipment', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
        ], [
            'start_time.regex' => 'Start hour must be on the hour or half hour.',
            'end_time.regex' => 'End hour must be on the hour or half hour.',
        ]);

        $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
        $package = $this->resolvePackageForSelection($data);
        $this->ensurePackageTimingSelection($package, $data);
        $selectedEquipment = $this->resolveEquipmentSelection($equipmentIds);
        $lead = $this->resolveLead($data['lead_token'] ?? null);
        unset($data['lead_token']);
        unset($data['add_on_ids']);
        unset($data['equipment_ids']);
        $data['booking_kind'] = $data['booking_kind'] ?? 'customer';
        $data['customer_id'] = $this->resolveCustomer($data)->id;
        $data['package_price'] = $this->resolvePackagePrice($data, $package, ! $request->expectsJson());
        $data['discount_id'] = null;
        $data['discount_amount'] = 0;

        $booking = Booking::query()->create($data);
        $booking->addOns()->sync($addOnIds);
        $booking->equipment()->sync($equipmentIds);
        $booking->load(['package', 'tenant.users', 'addOns', 'equipment']);
        $this->markLeadAsBooked($lead, $booking);
        $this->sendBookingEmails($booking);

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
    ): RedirectResponse {
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
            'equipment_ids' => ['nullable', 'array'],
            'equipment_ids.*' => [
                'integer',
                Rule::exists('equipment', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)),
            ],
        ], [
            'terms_accepted.accepted' => 'Please accept the terms and conditions before continuing to payment.',
            'start_time.regex' => 'Start hour must be on the hour or half hour.',
            'end_time.regex' => 'End hour must be on the hour or half hour.',
        ]);

        $package = $this->resolvePackageForSelection($data);
        $this->ensurePackageTimingSelection($package, $data);

        [$booking, $checkoutUrl] = DB::transaction(function () use ($data, $invoiceBuilder, $stripeCheckoutLinkGenerator, $package) {
            $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $selectedEquipment = $this->resolveEquipmentSelection($equipmentIds);
            $lead = $this->resolveLead($data['lead_token'] ?? null);
            $bookingData = $data;
            unset($bookingData['lead_token'], $bookingData['terms_accepted'], $bookingData['add_on_ids'], $bookingData['equipment_ids']);
            $bookingData['booking_kind'] = $bookingData['booking_kind'] ?? 'customer';
            $bookingData['customer_response_status'] = 'accepted';
            $bookingData['customer_responded_at'] = now();
            $bookingData['customer_id'] = $this->resolveCustomer($bookingData)->id;
            $bookingData['package_price'] = $this->resolvePackagePrice($bookingData, $package, true);
            [$discountId, $discountAmount] = $this->resolveDiscountSelection($bookingData, $package, $bookingData['package_price']);
            $bookingData['discount_id'] = $discountId;
            $bookingData['discount_amount'] = $discountAmount;

            $booking = Booking::query()->create($bookingData);
            $booking->addOns()->sync($addOnIds);
            $booking->equipment()->sync($equipmentIds);
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
                $stripeCheckoutLinkGenerator->forInstallment($invoice, $depositInstallment),
            ];
        });

        $this->sendBookingEmails($booking);

        return redirect()->away($checkoutUrl);
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
                'status' => ['required', Rule::in($this->statuses())],
                'notes' => ['nullable', 'string'],
            ]);

            $booking->update($data);
        } else {
            $data = $request->validate([
                'status' => ['required', Rule::in($this->statuses())],
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
                'equipment_ids' => ['nullable', 'array'],
                'equipment_ids.*' => [
                    'integer',
                    Rule::exists('equipment', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)),
                ],
            ], [
                'start_time.regex' => 'Start hour must be on the hour or half hour.',
                'end_time.regex' => 'End hour must be on the hour or half hour.',
            ]);

            $package = $this->resolvePackageForSelection($data);
            $this->ensurePackageTimingSelection($package, $data);
            $addOnIds = collect($data['add_on_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $equipmentIds = collect($data['equipment_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();
            $bookingData = $data;
            unset($bookingData['add_on_ids'], $bookingData['equipment_ids']);
            $bookingData['booking_kind'] = $bookingData['booking_kind'] ?? 'customer';
            $bookingData['customer_id'] = $this->resolveCustomer($bookingData)->id;
            $bookingData['package_price'] = $this->resolvePackagePrice($bookingData, $package, true);
            [$discountId, $discountAmount] = $this->resolveDiscountSelection($bookingData, $package, $bookingData['package_price']);
            $bookingData['discount_id'] = $discountId;
            $bookingData['discount_amount'] = $discountAmount;

            DB::transaction(function () use ($booking, $bookingData, $addOnIds, $equipmentIds): void {
                $booking->update($bookingData);
                $booking->addOns()->sync($addOnIds);
                $booking->equipment()->sync($equipmentIds);
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

    private function renderAdminBookingsPage(CurrentTenant $currentTenant, ?Booking $selectedBooking = null, ?Request $request = null): View|JsonResponse
    {
        $request ??= request();
        $tenant = $currentTenant->get();
        $bookings = $this->paginatedBookings($request);

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $bookings->getCollection()->map(fn (Booking $booking) => $this->serializeBooking($booking))->values()->all(),
                'pagination' => $this->paginationMeta($bookings),
            ]);
        }

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
        $serializedBookings = $bookings->getCollection()
            ->map(fn (Booking $booking) => $this->serializeBooking($booking));

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
                    'settings' => route('settings.index'),
                    'logout' => route('logout'),
                ],
                'bookingStatuses' => $this->statuses(),
                'bookingKinds' => $this->bookingKinds(),
                'bookings' => $serializedBookings->values()->all(),
                'pagination' => $this->paginationMeta($bookings),
                'eventTypes' => $this->eventTypes(),
                ...$this->adminBookingCreateProps($tenant, $packages, $equipment, $addOns),
            ],
        ]);
    }

    private function renderAdminBookingDetailPage(CurrentTenant $currentTenant, Booking $booking): View
    {
        $tenant = $currentTenant->get();
        $booking->loadMissing(['package', 'addOns', 'equipment', 'discount', 'invoice.installments', 'tasks.assignedUser', 'tasks.status']);
        $tenantUsers = $tenant?->users()
            ->wherePivot('role', '!=', 'guest')
            ->orderBy('name')
            ->get() ?? collect();
        $taskStatuses = $tenant?->taskStatuses()->orderBy('name')->get() ?? collect();

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
                    'settings' => route('settings.index'),
                    'logout' => route('logout'),
                ],
                'bookingStatuses' => $this->statuses(),
                'bookingKinds' => $this->bookingKinds(),
                'eventTypes' => $this->eventTypes(),
                'defaultDepositPercentage' => (float) ($tenant?->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)),
                'booking' => $this->serializeBooking($booking),
                'taskUsers' => $tenantUsers->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])->values()->all(),
                'taskStatuses' => $taskStatuses->map(fn (TaskStatus $status) => [
                    'id' => $status->id,
                    'name' => $status->name,
                ])->values()->all(),
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
                'addon_category' => $item->addon_category,
                'duration' => $item->duration,
                'price' => number_format((float) $item->unit_price, 2, '.', ''),
            ])->values()->all(),
            'discountOptions' => $this->serializeDiscountOptions($this->availableDiscounts()),
        ];
    }

    private function paginatedBookings(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $bookingKind = (string) $request->query('booking_kind', 'all');

        return Booking::query()
            ->with(['package', 'addOns', 'equipment', 'discount', 'invoice.installments'])
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
        $bookings = Booking::query()->with(['package'])->latest('event_date')->latest()->get();

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
                'bookings' => $bookings->map(fn (Booking $booking) => $this->serializeBooking($booking))->values()->all(),
            ],
        ]);
    }

    private function renderAdminQuotesPage(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $quotes = Booking::query()
            ->with(['package', 'invoice'])
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
                'quoteResponseStatuses' => ['all', 'pending', 'accepted', 'rejected'],
                'quotes' => $quotes->map(fn (Booking $booking) => $this->serializeBooking($booking))->values()->all(),
            ],
        ]);
    }

    private function serializeBooking(Booking $booking): array
    {
        $booking->loadMissing(['discount', 'package.hourlyPrices', 'tasks.assignedUser', 'tasks.status']);
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
            'package_id' => $booking->package_id,
            'package_hourly_price_id' => $packageHourlyPriceId ? (string) $packageHourlyPriceId : '',
            'discount_id' => $booking->discount_id ? (string) $booking->discount_id : '',
            'package_price' => number_format($packagePrice, 2, '.', ''),
            'discount_amount' => number_format((float) ($booking->discount_amount ?? 0), 2, '.', ''),
            'event_type' => $booking->event_type,
            'event_type_label' => $booking->event_type ? str($booking->event_type)->title()->toString() : null,
            'event_date' => $booking->event_date?->format('Y-m-d'),
            'event_date_label' => $booking->event_date?->format('d M Y'),
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
                'starts_at_label' => $booking->discount->starts_at?->format('d M Y'),
                'ends_at_label' => $booking->discount->ends_at?->format('d M Y'),
            ] : null,
            'notes' => $booking->notes,
            'status' => $booking->status,
            'customer_response_status' => $booking->customer_response_status,
            'customer_response_label' => str($booking->customer_response_status)->replace('_', ' ')->title()->toString(),
            'customer_responded_at_label' => $booking->customer_responded_at?->format('d M Y g:i A'),
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
                'addon_category' => $item->addon_category,
                'description' => $item->description,
                'price' => number_format((float) $item->unit_price, 2, '.', ''),
                'duration' => $item->duration,
                'photo_url' => $item->photo_path ? Storage::disk('public')->url($item->photo_path) : null,
            ])->values()->all(),
            'equipment' => $booking->equipment->map(fn (Equipment $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => number_format((float) $item->daily_rate, 2, '.', ''),
                'photo_url' => $item->photo_path ? Storage::disk('public')->url($item->photo_path) : null,
            ])->values()->all(),
            'add_on_ids' => $booking->addOns->pluck('id')->values()->all(),
            'equipment_ids' => $booking->equipment->pluck('id')->values()->all(),
            'tasks' => $booking->tasks
                ->sortByDesc(fn (Task $task) => $task->created_at)
                ->values()
                ->map(fn (Task $task) => $this->serializeTaskRecord($task))
                ->all(),
            'invoice' => $booking->invoice ? $this->serializeInvoice($booking->invoice) : null,
            'show_url' => route('admin.bookings.show', $booking),
            'update_url' => route('admin.bookings.update', $booking),
            'invoice_create_url' => route('admin.bookings.invoice.store', $booking),
        ];
    }

    private function serializeTaskRecord(Task $task): array
    {
        return [
            'id' => $task->id,
            'task_name' => $task->task_name,
            'task_duration_hours' => $task->task_duration_hours,
            'assigned_to' => $task->assigned_to,
            'assigned_to_name' => $task->assignedUser?->name ?? 'Unassigned',
            'booking_id' => $task->booking_id,
            'task_status_id' => $task->task_status_id,
            'status_name' => $task->status?->name ?? '',
            'due_date' => $task->due_date?->format('Y-m-d') ?? '',
            'due_date_label' => $task->due_date?->format('d M Y') ?? 'Not set',
            'date_started' => $task->date_started?->format('Y-m-d') ?? '',
            'date_started_label' => $task->date_started?->format('d M Y') ?? 'Not set',
            'date_completed' => $task->date_completed?->format('Y-m-d') ?? '',
            'date_completed_label' => $task->date_completed?->format('d M Y') ?? 'Not set',
            'remarks' => $task->remarks ?? '',
            'update_url' => route('tasks.update', $task),
            'delete_url' => route('tasks.destroy', $task),
        ];
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
            $customer = new Customer();
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
            'starts_at' => $discount->starts_at?->format('Y-m-d'),
            'ends_at' => $discount->ends_at?->format('Y-m-d'),
            'starts_at_label' => $discount->starts_at?->format('d M Y'),
            'ends_at_label' => $discount->ends_at?->format('d M Y'),
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
            'event_location' => $booking->event_location,
            'notes' => $booking->notes,
            'status' => 'booked',
            'last_activity_at' => now(),
        ]);
        $lead->save();
    }

    private function sendBookingEmails(Booking $booking): void
    {
        $addonsPdf = app(BookingAddonsPdfGenerator::class)->makeForBooking($booking);
        $adminRecipients = $booking->tenant?->users
            ->filter(fn (User $user) => $user->pivot?->role === 'owner')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        if ($adminRecipients !== []) {
            Mail::to($adminRecipients)->send(new AdminBookingCreatedMail($booking, $addonsPdf));
        }

        Mail::to($booking->customer_email)->send(new CustomerBookingCreatedMail($booking, $addonsPdf));
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

    private function timeValue(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function timeLabel(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time.':00' : $time)->format('g:i A');
    }
}
