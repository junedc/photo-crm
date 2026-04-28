<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class BookingObserver
{
    public function created(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking): void {
            $email = strtolower(trim((string) $booking->customer_email));
            $phone = trim((string) $booking->customer_phone);

            if ($email === '' && $phone === '') {
                return;
            }

            Lead::query()
                ->where('tenant_id', $booking->tenant_id)
                ->whereNull('booking_id')
                ->where(function ($query) use ($email, $phone): void {
                    if ($email !== '') {
                        $query->whereRaw('LOWER(customer_email) = ?', [$email]);
                    }

                    if ($phone !== '') {
                        $method = $email !== '' ? 'orWhere' : 'where';
                        $query->{$method}('customer_phone', $phone);
                    }
                })
                ->delete();
        });
    }
}
