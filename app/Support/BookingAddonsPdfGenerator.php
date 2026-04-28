<?php

namespace App\Support;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BookingAddonsPdfGenerator
{
    public function makeForBooking(Booking $booking): ?BookingAddonsPdfAttachment
    {
        $booking->loadMissing('package', 'package.equipment', 'package.addOns', 'tenant', 'addOns', 'discount');

        $package = $booking->package;
        $addons = $booking->addOns;

        if ($package === null) {
            return null;
        }

        $packagePrice = (float) ($booking->package_price ?? $package->base_price);
        $addOnTotal = (float) $addons->sum(fn ($addon) => $addon->discountedUnitPriceForBookingSelection(
            $addon->pivot?->discount_type,
            $addon->pivot?->discount_value,
            (float) ($addon->pivot?->discount_percentage ?? 0),
        ));
        $travelFee = (float) ($booking->travel_fee ?? 0);
        $discountAmount = (float) ($booking->discount_amount ?? 0);
        $subtotal = max(0, $packagePrice + $addOnTotal + $travelFee);
        $bookingTotal = max(0, $subtotal - $discountAmount);
        $quoteDate = $booking->created_at instanceof Carbon ? $booking->created_at->copy() : now();
        $expiryDate = $quoteDate->copy()->addDays(7);
        $currencyCode = strtoupper((string) ($booking->tenant?->stripe_currency ?: config('services.platform_stripe.currency', 'AUD')));

        $items = collect([
            [
                'description_title' => trim(($package->name ?? 'Package').($booking->total_hours ? ' · '.$this->formatHoursLabel($booking->total_hours).' hrs' : '')),
                'type' => 'package',
                'description_lines' => $this->packageDescriptionLines($booking),
                'quantity' => 1,
                'unit_price' => $packagePrice,
                'discount_label' => '-',
                'amount' => $packagePrice,
            ],
        ]);

        foreach ($addons as $addon) {
            $addonUnitPrice = (float) ($addon->unit_price ?? 0);
            $addonPrice = (float) $addon->discountedUnitPriceForBookingSelection(
                $addon->pivot?->discount_type,
                $addon->pivot?->discount_value,
                (float) ($addon->pivot?->discount_percentage ?? 0),
            );

            $items->push([
                'type' => 'add_on',
                'description_title' => $addon->name,
                'description_lines' => array_values(array_filter([
                    $addon->duration ? 'Duration: '.$addon->duration : null,
                    filled($addon->description) ? trim((string) $addon->description) : null,
                ])),
                'quantity' => 1,
                'unit_price' => $addonUnitPrice,
                'discount_label' => $this->discountLabel(
                    $addon->pivot?->discount_type,
                    $addon->pivot?->discount_value,
                    (float) ($addon->pivot?->discount_percentage ?? 0),
                ),
                'amount' => $addonPrice,
            ]);
        }

        if ($travelFee > 0) {
            $items->push([
                'type' => 'travel_fee',
                'description_title' => 'Travel Fee',
                'description_lines' => array_values(array_filter([
                    $booking->travel_distance_km !== null ? 'Distance: '.number_format((float) $booking->travel_distance_km, 2).' km' : null,
                    filled($booking->event_location) ? 'Location: '.trim((string) $booking->event_location) : null,
                ])),
                'quantity' => 1,
                'unit_price' => $travelFee,
                'discount_label' => '-',
                'amount' => $travelFee,
            ]);
        }

        $pdf = Pdf::loadView('pdf.bookings.addons', [
            'booking' => $booking,
            'tenant' => $booking->tenant,
            'quote_date' => $quoteDate,
            'expiry_date' => $expiryDate,
            'customer_name' => $booking->customer_name ?: $booking->entry_name,
            'currency_code' => $currencyCode,
            'logo_data_uri' => $this->imageDataUri($booking->tenant?->logo_path),
            'line_items' => $items->values(),
            'subtotal' => $subtotal,
            'discount' => [
                'code' => $booking->discount?->code,
                'name' => $booking->discount?->name,
                'amount' => $discountAmount,
            ],
            'business_lines' => $this->businessLines($booking),
            'booking_total' => $bookingTotal,
        ]);

        $slug = Str::slug($booking->quote_number ?: $package->name ?: 'quote');

        return new BookingAddonsPdfAttachment(
            name: $slug.'.pdf',
            content: $pdf->output(),
        );
    }

    private function packageDescriptionLines(Booking $booking): array
    {
        $equipmentNames = collect($booking->package?->equipment ?? [])
            ->map(fn ($item) => trim((string) ($item->name ?? '')))
            ->filter()
            ->values();

        $addOnNames = collect($booking->package?->addOns ?? [])
            ->map(fn ($item) => trim((string) ($item->name ?? '')))
            ->filter()
            ->values();

        $inclusions = $equipmentNames
            ->concat($addOnNames)
            ->unique()
            ->values();

        if ($inclusions->isNotEmpty()) {
            return $inclusions
                ->map(fn (string $item) => '- '.$item)
                ->all();
        }

        $lines = [];

        if (filled($booking->package?->description)) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $booking->package->description) ?: [] as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $lines[] = ltrim($line, "- \t");
                }
            }
        }

        return $lines;
    }

    private function businessLines(Booking $booking): array
    {
        $tenant = $booking->tenant;

        return array_values(array_filter([
            $tenant?->name ?: 'MemoShot',
            filled($tenant?->abn) ? 'ABN: '.trim((string) $tenant->abn) : null,
            filled($tenant?->address) ? trim((string) $tenant->address) : null,
            filled($tenant?->contact_phone) ? trim((string) $tenant->contact_phone) : null,
            filled($tenant?->contact_email) ? trim((string) $tenant->contact_email) : null,
        ]));
    }

    private function discountLabel(mixed $discountType = null, mixed $discountValue = null, float $legacyPercentage = 0): string
    {
        if ($discountType === 'amount' && (float) ($discountValue ?? 0) > 0) {
            return '$'.number_format((float) $discountValue, 2);
        }

        $percentage = (float) ($legacyPercentage > 0 ? $legacyPercentage : ($discountValue ?? 0));

        if ($percentage > 0) {
            return number_format($percentage, 2).'%';
        }

        return '-';
    }

    private function formatHoursLabel(mixed $value): string
    {
        $hours = (float) $value;

        if (fmod($hours, 1.0) === 0.0) {
            return (string) (int) $hours;
        }

        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    private function imageDataUri(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $content = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
