<?php

namespace App\Support;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BookingAddonsPdfGenerator
{
    public function makeForBooking(Booking $booking): ?BookingAddonsPdfAttachment
    {
        $booking->loadMissing('package', 'tenant', 'addOns');

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
        $bookingTotal = max(0, $packagePrice + $addOnTotal + $travelFee - $discountAmount);

        $pdf = Pdf::loadView('pdf.bookings.addons', [
            'booking' => $booking,
            'tenant' => $booking->tenant,
            'package' => [
                'name' => $package->name,
                'description' => $package->description,
                'price' => $packagePrice,
                'image_data_uri' => $this->imageDataUri($package->photo_path),
            ],
            'travel' => [
                'distance_km' => $booking->travel_distance_km,
                'fee' => $travelFee,
            ],
            'discount' => [
                'code' => $booking->discount?->code,
                'name' => $booking->discount?->name,
                'amount' => $discountAmount,
            ],
            'booking_total' => $bookingTotal,
            'addons' => $addons->map(fn ($addon) => [
                'product_code' => $addon->sku,
                'name' => $addon->name,
                'category' => $addon->type ?: $addon->addon_category,
                'description' => $addon->description,
                'price' => $addon->discountedUnitPriceForBookingSelection(
                    $addon->pivot?->discount_type,
                    $addon->pivot?->discount_value,
                    (float) ($addon->pivot?->discount_percentage ?? 0),
                ),
                'duration' => $addon->duration,
                'image_data_uri' => $this->imageDataUri($addon->photo_path),
            ])->values(),
        ]);

        $slug = Str::slug($package->name ?: 'package');

        return new BookingAddonsPdfAttachment(
            name: $slug.'-booking-details.pdf',
            content: $pdf->output(),
        );
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
