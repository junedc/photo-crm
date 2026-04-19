<?php

namespace App\Support;

use App\Models\Discount;
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingDiscountResolver
{
    public function isActive(Discount $discount, CarbonInterface|string|null $date = null): bool
    {
        $date = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();

        return $discount->starts_at !== null
            && $discount->ends_at !== null
            && $discount->starts_at->startOfDay()->lte($date)
            && $discount->ends_at->startOfDay()->gte($date);
    }

    public function appliesToSelection(
        Discount $discount,
        ?int $packageId,
    ): bool {
        return $packageId !== null && DB::table('discount_package')
            ->where('discount_id', $discount->id)
            ->where('package_id', $packageId)
            ->exists();
    }

    public function calculateAmount(
        Discount $discount,
        ?int $packageId,
        float $packagePrice,
    ): float {
        $applicableSubtotal = $this->applicableSubtotal($discount, $packageId, $packagePrice);

        if ($applicableSubtotal <= 0) {
            return 0;
        }

        if ($discount->discount_type === 'percentage') {
            return round($applicableSubtotal * (((float) $discount->discount_value) / 100), 2);
        }

        return round(min((float) $discount->discount_value, $applicableSubtotal), 2);
    }

    private function applicableSubtotal(
        Discount $discount,
        ?int $packageId,
        float $packagePrice,
    ): float {
        $subtotal = 0;

        if ($packageId !== null && DB::table('discount_package')
            ->where('discount_id', $discount->id)
            ->where('package_id', $packageId)
            ->exists()) {
            $subtotal += $packagePrice;
        }

        return round($subtotal, 2);
    }
}
