<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantReferral;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): View
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $this->ensureReferralCode($tenant);

        $tickets = SupportTicket::query()
            ->with('user')
            ->latest()
            ->get();

        $referrals = TenantReferral::query()
            ->with('referredTenant')
            ->where('referrer_tenant_id', $tenant->id)
            ->latest()
            ->get();

        return view('admin.app', [
            'page' => 'support',
            'props' => [
                'tenant' => $this->serializeTenant($tenant->fresh()),
                'routes' => $this->baseRoutes(),
                'tickets' => $tickets->map(fn (SupportTicket $ticket): array => $this->serializeTicket($ticket))->values()->all(),
                'ticketTypes' => SupportTicket::types(),
                'ticketPriorities' => SupportTicket::priorities(),
                'ticketStatuses' => SupportTicket::statuses(),
                'referral' => [
                    'code' => $tenant->referral_code,
                    'url' => rtrim((string) config('app.url'), '/').'/?ref='.$tenant->referral_code,
                    'count' => $referrals->count(),
                    'qualified_count' => $referrals->where('status', TenantReferral::STATUS_QUALIFIED)->count(),
                    'rewarded_count' => $referrals->where('status', TenantReferral::STATUS_REWARDED)->count(),
                    'referred' => $referrals->map(fn (TenantReferral $referral): array => $this->serializeReferral($referral))->values()->all(),
                ],
            ],
        ]);
    }

    public function storeTicket(CurrentTenant $currentTenant, Request $request): RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(SupportTicket::types()))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::priorities()))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        SupportTicket::query()->create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
            'ticket_number' => $this->newTicketNumber(),
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return redirect()->route('support.index')->with('status', 'Support ticket submitted.');
    }

    private function ensureReferralCode(Tenant $tenant): void
    {
        if (filled($tenant->referral_code)) {
            return;
        }

        do {
            $code = Str::upper(Str::random(8));
        } while (Tenant::query()->where('referral_code', $code)->exists());

        $tenant->forceFill(['referral_code' => $code])->save();
    }

    private function newTicketNumber(): string
    {
        do {
            $number = 'TCK-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (SupportTicket::query()->withoutGlobalScopes()->where('ticket_number', $number)->exists());

        return $number;
    }

    private function serializeTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'type' => $ticket->type,
            'type_label' => SupportTicket::types()[$ticket->type] ?? Str::headline($ticket->type),
            'priority' => $ticket->priority,
            'priority_label' => SupportTicket::priorities()[$ticket->priority] ?? Str::headline($ticket->priority),
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'status_label' => SupportTicket::statuses()[$ticket->status] ?? Str::headline($ticket->status),
            'created_by' => $ticket->user?->name,
            'created_at' => $ticket->created_at?->format('d M Y g:i A'),
        ];
    }

    private function serializeReferral(TenantReferral $referral): array
    {
        return [
            'id' => $referral->id,
            'workspace_name' => $referral->referredTenant?->name ?? $referral->referred_workspace_name,
            'owner_email' => $referral->referred_owner_email,
            'status' => $referral->status,
            'status_label' => TenantReferral::statuses()[$referral->status] ?? Str::headline($referral->status),
            'created_at' => $referral->created_at?->format('d M Y'),
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
            'support' => route('support.index'),
            'supportTicketsStore' => route('support.tickets.store'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
    }
}
