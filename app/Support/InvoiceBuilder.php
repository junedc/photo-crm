<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Support\TenantStatuses;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceBuilder
{
    public function __construct(
        private readonly BookingPricing $bookingPricing,
    ) {
    }

    public function createForBooking(
        Booking $booking,
        int $installmentCount,
        ?float $depositPercentage,
        Carbon $firstDueDate,
        int $intervalDays,
    ): Invoice {
        if ($booking->invoice()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'An invoice already exists for this booking.',
            ]);
        }

        $totalAmount = $this->bookingPricing->totalForBooking($booking);
        $invoiceStatus = $booking->tenant
            ? TenantStatuses::firstOrCreateWorkspaceStatus($booking->tenant, TenantStatuses::SCOPE_INVOICE, 'issued')
            : null;

        $invoice = Invoice::query()->create([
            'booking_id' => $booking->id,
            'invoice_number' => $this->nextInvoiceNumber(),
            'token' => Str::random(40),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'amount_paid' => 0,
            'invoice_status_id' => $invoiceStatus?->id,
            'status' => $invoiceStatus?->name ?? 'issued',
            'issued_at' => now(),
        ]);

        $this->createInstallments(
            $invoice,
            $installmentCount,
            (float) ($depositPercentage ?? config('invoicing.deposit_percentage', 30)),
            $firstDueDate,
            $intervalDays,
            (int) round($totalAmount * 100),
        );

        return $invoice->load('installments');
    }

    private function createInstallments(Invoice $invoice, int $count, float $depositPercentage, Carbon $firstDueDate, int $intervalDays, int $totalAmountCents): void
    {
        $depositAmount = (int) round($totalAmountCents * ($depositPercentage / 100));
        $remainingAmount = max($totalAmountCents - $depositAmount, 0);
        $remainingInstallments = max($count - 1, 0);
        $baseRemainingAmount = $remainingInstallments > 0 ? intdiv($remainingAmount, $remainingInstallments) : 0;
        $remainingRemainder = $remainingInstallments > 0 ? $remainingAmount % $remainingInstallments : 0;

        foreach (range(1, $count) as $sequence) {
            if ($sequence === 1) {
                $label = 'Deposit';
                $amountInCents = $count === 1 ? $totalAmountCents : $depositAmount;
            } else {
                $label = 'Installment '.($sequence - 1);
                $isLastRemainingInstallment = $sequence === $count;
                $amountInCents = $baseRemainingAmount + ($isLastRemainingInstallment ? $remainingRemainder : 0);
            }

            $status = $invoice->tenant
                ? TenantStatuses::firstOrCreateWorkspaceStatus($invoice->tenant, TenantStatuses::SCOPE_INVOICE_INSTALLMENT, 'pending')
                : null;

            InvoiceInstallment::query()->create([
                'invoice_id' => $invoice->id,
                'sequence' => $sequence,
                'label' => $label,
                'due_date' => $firstDueDate->copy()->addDays(($sequence - 1) * $intervalDays)->toDateString(),
                'amount' => number_format($amountInCents / 100, 2, '.', ''),
                'invoice_installment_status_id' => $status?->id,
                'status' => $status?->name ?? 'pending',
            ]);
        }
    }

    private function nextInvoiceNumber(): string
    {
        $tenant = $this->bookingPricingTenant();
        $prefix = $tenant?->invoice_prefix ?: 'INV';

        return strtoupper($prefix).'-'.now()->format('Ymd').'-'.str_pad((string) ((Invoice::query()->count()) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function bookingPricingTenant(): ?\App\Models\Tenant
    {
        $tenantId = app(\App\Tenancy\CurrentTenant::class)->id();

        if ($tenantId === null) {
            return null;
        }

        return \App\Models\Tenant::query()->withoutGlobalScopes()->find($tenantId);
    }
}
