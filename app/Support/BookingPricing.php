<?php

namespace App\Support;

use App\Models\Booking;

class BookingPricing
{
    public function __construct(
        private readonly PackagePriceResolver $packagePriceResolver,
    ) {
    }

    public function totalForBooking(Booking $booking): float
    {
        $booking->loadMissing(['package', 'equipment', 'addOns', 'discount']);

        return (float) ($booking->package_price ?? $this->packagePriceResolver->forHours($booking->package, $booking->total_hours))
            + (float) $booking->equipment->sum('daily_rate')
            + (float) $booking->addOns->sum('unit_price')
            + (float) ($booking->travel_fee ?? 0)
            - (float) ($booking->discount_amount ?? 0);
    }
}
