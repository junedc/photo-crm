<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicCatalogController extends Controller
{
    public function packages(Request $request): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], Response::HTTP_NO_CONTENT, $this->corsHeaders());
        }

        $data = $request->validate([
            'tenant' => ['required', 'string', 'max:255'],
        ]);

        $tenant = $this->resolveTenant($request, $data['tenant']);
        $this->ensureAuthorized($request, $tenant);

        $packages = Package::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['equipment', 'addOns', 'hourlyPrices'])
            ->latest()
            ->get()
            ->map(fn (Package $package) => $this->serializePackage($request, $tenant, $package))
            ->values();

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'booking_create_url' => $this->tenantBookingUrl($request, $tenant),
            ],
            'packages' => $packages,
        ], 200, $this->corsHeaders());
    }

    private function resolveTenant(Request $request, string $tenantSlug): Tenant
    {
        return Tenant::query()
            ->where('slug', $tenantSlug)
            ->firstOrFail();
    }

    private function serializePackage(Request $request, Tenant $tenant, Package $package): array
    {
        $displayPrice = $package->hourlyPrices->min('price') ?? $package->base_price;

        return [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'base_price' => number_format((float) $package->base_price, 2, '.', ''),
            'display_price' => number_format((float) $displayPrice, 2, '.', ''),
            'photo_url' => $package->photo_path ? url(Storage::disk('public')->url($package->photo_path)) : null,
            'booking_url' => $this->tenantBookingUrl($request, $tenant, [
                'package_id' => $package->id,
            ]),
            'equipment' => $package->equipment->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'description' => $item->description,
            ])->values()->all(),
            'add_ons' => $package->addOns->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'addon_category' => $item->addon_category,
                'description' => $item->description,
                'price' => number_format((float) $item->unit_price, 2, '.', ''),
            ])->values()->all(),
            'hourly_prices' => $package->hourlyPrices->map(fn ($tier) => [
                'id' => $tier->id,
                'hours' => number_format((float) $tier->hours, 2, '.', ''),
                'hours_label' => rtrim(rtrim(number_format((float) $tier->hours, 2, '.', ''), '0'), '.').' hours',
                'price' => number_format((float) $tier->price, 2, '.', ''),
            ])->values()->all(),
        ];
    }

    private function tenantBookingUrl(Request $request, Tenant $tenant, array $query = []): string
    {
        $scheme = $request->getScheme();
        $port = $request->getPort();
        $baseDomain = (string) config('app.tenant_base_domain', '');
        $host = $tenant->slug;

        if ($baseDomain !== '') {
            $host .= '.'.$baseDomain;
        }

        $portSuffix = in_array($port, [80, 443], true) ? '' : ':'.$port;
        $url = $scheme.'://'.$host.$portSuffix.'/bookings/create';

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query);
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, X-Tenant-Api-Key',
        ];
    }

    private function ensureAuthorized(Request $request, Tenant $tenant): void
    {
        $providedKey = (string) $request->header('X-Tenant-Api-Key', '');
        $expectedKey = (string) ($tenant->packages_api_key ?? '');

        abort_if($expectedKey === '', 403, 'This workspace has not configured a packages API key.');
        abort_unless($providedKey !== '' && hash_equals($expectedKey, $providedKey), 401, 'Invalid packages API key.');
    }
}
