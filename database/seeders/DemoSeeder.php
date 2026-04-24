<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignResult;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Lead;
use App\Models\Package;
use App\Models\SubscriberGroup;
use App\Models\Template;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'm')->first()
            ?? Tenant::query()->first();

        if ($tenant === null) {
            return;
        }

        for ($batch = 1; $batch <= 5; $batch++) {
            $customers = collect($this->customerSeedData())
                ->map(fn (array $attributes): array => $this->batchCustomerAttributes($attributes, $batch))
                ->mapWithKeys(fn (array $attributes): array => [
                    $attributes['email'] => Customer::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'email' => $attributes['email'],
                        ],
                        [
                            'full_name' => $attributes['full_name'],
                            'phone' => $attributes['phone'],
                            'address' => $attributes['address'],
                        ]
                    ),
                ]);

            $leads = collect($this->leadSeedData())
                ->map(fn (array $attributes, int $index): array => $this->batchLeadAttributes($attributes, $batch, $index + 1))
                ->mapWithKeys(fn (array $attributes): array => [
                    $attributes['customer_email'] => Lead::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'customer_email' => $attributes['customer_email'],
                        ],
                        [
                            'token' => $attributes['token'],
                            'customer_name' => $attributes['customer_name'],
                            'customer_phone' => $attributes['customer_phone'],
                            'event_date' => $attributes['event_date'],
                            'event_location' => $attributes['event_location'],
                            'notes' => $attributes['notes'],
                            'status' => $attributes['status'],
                            'last_activity_at' => now(),
                        ]
                    ),
                ]);

            $templates = $batch === 1
                ? Template::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('name', ['Monthly Promo', 'Lead Follow Up'])
                    ->get()
                    ->keyBy('name')
                : collect($this->templateSeedData())
                    ->map(fn (array $attributes): array => $this->batchTemplateAttributes($attributes, $batch))
                    ->mapWithKeys(fn (array $attributes): array => [
                        $attributes['name'] => Template::query()->updateOrCreate(
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
                        ),
                    ]);

            $groups = collect([
                [
                    'name' => $this->batchName('VIP Customers', $batch),
                    'description' => 'Past customers and warm contacts for monthly promotional campaigns.',
                    'recipients' => [
                        $customers[$this->batchEmail('avery.thompson@example.com', $batch)],
                        $customers[$this->batchEmail('mia.rodriguez@example.com', $batch)],
                        $leads[$this->batchEmail('sophie.williams@example.com', $batch)],
                    ],
                ],
                [
                    'name' => $this->batchName('Wedding Leads', $batch),
                    'description' => 'Leads planning weddings or engagement celebrations.',
                    'recipients' => [
                        $customers[$this->batchEmail('noah.chen@example.com', $batch)],
                        $leads[$this->batchEmail('ethan.brooks@example.com', $batch)],
                        $leads[$this->batchEmail('grace.patel@example.com', $batch)],
                    ],
                ],
            ])->mapWithKeys(function (array $attributes) use ($tenant): array {
                $group = SubscriberGroup::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $attributes['name'],
                    ],
                    [
                        'description' => $attributes['description'],
                    ]
                );

                foreach ($attributes['recipients'] as $recipient) {
                    $this->upsertRecipient($group, $recipient);
                }

                return [$group->name => $group];
            });

            $monthlyPromoTemplate = $templates[$this->batchName('Monthly Promo', $batch)];
            $leadFollowUpTemplate = $templates[$this->batchName('Lead Follow Up', $batch)];

            $promoCampaign = Campaign::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'subject' => $this->batchName('April MemoShot News', $batch),
                ],
                [
                    'template_id' => $monthlyPromoTemplate->id,
                    'preheader' => $monthlyPromoTemplate->preheader,
                    'headline' => $monthlyPromoTemplate->headline,
                    'body' => $monthlyPromoTemplate->html_body,
                    'button_text' => $monthlyPromoTemplate->button_text,
                    'button_url' => $monthlyPromoTemplate->button_url,
                    'status' => 'sent',
                    'sent_count' => 3,
                    'sent_at' => now()->subDays(3 + $batch),
                ]
            );

            $leadCampaign = Campaign::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'subject' => $this->batchName('Wedding booth follow up', $batch),
                ],
                [
                    'template_id' => $leadFollowUpTemplate->id,
                    'preheader' => $leadFollowUpTemplate->preheader,
                    'headline' => $leadFollowUpTemplate->headline,
                    'body' => $leadFollowUpTemplate->html_body,
                    'button_text' => $leadFollowUpTemplate->button_text,
                    'button_url' => $leadFollowUpTemplate->button_url,
                    'status' => 'draft',
                    'sent_count' => 0,
                    'sent_at' => null,
                ]
            );

            $this->seedResults($promoCampaign, $groups[$this->batchName('VIP Customers', $batch)], [
                $this->batchEmail('avery.thompson@example.com', $batch) => 'opened',
                $this->batchEmail('mia.rodriguez@example.com', $batch) => 'sent',
                $this->batchEmail('sophie.williams@example.com', $batch) => 'bounced',
            ]);

            $this->seedResults($leadCampaign, $groups[$this->batchName('Wedding Leads', $batch)], [
                $this->batchEmail('ethan.brooks@example.com', $batch) => 'unsubscribed',
            ]);
        }

        $this->seedBookingsAndInvoices($tenant);
    }

    private function upsertRecipient(SubscriberGroup $group, Model $recipient): CampaignRecipient
    {
        return CampaignRecipient::query()->updateOrCreate(
            [
                'subscriber_group_id' => $group->id,
                'recipient_type' => $recipient->getMorphClass(),
                'recipient_id' => $recipient->getKey(),
            ],
            []
        );
    }

    /**
     * @param  array<string, string>  $statusesByEmail
     */
    private function seedResults(Campaign $campaign, SubscriberGroup $group, array $statusesByEmail): void
    {
        $group->load('recipients.recipient');

        foreach ($group->recipients as $recipient) {
            $email = $this->recipientEmail($recipient);

            if ($email === null || ! array_key_exists($email, $statusesByEmail)) {
                continue;
            }

            $status = $statusesByEmail[$email];
            $sentAt = $campaign->sent_at ?? now()->subDays(2);

            CampaignResult::query()->updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'email' => $email,
                ],
                [
                    'campaign_recipient_id' => $recipient->id,
                    'name' => $this->recipientName($recipient),
                    'token' => 'demo-'.Str::slug($campaign->subject).'-'.md5($email),
                    'status' => $status,
                    'sent_at' => $sentAt,
                    'opened_at' => $status === 'opened' ? $sentAt->copy()->addHours(2) : null,
                    'bounced_at' => $status === 'bounced' ? $sentAt->copy()->addMinutes(15) : null,
                    'unsubscribed_at' => $status === 'unsubscribed' ? $sentAt->copy()->addHours(8) : null,
                ]
            );
        }

    }

    private function recipientEmail(CampaignRecipient $recipient): ?string
    {
        return match (true) {
            $recipient->recipient instanceof Customer => $recipient->recipient->email,
            $recipient->recipient instanceof Lead => $recipient->recipient->customer_email,
            default => null,
        };
    }

    private function recipientName(CampaignRecipient $recipient): ?string
    {
        return match (true) {
            $recipient->recipient instanceof Customer => $recipient->recipient->full_name,
            $recipient->recipient instanceof Lead => $recipient->recipient->customer_name,
            default => null,
        };
    }

    private function seedBookingsAndInvoices(Tenant $tenant): void
    {
        $customers = Customer::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();
        $packages = Package::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['equipment', 'addOns', 'hourlyPrices'])
            ->orderBy('id')
            ->get();
        $fallbackEquipment = Equipment::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();
        $fallbackAddOns = InventoryItem::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        if ($customers->isEmpty() || $packages->isEmpty()) {
            return;
        }

        foreach ($this->bookingSeedData() as $index => $attributes) {
            $sequence = $index + 1;
            $customer = $customers[$index % $customers->count()];
            $package = $packages[$index % $packages->count()];
            $hourlyPrice = $package->hourlyPrices->isNotEmpty()
                ? $package->hourlyPrices[$index % $package->hourlyPrices->count()]
                : null;
            $totalHours = (float) ($hourlyPrice?->hours ?? $attributes['total_hours']);
            $packagePrice = (float) ($hourlyPrice?->price ?? $package->base_price);
            $travelFee = (float) $attributes['travel_fee'];
            $discountAmount = (float) $attributes['discount_amount'];

            $booking = Booking::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'quote_number' => sprintf('QT-DEMO-%04d', $sequence),
                ],
                [
                    'customer_id' => $customer->id,
                    'package_id' => $package->id,
                    'package_price' => number_format($packagePrice, 2, '.', ''),
                    'discount_id' => null,
                    'discount_amount' => number_format($discountAmount, 2, '.', ''),
                    'booking_kind' => $attributes['booking_kind'],
                    'entry_name' => $attributes['entry_name'],
                    'entry_description' => $attributes['entry_description'],
                    'customer_name' => $customer->full_name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'event_type' => $attributes['event_type'],
                    'event_date' => $attributes['event_date'],
                    'start_time' => $attributes['start_time'],
                    'end_time' => $this->endTime($attributes['start_time'], $totalHours),
                    'total_hours' => number_format($totalHours, 2, '.', ''),
                    'event_location' => $attributes['event_location'],
                    'travel_distance_km' => number_format((float) $attributes['travel_distance_km'], 2, '.', ''),
                    'travel_fee' => number_format($travelFee, 2, '.', ''),
                    'notes' => $attributes['notes'],
                    'status' => $attributes['status'],
                    'quote_token' => sprintf('20000000-0000-4000-8000-%012d', $sequence),
                    'customer_response_status' => $attributes['customer_response_status'],
                    'customer_responded_at' => $attributes['customer_responded_at'],
                ]
            );

            $equipmentIds = $package->equipment->pluck('id')
                ->whenEmpty(fn ($collection) => $fallbackEquipment->pluck('id')->take(1))
                ->take(($index % 3) + 1)
                ->values()
                ->all();
            $addOnIds = $package->addOns->pluck('id')
                ->whenEmpty(fn ($collection) => $fallbackAddOns->pluck('id')->take(2))
                ->take(($index % 4) + 1)
                ->values()
                ->all();

            $booking->equipment()->sync($equipmentIds);
            $booking->addOns()->sync($addOnIds);

            $booking->load(['equipment', 'addOns']);
            $totalAmount = $packagePrice
                + (float) $booking->equipment->sum('daily_rate')
                + (float) $booking->addOns->sum('unit_price')
                + $travelFee
                - $discountAmount;

            $invoice = Invoice::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'invoice_number' => sprintf('INV-DEMO-%04d', $sequence),
                    'token' => 'demo-invoice-token-'.str_pad((string) $sequence, 21, '0', STR_PAD_LEFT),
                    'total_amount' => number_format(max($totalAmount, 0), 2, '.', ''),
                    'amount_paid' => number_format($attributes['invoice_status'] === 'paid' ? max($totalAmount, 0) : 0, 2, '.', ''),
                    'status' => $attributes['invoice_status'],
                    'issued_at' => now()->subDays(30 - min($sequence, 29)),
                ]
            );

            $this->seedInstallments($invoice, max($totalAmount, 0), $attributes['invoice_status'], $sequence);
        }
    }

    private function seedInstallments(Invoice $invoice, float $totalAmount, string $invoiceStatus, int $sequence): void
    {
        $invoice->installments()->delete();

        $deposit = round($totalAmount * 0.30, 2);
        $remaining = round($totalAmount - $deposit, 2);
        $firstDueDate = now()->subDays(7)->addDays($sequence)->toDateString();

        foreach ([
            ['sequence' => 1, 'label' => 'Deposit', 'amount' => $deposit],
            ['sequence' => 2, 'label' => 'Final Balance', 'amount' => $remaining],
        ] as $installment) {
            $isPaid = $invoiceStatus === 'paid' || ($invoiceStatus === 'partial' && $installment['sequence'] === 1);

            InvoiceInstallment::query()->create([
                'invoice_id' => $invoice->id,
                'sequence' => $installment['sequence'],
                'label' => $installment['label'],
                'due_date' => Carbon::parse($firstDueDate)->addDays(($installment['sequence'] - 1) * 14)->toDateString(),
                'amount' => number_format($installment['amount'], 2, '.', ''),
                'status' => $isPaid ? 'paid' : 'pending',
                'paid_at' => $isPaid ? now()->subDays(max(1, 20 - $sequence)) : null,
            ]);
        }
    }

    private function endTime(string $startTime, float $hours): string
    {
        return Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes((int) round($hours * 60))
            ->format('H:i');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookingSeedData(): array
    {
        $locations = [
            'Brisbane City Hall',
            'Howard Smith Wharves',
            'The Calile Hotel',
            'Gold Coast Convention Centre',
            'Cloudland Brisbane',
            'The Warehouse Fortitude Valley',
            'Customs House Brisbane',
            'Tamborine Mountain Estate',
            'South Bank Piazza',
            'Sanctuary Cove Chapel',
        ];
        $eventTypes = ['Wedding', 'Birthday', 'Corporate', 'Engagement', 'School Formal', 'Anniversary'];
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $invoiceStatuses = ['issued', 'partial', 'paid', 'issued'];

        return collect(range(1, 25))->map(function (int $sequence) use ($locations, $eventTypes, $statuses, $invoiceStatuses): array {
            $kind = $sequence % 9 === 0 ? 'sponsored' : ($sequence % 7 === 0 ? 'market_stall' : 'customer');

            return [
                'booking_kind' => $kind,
                'entry_name' => $kind === 'customer' ? null : ($kind === 'market_stall' ? "Demo Market Stall {$sequence}" : "Demo Sponsored Entry {$sequence}"),
                'entry_description' => $kind === 'customer' ? null : 'Demo non-customer entry used for booking list testing.',
                'event_type' => $eventTypes[($sequence - 1) % count($eventTypes)],
                'event_date' => now()->addDays($sequence * 3)->toDateString(),
                'start_time' => ['10:00', '12:00', '14:00', '16:00', '18:00'][($sequence - 1) % 5],
                'total_hours' => [2.00, 3.00, 4.00, 5.00][($sequence - 1) % 4],
                'event_location' => $locations[($sequence - 1) % count($locations)],
                'travel_distance_km' => 12 + ($sequence * 3),
                'travel_fee' => ($sequence % 4) * 25,
                'discount_amount' => $sequence % 5 === 0 ? 50 : 0,
                'notes' => "Demo booking {$sequence} for pagination, invoice, and calendar testing.",
                'status' => $statuses[($sequence - 1) % count($statuses)],
                'customer_response_status' => $sequence % 3 === 0 ? 'accepted' : 'pending',
                'customer_responded_at' => $sequence % 3 === 0 ? now()->subDays($sequence) : null,
                'invoice_status' => $invoiceStatuses[($sequence - 1) % count($invoiceStatuses)],
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function customerSeedData(): array
    {
        return [
            [
                'full_name' => 'Avery Thompson',
                'email' => 'avery.thompson@example.com',
                'phone' => '0400 111 222',
                'address' => '14 Bay Street, Brisbane QLD',
            ],
            [
                'full_name' => 'Mia Rodriguez',
                'email' => 'mia.rodriguez@example.com',
                'phone' => '0400 333 444',
                'address' => '82 Garden Lane, Gold Coast QLD',
            ],
            [
                'full_name' => 'Noah Chen',
                'email' => 'noah.chen@example.com',
                'phone' => '0400 555 666',
                'address' => '5 Riverside Avenue, Logan QLD',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function leadSeedData(): array
    {
        return [
            [
                'customer_name' => 'Sophie Williams',
                'customer_email' => 'sophie.williams@example.com',
                'customer_phone' => '0411 222 333',
                'event_date' => now()->addWeeks(5)->toDateString(),
                'event_location' => 'The Warehouse, Fortitude Valley',
                'notes' => 'Interested in mirror booth and glam backdrop.',
                'status' => 'new',
            ],
            [
                'customer_name' => 'Ethan Brooks',
                'customer_email' => 'ethan.brooks@example.com',
                'customer_phone' => '0411 444 555',
                'event_date' => now()->addWeeks(8)->toDateString(),
                'event_location' => 'Garden Estate, Tamborine Mountain',
                'notes' => 'Wedding inquiry. Asked about unlimited prints.',
                'status' => 'quoted',
            ],
            [
                'customer_name' => 'Grace Patel',
                'customer_email' => 'grace.patel@example.com',
                'customer_phone' => '0411 666 777',
                'event_date' => now()->addWeeks(10)->toDateString(),
                'event_location' => 'City Hall Brisbane',
                'notes' => 'Corporate activation lead for 360 booth.',
                'status' => 'campaign',
            ],
        ];
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

    /**
     * @param  array<string, string>  $attributes
     * @return array<string, string>
     */
    private function batchCustomerAttributes(array $attributes, int $batch): array
    {
        return [
            ...$attributes,
            'full_name' => $this->batchName($attributes['full_name'], $batch),
            'email' => $this->batchEmail($attributes['email'], $batch),
            'phone' => $this->batchPhone($attributes['phone'], $batch),
            'address' => $this->batchName($attributes['address'], $batch),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function batchLeadAttributes(array $attributes, int $batch, int $index): array
    {
        return [
            ...$attributes,
            'token' => sprintf('10000000-0000-4000-8000-%012d', (($batch - 1) * 3) + $index),
            'customer_name' => $this->batchName($attributes['customer_name'], $batch),
            'customer_email' => $this->batchEmail($attributes['customer_email'], $batch),
            'customer_phone' => $this->batchPhone($attributes['customer_phone'], $batch),
            'event_location' => $this->batchName($attributes['event_location'], $batch),
        ];
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array<string, string>
     */
    private function batchTemplateAttributes(array $attributes, int $batch): array
    {
        return [
            ...$attributes,
            'name' => $this->batchName($attributes['name'], $batch),
            'subject' => $this->batchName($attributes['subject'], $batch),
        ];
    }

    private function batchName(string $value, int $batch): string
    {
        return $batch === 1 ? $value : "{$value} {$batch}";
    }

    private function batchEmail(string $email, int $batch): string
    {
        if ($batch === 1) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return "{$local}+demo{$batch}@{$domain}";
    }

    private function batchPhone(string $phone, int $batch): string
    {
        return $batch === 1 ? $phone : "{$phone} {$batch}";
    }
}
