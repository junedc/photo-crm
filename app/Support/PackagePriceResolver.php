<?php

namespace App\Support;

use App\Models\Package;

class PackagePriceResolver
{
    public function forHours(?Package $package, float|int|string|null $hours, ?float $fallback = null): float
    {
        if ($package === null) {
            return (float) ($fallback ?? 0);
        }

        $normalizedHours = number_format((float) ($hours ?? 0), 2, '.', '');
        $package->loadMissing('hourlyPrices');

        $tier = $package->hourlyPrices->first(fn ($entry) => number_format((float) $entry->hours, 2, '.', '') === $normalizedHours);

        if ($tier !== null) {
            return (float) $tier->price;
        }

        return (float) ($fallback ?? $package->base_price ?? 0);
    }

    public function applyDiscount(float|int|string|null $amount, float|int|string|null $discountPercentage): float
    {
        $normalizedAmount = (float) ($amount ?? 0);
        $normalizedDiscount = max(0, min(100, (float) ($discountPercentage ?? 0)));

        if ($normalizedDiscount <= 0) {
            return $normalizedAmount;
        }

        return round($normalizedAmount * (1 - ($normalizedDiscount / 100)), 2);
    }
}
