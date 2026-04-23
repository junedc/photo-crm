<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\LoginVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CatalogAdminController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TenantOnboardingController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::get('/', [TenantOnboardingController::class, 'create'])->name('home');
Route::match(['GET', 'OPTIONS'], '/api/public/packages', [PublicCatalogController::class, 'packages'])->name('api.public.packages');
Route::post('/workspaces', [TenantOnboardingController::class, 'store'])
    ->middleware('guest')
    ->name('workspaces.store');
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->defaults('scope', 'tenant')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('stripe.webhook');
Route::post('/platform/stripe/webhook', StripeWebhookController::class)
    ->defaults('scope', 'platform')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('platform.stripe.webhook');

Route::prefix('admin')->name('super-admin.')->group(function () {
    Route::get('/login', [SuperAdminController::class, 'login'])->name('login');
    Route::post('/login', [SuperAdminController::class, 'sendCode'])->name('login.store');
    Route::get('/verify', [SuperAdminController::class, 'verify'])->name('verify');
    Route::post('/verify', [SuperAdminController::class, 'confirm'])->name('verify.store');
    Route::post('/verify/resend', [SuperAdminController::class, 'resend'])->name('verify.resend');
    Route::post('/logout', [SuperAdminController::class, 'logout'])->name('logout');

    Route::middleware('super.admin')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index'])->name('index');
        Route::post('/subscriptions', [SuperAdminController::class, 'storeSubscription'])->name('subscriptions.store');
        Route::put('/subscriptions/{subscription}', [SuperAdminController::class, 'updateSubscription'])->name('subscriptions.update');
        Route::put('/tenants/{tenant}/subscription', [SuperAdminController::class, 'updateTenantSubscription'])->name('tenants.subscription.update');
        Route::put('/tenants/{tenant}/access', [SuperAdminController::class, 'updateTenantAccess'])->name('tenants.access.update');
        Route::post('/environment', [SuperAdminController::class, 'updateEnvironment'])->name('environment.update');
    });
});

Route::middleware('tenant.required')->group(function () {
    Route::get('/bookings/terms-and-conditions', [BookingController::class, 'terms'])->name('bookings.terms');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('/quotes/{booking:quote_token}/{response}', [BookingController::class, 'respondToQuote'])
        ->whereIn('response', ['accept', 'reject'])
        ->name('quotes.respond');
    Route::post('/bookings/autosave-lead', [BookingController::class, 'autosaveLead'])->name('bookings.autosave-lead');
    Route::post('/bookings/book-now', [BookingController::class, 'bookNow'])->name('bookings.book-now');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/invoices/{invoice:token}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice:token}/status', [InvoiceController::class, 'status'])->name('invoices.status');
    Route::post('/invoices/{invoice:token}/installments/{installment}/pay', [InvoiceController::class, 'pay'])->name('invoices.installments.pay');
    Route::get('/campaigns/open/{token}.gif', [CampaignController::class, 'trackOpen'])->name('campaigns.track-open');
    Route::get('/campaigns/unsubscribe/{token}', [CampaignController::class, 'unsubscribe'])->name('campaigns.unsubscribe');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::get('/verify-login', [LoginVerificationController::class, 'create'])->name('verification.notice');
        Route::post('/verify-login', [LoginVerificationController::class, 'store'])->name('verification.verify');
        Route::post('/verify-login/resend', [LoginVerificationController::class, 'resend'])->name('verification.resend');
        Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    });

    Route::middleware(['auth', 'admin.access'])->group(function () {
        Route::get('/dashboard', [CatalogAdminController::class, 'index'])->name('dashboard');
        Route::get('/packages', [CatalogAdminController::class, 'packagesIndex'])->name('packages.index');
        Route::get('/packages/create', [CatalogAdminController::class, 'packagesCreate'])->name('packages.create');
        Route::get('/packages/{package}', [CatalogAdminController::class, 'packagesShow'])->name('packages.show');
        Route::get('/equipment', [CatalogAdminController::class, 'equipmentIndex'])->name('equipment.index');
        Route::get('/equipment/create', [CatalogAdminController::class, 'equipmentCreate'])->name('equipment.create');
        Route::get('/equipment/{equipment}', [CatalogAdminController::class, 'equipmentShow'])->name('equipment.show');
        Route::get('/addons', [CatalogAdminController::class, 'addOnsIndex'])->name('addons.index');
        Route::get('/addons/create', [CatalogAdminController::class, 'addOnsCreate'])->name('addons.create');
        Route::get('/addons/{addon}', [CatalogAdminController::class, 'addOnsShow'])->name('addons.show');
        Route::get('/leads', [CatalogAdminController::class, 'leadsIndex'])->name('leads.index');
        Route::get('/leads/create', [CatalogAdminController::class, 'leadsCreate'])->name('leads.create');
        Route::get('/leads/{lead}', [CatalogAdminController::class, 'leadsShow'])->name('leads.show');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/discounts', [DiscountController::class, 'index'])->name('discounts.index');
        Route::get('/discounts/create', [DiscountController::class, 'create'])->name('discounts.create');
        Route::get('/discounts/{discount}', [DiscountController::class, 'show'])->name('discounts.show');
        Route::get('/admin/bookings', [BookingController::class, 'index'])->name('admin.bookings.index');
        Route::get('/admin/quotes', [BookingController::class, 'quotes'])->name('admin.quotes.index');
        Route::get('/admin/invoices', [InvoiceController::class, 'index'])->name('admin.invoices.index');
        Route::get('/admin/calendar', [BookingController::class, 'calendar'])->name('admin.calendar.index');
        Route::get('/admin/bookings/{booking}', [BookingController::class, 'show'])->name('admin.bookings.show');
        Route::get('/support', [SupportController::class, 'index'])->name('support.index');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/users', [UserRoleController::class, 'users'])->name('users.index');
        Route::get('/roles', [UserRoleController::class, 'roles'])->name('roles.index');
        Route::get('/access-control', [UserRoleController::class, 'access'])->name('access.index');
        Route::post('/packages', [CatalogAdminController::class, 'storePackage'])->name('packages.store');
        Route::put('/packages/{package}', [CatalogAdminController::class, 'updatePackage'])->name('packages.update');
        Route::delete('/packages/{package}', [CatalogAdminController::class, 'destroyPackage'])->name('packages.destroy');
        Route::post('/equipment', [CatalogAdminController::class, 'storeEquipment'])->name('equipment.store');
        Route::put('/equipment/{equipment}', [CatalogAdminController::class, 'updateEquipment'])->name('equipment.update');
        Route::delete('/equipment/{equipment}', [CatalogAdminController::class, 'destroyEquipment'])->name('equipment.destroy');
        Route::post('/addons', [CatalogAdminController::class, 'storeAddOn'])->name('addons.store');
        Route::put('/addons/{addon}', [CatalogAdminController::class, 'updateAddOn'])->name('addons.update');
        Route::delete('/addons/{addon}', [CatalogAdminController::class, 'destroyAddOn'])->name('addons.destroy');
        Route::post('/leads', [CatalogAdminController::class, 'storeLead'])->name('leads.store');
        Route::put('/leads/{lead}', [CatalogAdminController::class, 'updateLead'])->name('leads.update');
        Route::delete('/leads/{lead}', [CatalogAdminController::class, 'destroyLead'])->name('leads.destroy');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::post('/campaigns/templates', [CampaignController::class, 'storeTemplate'])->name('campaigns.templates.store');
        Route::put('/campaigns/templates/{template}', [CampaignController::class, 'updateTemplate'])->name('campaigns.templates.update');
        Route::post('/campaigns/groups', [CampaignController::class, 'storeGroup'])->name('campaigns.groups.store');
        Route::put('/campaigns/groups/{group}', [CampaignController::class, 'updateGroup'])->name('campaigns.groups.update');
        Route::post('/campaigns/groups/{group}/recipients', [CampaignController::class, 'storeGroupRecipients'])->name('campaigns.groups.recipients.store');
        Route::post('/campaigns/groups/{group}/import', [CampaignController::class, 'importGroup'])->name('campaigns.groups.import');
        Route::post('/campaigns/results/{result}/bounce', [CampaignController::class, 'markBounce'])->name('campaigns.results.bounce');
        Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
        Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('/discounts', [DiscountController::class, 'store'])->name('discounts.store');
        Route::put('/discounts/{discount}', [DiscountController::class, 'update'])->name('discounts.update');
        Route::delete('/discounts/{discount}', [DiscountController::class, 'destroy'])->name('discounts.destroy');
        Route::post('/support/tickets', [SupportController::class, 'storeTicket'])->name('support.tickets.store');
        Route::post('/settings/workspace', [SettingsController::class, 'updateWorkspace'])->name('settings.workspace.update');
        Route::post('/settings/subscription/pay', [SettingsController::class, 'paySubscription'])->name('settings.subscription.pay');
        Route::post('/settings/account', [SettingsController::class, 'updateAccount'])->name('settings.account.update');
        Route::post('/users', [UserRoleController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [UserRoleController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [UserRoleController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/roles', [UserRoleController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}', [UserRoleController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [UserRoleController::class, 'destroyRole'])->name('roles.destroy');
        Route::post('/access-control', [UserRoleController::class, 'updateAccess'])->name('access.update');
        Route::put('/admin/bookings/{booking}', [BookingController::class, 'update'])->name('admin.bookings.update');
        Route::post('/admin/bookings/{booking}/invoice', [InvoiceController::class, 'store'])->name('admin.bookings.invoice.store');
        Route::post('/admin/bookings/{booking}/invoice/send', [InvoiceController::class, 'send'])->name('admin.bookings.invoice.send');
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
