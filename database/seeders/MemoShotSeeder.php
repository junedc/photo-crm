<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\Package;
use App\Models\PackageHourlyPrice;
use App\Models\Tenant;
use App\Models\Template;
use App\Models\User;
use App\Support\TenantStatuses;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemoShotSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'm'],
            [
                'name' => 'MemoShot',
                'logo_path' => null,
                'contact_email' => null,
                'contact_phone' => null,
                'address' => null,
                'invoice_deposit_percentage' => null,
                'travel_free_kilometers' => null,
                'travel_fee_per_kilometer' => null,
                'quote_prefix' => null,
                'booking_number_prefix' => null,
                'invoice_prefix' => null,
            ]
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'junecruzes@gmail.com'],
            [
                'name' => 'MemoShot',
                'password' => '$2y$12$m3Tg1T9PX1JFGrfjA103LudkIWlWsGnYFgzJviUzQ6kwKLKyQiCcu',
                'current_tenant_id' => $tenant->id,
                'remember_token' => null,
                'email_verified_at' => null,
            ]
        );

        DB::table('tenant_user')->updateOrInsert(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        collect($this->templateSeedData())->each(function (array $attributes) use ($tenant): void {
            Template::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $attributes['name'],
                ],
                [
                    'subject' => $attributes['subject'],
                    'preheader' => $attributes['preheader'],
                    'headline' => $attributes['headline'],
                    'html_body' => $attributes['html_body'],
                    'button_text' => $attributes['button_text'],
                    'button_url' => $attributes['button_url'],
                ]
            );
        });

        TenantStatusSeeder::seedTenant($tenant);
        Tenant::seedInventoryItemCategories($tenant);
        Tenant::seedExpenseCategories($tenant);
        Tenant::seedServiceOfferings($tenant);
        Tenant::seedEventTypes($tenant);

        $packageStatusIds = collect(TenantStatuses::records($tenant, TenantStatuses::SCOPE_PACKAGE))
            ->mapWithKeys(fn (array $status): array => [$status['name'] => $status['id']]);
        $equipmentStatusIds = collect(TenantStatuses::records($tenant, TenantStatuses::SCOPE_EQUIPMENT))
            ->mapWithKeys(fn (array $status): array => [$status['name'] => $status['id']]);

        $packages = collect([
            [
                'name' => 'Digital Set',
                'description' => "Feeling classic?\r\n\r\nThis set includes an easy to use photobooth with a friendly attendant, a selection of standard backdrops and templates, themed photobooth  animations for weddings, birthdays, and more. Plus fancy props to keep the fun going. \r\n\r\nYou will also receive unlimited digital photos, a QR code for instant downloads, and online gallery after the event.",
                'base_price' => 0,
                'photo_path' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Deluxe Set',
                'description' => "The sleek, interactive mirror photobooth lets guests pose, play and personalize their photos with fun animations, emojis, and signatures, creating unforgettable moments for weddings, parties, and corporate events. \r\n\r\nIncludes friendly and trained attendants,  standard backdrop, templates, themed animations, props, unlimited digital photos, unlimited on-site prints, QR downloads, and online gallery",
                'base_price' => 880,
                'photo_path' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Motion Set',
                'description' => "Fancy a spin?\r\n\r\nThe 360 video booth brings the party to life, letting guests strike dynamic poses and capture unlimited videos in style. \r\n\r\nIncludes friendly attendants, fancy props, choice of any backdrop, bollard and red carpet, floor LED lighting, umlimited videos, QR code for downloads, online gallery, delivery setup and removel.",
                'base_price' => 750,
                'photo_path' => 'catalog/vPjrXWQS4VLHgZZ9BcV9VmPRf9yuWgPxbXTNAqpw.png',
                'is_active' => true,
            ],
            [
                'name' => 'VIP Ultimate',
                'description' => "Our VIP Ultimate package combines the mirror booth and 360 video booth for a fully interactive and unforgettable experience. \r\n\r\nIncludes friend attendants, fancy props, unlimited photos, and videos, custom overlay,  QR downloads, online gallery, full delivery setup and removal. \r\n\r\nAs a token of appreciation, we would also provide audio guestbook,  scrapbook, 100 pieces of 2x6 magnetic holder or slanted acrylic frame, bollards, and red carpet at no extra costs.",
                'base_price' => 1199,
                'photo_path' => 'catalog/jXF8lU1baVV8vDHEjWBAo9T3fvhASfQQccso3HxR.png',
                'is_active' => true,
            ],
            [
                'name' => 'Glam Booth',
                'description' => 'A classic photo booth setup with studio lighting, instant prints, and a polished guest flow.',
                'base_price' => 1299,
                'photo_path' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Mirror Booth Luxe',
                'description' => 'Premium mirror booth experience with animated prompts and a more elevated event presentation.',
                'base_price' => 1899,
                'photo_path' => null,
                'is_active' => true,
            ],
        ])->mapWithKeys(function (array $attributes) use ($tenant, $packageStatusIds): array {
            $package = Package::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $attributes['name'],
                ],
                [
                    'description' => $attributes['description'],
                    'base_price' => $attributes['base_price'],
                    'photo_path' => $attributes['photo_path'],
                    'package_status_id' => $packageStatusIds[$attributes['is_active'] ? 'active' : 'inactive'] ?? null,
                    'status' => $attributes['is_active'] ? 'active' : 'inactive',
                    'is_active' => $attributes['is_active'],
                ]
            );

            return [$package->name => $package];
        });

        foreach ([
            [
                'package_name' => 'Digital Set',
                'name' => '15.6 Inch Magic Mirror Photobooth',
                'category' => null,
                'serial_number' => null,
                'description' => 'A refined 15.6” touch-screen photobooth crafted for luxury events. Its sleek metal design, pro-grade camera compatibility, and seamless instant-share experience create polished, high-end photos every time. Compact, elegant, and fully brandable, it blends effortlessly into premium venues while delivering the elevated, modern experience MemoShot is known for.',
                'daily_rate' => 0,
                'maintenance_status' => 'ready',
                'last_maintained_at' => null,
                'maintenance_notes' => null,
                'photo_path' => 'catalog/ihEPCHLG7QbxkhOcQRa32LofdWcrCECUfyF32LLC.png',
            ],
            [
                'package_name' => 'VIP Ultimate',
                'name' => '32 Inch Magic Mirror Touch Screen Photobooth',
                'category' => null,
                'serial_number' => null,
                'description' => "A full-length interactive mirror that feels more like a statement piece than a photobooth. Its sleek reflective design, vibrant touch-screen animations, and studio-grade lighting create polished, editorial-quality photos with effortless ease. Guests pose, sign, and personalise their images directly on the mirror before receiving beautifully finished prints or instant digital copies.\r\nRefined, modern, and built for premium events - this Mirror Booth elevates the room the moment it lights up.",
                'daily_rate' => 0,
                'maintenance_status' => 'ready',
                'last_maintained_at' => null,
                'maintenance_notes' => null,
                'photo_path' => 'catalog/YVysbGBeG2Px383XieHWvP9kAfeEMulDGEtzXQrH.png',
            ],
            [
                'package_name' => 'VIP Ultimate',
                'name' => '360 Video Booth',
                'category' => 'Photobooth',
                'serial_number' => null,
                'description' => "The MemoShot 360 turns every moment into a cinematic highlight. Guests step onto a sleek, low-profile platform while a smooth motorized arm rotates around them, capturing stunning HD video from every angle. With slow-motion, boomerang, and fully branded overlays, every clip becomes instantly shareable and impossible to forget.\r\nBuilt from durable steel with a stable, anti-slip platform, the MemoShot 360 supports individuals, couples, and small groups with confidence. The adjustable rotating arm accommodates phones, GoPros, and DSLR setups, while integrated LED lighting elevates the energy and enhances every shot.\r\nDespite its premium build, the booth remains lightweight and portable - folding down for fast setup at weddings, corporate activations, school formals, and luxury celebrations. Paired with MemoShot's signature templates and instant sharing station, the 360 booth delivers high-impact content that guests love and brands rely on.\r\nPerfect for:\r\n✨ Weddings & engagements\r\n✨ Corporate events & brand activations\r\n✨ Birthdays, formals & private parties",
                'daily_rate' => 0,
                'maintenance_status' => 'ready',
                'last_maintained_at' => null,
                'maintenance_notes' => null,
                'photo_path' => 'catalog/Up5gs4byaTbIeUrUY1W4GRLo0ks6mLgDqvyS4rgR.png',
            ],
            [
                'package_name' => 'Glam Booth',
                'name' => 'Canon Camera Rig',
                'category' => 'Camera',
                'serial_number' => 'MS-CAM-001',
                'description' => 'Primary DSLR camera rig for booth sessions.',
                'daily_rate' => 180,
                'maintenance_status' => 'ready',
                'last_maintained_at' => '2026-04-01',
                'maintenance_notes' => 'Cleaned sensor and checked tether connection.',
                'photo_path' => null,
            ],
            [
                'package_name' => 'Glam Booth',
                'name' => 'DNP Event Printer',
                'category' => 'Printer',
                'serial_number' => 'MS-PRINT-001',
                'description' => 'Fast dye-sublimation printer for instant photo strips.',
                'daily_rate' => 140,
                'maintenance_status' => 'ready',
                'last_maintained_at' => '2026-04-01',
                'maintenance_notes' => 'Paper stock replaced and rollers checked.',
                'photo_path' => null,
            ],
            [
                'package_name' => 'Mirror Booth Luxe',
                'name' => 'Mirror Booth Shell',
                'category' => 'Booth',
                'serial_number' => 'MS-MIRROR-001',
                'description' => 'Interactive mirror booth enclosure with touch display.',
                'daily_rate' => 260,
                'maintenance_status' => 'ready',
                'last_maintained_at' => '2026-04-01',
                'maintenance_notes' => 'Touchscreen calibrated and lighting tested.',
                'photo_path' => null,
            ],
            [
                'package_name' => 'Mirror Booth Luxe',
                'name' => 'Champagne Sequin Backdrop',
                'category' => 'Backdrop',
                'serial_number' => 'MS-BACKDROP-001',
                'description' => 'Premium sequin backdrop for high-end events.',
                'daily_rate' => 95,
                'maintenance_status' => 'ready',
                'last_maintained_at' => '2026-04-01',
                'maintenance_notes' => 'Stored pressed and event-ready.',
                'photo_path' => null,
            ],
        ] as $attributes) {
            Equipment::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $attributes['name'],
                ],
                [
                    'package_id' => $packages[$attributes['package_name']]->id,
                    'category' => $attributes['category'],
                    'serial_number' => $attributes['serial_number'],
                    'description' => $attributes['description'],
                    'daily_rate' => $attributes['daily_rate'],
                    'maintenance_status_id' => $equipmentStatusIds[$attributes['maintenance_status']] ?? null,
                    'maintenance_status' => $attributes['maintenance_status'],
                    'last_maintained_at' => $attributes['last_maintained_at'],
                    'maintenance_notes' => $attributes['maintenance_notes'],
                    'photo_path' => $attributes['photo_path']
                        ?? 'catalog/generated/add-on-'.Str::slug(($attributes['sku'] ?? $attributes['serial_number']).'-'.$attributes['name']).'.png',
                ]
            );
        }

        $addonCategoryFor = function (array $attributes): string {
            $name = strtolower($attributes['name']);
            $sku = $attributes['sku'];

            return match (true) {
                str_contains($name, 'backdrop'), str_contains($name, 'flower wall') => 'Backdrops',
                str_contains($name, 'attendant'), str_contains($name, 'celebrant'), str_contains($name, 'dj'), str_contains($name, 'singer'), str_contains($name, 'emcee') => 'Event Staff',
                str_contains($name, 'print'), str_contains($name, 'photo frame'), str_contains($name, 'magnetic'), str_contains($name, 'scrapbook') => 'Prints and Keepsakes',
                str_contains($name, 'travel'), str_contains($name, 'delivery') => 'Travel and Logistics',
                str_contains($name, 'qr'), str_contains($name, 'gallery'), str_contains($name, 'usb') => 'Digital Delivery',
                str_contains($name, 'sparkular'), str_contains($name, 'clouds'), str_contains($name, 'red carpet'), str_contains($name, 'bollards'), str_contains($name, 'props'), str_contains($name, 'styling') => 'Event Styling',
                $sku === 'A1' || str_contains($name, 'guest book') || str_contains($name, 'neon') => 'Guest Experience',
                default => 'Other Add-ons',
            };
        };

        $inventoryItems = collect([
            ['name' => 'Audio Guestbook White Vintage Style', 'category' => 'add-on', 'sku' => 'A1', 'description' => 'Audio guestbook white vintage style that records guests heartfelt greetings message', 'quantity' => 1, 'unit_price' => 0, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => 'catalog/o4MEAsk6iBxY343sFYtkM7nbsk1HHya6XimpdlRy.png'],
            ['name' => 'Plain white backdrop', 'category' => 'add-on', 'sku' => 'A2', 'description' => 'Plain white backdrop', 'quantity' => 1, 'unit_price' => 50, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Gold backdrop', 'category' => 'add-on', 'sku' => 'A4', 'description' => 'Gold backdrop vinyl', 'quantity' => 1, 'unit_price' => 85, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'White Flower Wall', 'category' => 'add-on', 'sku' => 'A21', 'description' => '3d White flower wall 3m x 2m ideal for weddings, anniversaries and other special events', 'quantity' => 1, 'unit_price' => 300, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => 'catalog/z4vdjdUsgsdvE2240UUC1P4JmdFeVXwWFAeUS7rp.png'],
            ['name' => 'Attendants', 'category' => 'add-on', 'sku' => 'A22', 'description' => 'Professional, friendly and trained attendants to assist the guesrts in using the photobooth and to ensure smooth operations during the event.', 'quantity' => 1, 'unit_price' => 60, 'duration' => '1 hour', 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => 'catalog/Pib6mn0rtrnqWP9sguyfiDKjrq53MqOoXnIIMrSi.jpg'],
            ['name' => 'Props', 'category' => 'add-on', 'sku' => 'A26', 'description' => 'Fancy props appropriate for the event type', 'quantity' => 1, 'unit_price' => 50, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Additional Hour', 'category' => 'add-on', 'sku' => 'A38', 'description' => 'Additional hour when guests requested an extension of the photobooth services', 'quantity' => 1, 'unit_price' => 150, 'duration' => '1 hour', 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Scrapbook Memory Album', 'category' => 'add-on', 'sku' => 'A40', 'description' => 'Scrapbook Memory Album', 'quantity' => 1, 'unit_price' => 50, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Photo Strip Magnetic Holder', 'category' => 'add-on', 'sku' => 'A41', 'description' => 'Photo Strip Magnetic Holder', 'quantity' => 1, 'unit_price' => 250, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Acrylic Photo Frame for 2x6', 'category' => 'add-on', 'sku' => 'A42', 'description' => '100 pieces of acrylic photo frame for 2x6 for photoboooth prints', 'quantity' => 1, 'unit_price' => 20, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => 'catalog/04Fp7qb93hR8EsxmcQMXGMZnEyAeqOk2LxoHkJzJ.png'],
            ['name' => 'Event Styling & Decors', 'category' => 'add-on', 'sku' => 'A46', 'description' => 'Event Styling & Designs for birthdays, corporate events, weddings, graduations and all other special events.', 'quantity' => 1, 'unit_price' => 1200, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Celebrant', 'category' => 'add-on', 'sku' => 'A47', 'description' => 'In Australia, celebrants or civil celebrants are people who conduct formal ceremonies in the community, particularly weddings - which represent the main ceremony of legal import conducted by celebrants - and for this reason are often referred to as marriage celebrants.', 'quantity' => 1, 'unit_price' => 800, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'DJ', 'category' => 'add-on', 'sku' => 'A48', 'description' => "Your MemoShot DJ is more than someone who plays music - they're the energy curator, the flow-keeper, and the person who turns your celebration into a seamless, unforgettable experience. With a professional setup, smooth transitions, and a deep understanding of crowd dynamics, your DJ ensures every moment feels intentional, elevated, and full of life.", 'quantity' => 1, 'unit_price' => 200, 'duration' => '1 hour', 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Live Singer', 'category' => 'add-on', 'sku' => 'A50', 'description' => 'Add a touch of luxury and live artistry to your celebration.', 'quantity' => 1, 'unit_price' => 500, 'duration' => '3 hours', 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Dancing on Clouds Special Effect', 'category' => 'add-on', 'sku' => 'A36', 'description' => 'A magical first dance moment that feels straight out of a fairytale', 'quantity' => 1, 'unit_price' => 300, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Sparkular Display', 'category' => 'add-on', 'sku' => 'A37', 'description' => "Transform your celebration with MemoShot's Sparkular effects - a breathtaking, cold-spark fountain that adds drama, elegance, and pure \"wow\" to your most important moments. Safe for indoor venues and visually stunning in photos and video, Sparkular brings that luxury-event atmosphere without the heat, smoke, or risk of traditional pyrotechnics.", 'quantity' => 1, 'unit_price' => 350, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Gold & Silver Bokeh Backdrop', 'category' => 'add-on', 'sku' => 'A9', 'description' => 'Gold & Silver Bokeh Backdrop', 'quantity' => 1, 'unit_price' => 85, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Green Leaves Backdrop', 'category' => 'add-on', 'sku' => 'A20', 'description' => 'Green leaves backdrop', 'quantity' => 1, 'unit_price' => 85, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => 'catalog/rqxSqgDey7Czh5wKfMVlCbxNyLVXb1kDdiweovS6.jpg'],
            ['name' => 'Wooden Style Backdrop', 'category' => 'add-on', 'sku' => 'A12', 'description' => 'Wooden Style Backdrop with lights', 'quantity' => 1, 'unit_price' => 85, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => 'catalog/PEEmHDEF7CzlMk04VxfpI6lxON1G7ov4wYdMezOA.jpg'],
            ['name' => 'Green Leaves with White Flower Hanging Backdrop', 'category' => 'add-on', 'sku' => 'A17', 'description' => 'Green Leaves with White Flower Hanging Backdrop', 'quantity' => 1, 'unit_price' => 85, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => 'catalog/L7TeNjpteyX92LQpXVtsakYeLFrdmnEglcAMDLZd.jpg'],
            ['name' => 'MC | Emcee', 'category' => 'add-on', 'sku' => 'A49', 'description' => 'Master of ceremonies at your event', 'quantity' => 1, 'unit_price' => 500, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Photography', 'category' => 'add-on', 'sku' => 'A43', 'description' => 'Photography for weddings, birthdays, etc.', 'quantity' => 1, 'unit_price' => 800, 'duration' => '4 hours', 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Videography', 'category' => 'add-on', 'sku' => 'A44', 'description' => 'Videography', 'quantity' => 1, 'unit_price' => 800, 'duration' => '4 hours', 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Unlimited Prints', 'category' => 'add-on', 'sku' => 'A23', 'description' => 'Unlimited Prints', 'quantity' => 1, 'unit_price' => 100, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Unlimited Videos for 360 Video  Booth', 'category' => 'add-on', 'sku' => 'A24', 'description' => 'Unlimited Videos for 360 Video booth', 'quantity' => 1, 'unit_price' => 50, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'USB', 'category' => 'add-on', 'sku' => 'A33', 'description' => 'A USB containing all event photos/videos is/are included, with Australia Post delivery covered at no extra cost.', 'quantity' => 1, 'unit_price' => 20, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Bollards', 'category' => 'add-on', 'sku' => 'A34', 'description' => 'Bollards/ stanchion 6 pieces with red rope', 'quantity' => 1, 'unit_price' => 50, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Red Carpet', 'category' => 'add-on', 'sku' => 'A35', 'description' => 'Make every guest feel like a star with our signature MemoShot red carpet - perfect for weddings, galas, formals, and premium celebrations', 'quantity' => 1, 'unit_price' => 30, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Delivery Setup & Removal', 'category' => 'add-on', 'sku' => 'A51', 'description' => 'Delivery Setup & Removal', 'quantity' => 1, 'unit_price' => 0, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Travel Fees Free', 'category' => 'add-on', 'sku' => 'A31', 'description' => 'Travel fees free for the first 40 kms from Yarrabilba main point', 'quantity' => 1, 'unit_price' => 0, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Travel Fees', 'category' => 'add-on', 'sku' => 'A32', 'description' => 'Travel fees that exceeded 40 kms from Yarrabilba point', 'quantity' => 1, 'unit_price' => 2.25, 'duration' => '1 km', 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'QR for Downloads', 'category' => 'add-on', 'sku' => 'A52', 'description' => 'QR for Downloads', 'quantity' => 1, 'unit_price' => 0, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Online Gallery', 'category' => 'add-on', 'sku' => 'A53', 'description' => 'Online Gallery after the event', 'quantity' => 1, 'unit_price' => 0, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Selection of Standard Backdrop', 'category' => 'add-on', 'sku' => 'A54', 'description' => 'Selection of Standard Backdrop in plain colors or vinyl or fabric type of backdrop', 'quantity' => 1, 'unit_price' => 0, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Selection of Any Backdrop', 'category' => 'add-on', 'sku' => 'A55', 'description' => "Selection of any backdrop as below:\r\n- plain colors or\r\n- vinyl or\r\n- fabric or\r\n- flower wall", 'quantity' => 1, 'unit_price' => 0, 'duration' => null, 'maintenance_status' => 'ready', 'last_maintained_at' => null, 'maintenance_notes' => null, 'photo_path' => null],
            ['name' => 'Audio Guest Book', 'category' => 'add-on', 'sku' => 'MS-ADD-001', 'description' => 'Vintage phone guest book for recorded voice messages.', 'quantity' => 1, 'unit_price' => 149, 'duration' => 'Full event', 'maintenance_status' => 'ready', 'last_maintained_at' => '2026-04-01', 'maintenance_notes' => 'Battery charged and greeting reset.', 'photo_path' => null],
            ['name' => 'Custom Neon Sign', 'category' => 'add-on', 'sku' => 'MS-ADD-002', 'description' => 'Statement neon signage for booth styling.', 'quantity' => 2, 'unit_price' => 89, 'duration' => 'Full event', 'maintenance_status' => 'ready', 'last_maintained_at' => '2026-04-01', 'maintenance_notes' => 'Transformer and mounting kit packed.', 'photo_path' => null],
            ['name' => 'Extra Print Pack', 'category' => 'add-on', 'sku' => 'MS-ADD-003', 'description' => 'Extra duplicate prints for guests throughout the event.', 'quantity' => 10, 'unit_price' => 59, 'duration' => 'Per event', 'maintenance_status' => 'ready', 'last_maintained_at' => '2026-04-01', 'maintenance_notes' => 'Stored with printer consumables.', 'photo_path' => null],
        ])->mapWithKeys(function (array $attributes) use ($tenant, $addonCategoryFor): array {
            $categoryName = $attributes['addon_category'] ?? $addonCategoryFor($attributes);
            $categoryId = InventoryItemCategory::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => $categoryName,
            ], [
                'sort_order' => ($tenant->inventoryItemCategories()->max('sort_order') ?? 0) + 1,
            ])->id;

            $item = InventoryItem::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'sku' => $attributes['sku'],
                ],
                [
                    'name' => $attributes['name'],
                    'category' => $attributes['category'],
                    'type' => $attributes['type'] ?? 'Items',
                    'inventory_item_category_id' => $categoryId,
                    'addon_category' => $categoryName,
                    'description' => $attributes['description'],
                    'quantity' => $attributes['quantity'],
                    'unit_price' => $attributes['unit_price'],
                    'duration' => $attributes['duration'],
                    'maintenance_status' => $attributes['maintenance_status'],
                    'last_maintained_at' => $attributes['last_maintained_at'],
                    'maintenance_notes' => $attributes['maintenance_notes'],
                    'photo_path' => $attributes['photo_path'],
                ]
            );

            return [$item->sku => $item];
        });

        foreach ([
            ['package_name' => 'Digital Set', 'hours' => 2.00, 'price' => 650],
            ['package_name' => 'Digital Set', 'hours' => 3.00, 'price' => 799],
            ['package_name' => 'Digital Set', 'hours' => 4.00, 'price' => 850],
            ['package_name' => 'Digital Set', 'hours' => 5.00, 'price' => 940],
            ['package_name' => 'Digital Set', 'hours' => 6.00, 'price' => 990],
            ['package_name' => 'Deluxe Set', 'hours' => 2.00, 'price' => 880],
            ['package_name' => 'Deluxe Set', 'hours' => 3.00, 'price' => 910],
            ['package_name' => 'Deluxe Set', 'hours' => 4.00, 'price' => 1180],
            ['package_name' => 'Deluxe Set', 'hours' => 5.00, 'price' => 1330],
            ['package_name' => 'Deluxe Set', 'hours' => 6.00, 'price' => 1480],
            ['package_name' => 'Motion Set', 'hours' => 2.00, 'price' => 750],
            ['package_name' => 'Motion Set', 'hours' => 3.00, 'price' => 800],
            ['package_name' => 'Motion Set', 'hours' => 4.00, 'price' => 900],
            ['package_name' => 'Motion Set', 'hours' => 5.00, 'price' => 1000],
            ['package_name' => 'Motion Set', 'hours' => 6.00, 'price' => 1100],
            ['package_name' => 'VIP Ultimate', 'hours' => 2.00, 'price' => 1100],
            ['package_name' => 'VIP Ultimate', 'hours' => 3.00, 'price' => 1300],
            ['package_name' => 'VIP Ultimate', 'hours' => 4.00, 'price' => 1500],
            ['package_name' => 'VIP Ultimate', 'hours' => 5.00, 'price' => 1700],
            ['package_name' => 'VIP Ultimate', 'hours' => 6.00, 'price' => 1900],
            ['package_name' => 'Glam Booth', 'hours' => 3.00, 'price' => 1099],
            ['package_name' => 'Glam Booth', 'hours' => 4.00, 'price' => 1299],
            ['package_name' => 'Mirror Booth Luxe', 'hours' => 3.00, 'price' => 1599],
            ['package_name' => 'Mirror Booth Luxe', 'hours' => 4.00, 'price' => 1899],
        ] as $attributes) {
            PackageHourlyPrice::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'package_id' => $packages[$attributes['package_name']]->id,
                    'hours' => $attributes['hours'],
                ],
                [
                    'price' => $attributes['price'],
                ]
            );
        }

        $packageAddOns = [
            'Digital Set' => ['A22', 'A26', 'A40', 'A34', 'A35', 'A51', 'A31', 'A52', 'A53', 'A54'],
            'Deluxe Set' => ['A1', 'A22', 'A26', 'A40', 'A12', 'A23', 'A34', 'A35', 'A51', 'A31', 'A52', 'A53', 'A55'],
            'Motion Set' => ['A24', 'A34', 'A35', 'A51', 'A31', 'A52', 'A53', 'A55'],
            'VIP Ultimate' => ['A1', 'A22', 'A26', 'A40', 'A42', 'A23', 'A24', 'A34', 'A35', 'A51', 'A31', 'A52', 'A53', 'A55'],
            'Glam Booth' => ['MS-ADD-001', 'MS-ADD-003'],
            'Mirror Booth Luxe' => ['MS-ADD-001', 'MS-ADD-002', 'MS-ADD-003'],
        ];

        foreach ($packageAddOns as $packageName => $skus) {
            $packages[$packageName]->addOns()->sync(
                collect($skus)->map(fn (string $sku): int => $inventoryItems[$sku]->id)->all()
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function templateSeedData(): array
    {
        return [
            [
                'name' => 'Monthly Promo',
                'subject' => 'New MemoShot packages for your next celebration',
                'preheader' => 'Fresh photobooth ideas, styling upgrades, and event extras.',
                'headline' => 'Make the next celebration feel unforgettable',
                'html_body' => '<p>Hello {{ first_name }},</p><p>We have refreshed our photobooth packages with premium backdrops, instant sharing, and event-ready add-ons.</p><p>Reply to this email if you would like us to recommend a setup for your date.</p>',
                'button_text' => 'View packages',
                'button_url' => 'https://memoshot.com/packages',
            ],
            [
                'name' => 'Lead Follow Up',
                'subject' => 'Still planning your photobooth experience?',
                'preheader' => 'Here are a few easy options for your upcoming event.',
                'headline' => 'Let us help shape the booth experience',
                'html_body' => '<p>Hi {{ first_name }},</p><p>Thanks for checking out MemoShot. We can tailor a booth setup around your venue, guest count, and celebration style.</p><p>Send us your event date and we can prepare a quick recommendation.</p>',
                'button_text' => 'Request recommendation',
                'button_url' => 'https://memoshot.com/contact',
            ],
        ];
    }
}
