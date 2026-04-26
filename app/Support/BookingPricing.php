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
            + (float) $booking->equipment->sum(fn ($equipment) => $equipment->discountedDailyRateForBooking(
                $equipment->pivot?->discount_type,
                $equipment->pivot?->discount_value,
                (float) ($equipment->pivot?->discount_percentage ?? 0),
            ))
            + (float) $booking->addOns->sum(fn ($addOn) => $addOn->discountedUnitPriceForBookingSelection(
                $addOn->pivot?->discount_type,
                $addOn->pivot?->discount_value,
                (float) ($addOn->pivot?->discount_percentage ?? 0),
            ))
            + (float) ($booking->travel_fee ?? 0)
            - (float) ($booking->discount_amount ?? 0);
    }
}
