<?php

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignResult;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\SubscriberGroup;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_campaign_workspace(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $template = Template::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Promo Template',
            'subject' => 'April Specials',
            'html_body' => '<p>New package updates are ready.</p>',
        ]);

        Campaign::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'subject' => 'April Specials',
            'body' => '<p>New package updates are ready.</p>',
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/campaigns')
            ->assertOk()
            ->assertSee('data-page="campaigns"', false)
            ->assertSee('April Specials');
    }

    public function test_admin_can_create_template_group_import_contacts_and_send_campaign(): void
    {
        Mail::fake();

        [$tenant, $user] = $this->tenantUser();

        $templateResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns/templates', [
                'name' => 'Monthly Promo',
                'subject' => 'April MemoShot News',
                'preheader' => 'A quick update from MemoShot.',
                'headline' => 'Fresh event ideas',
                'html_body' => '<p>We have new photobooth options ready for your next event.</p>',
                'button_text' => 'Book now',
                'button_url' => 'https://memoshot.test/bookings/create',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Template saved.');

        $groupResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns/groups', [
                'name' => 'VIP Customers',
                'description' => 'Customers who should receive monthly campaigns.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Subscriber group saved.');

        $groupId = $groupResponse->json('record.id');
        $csv = "first_name,last_name,phone,email\nCasey,Customer,0400111222,casey@example.com\n";

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/campaigns/groups/'.$groupId.'/import', [
                'file' => UploadedFile::fake()->createWithContent('subscribers.csv', $csv),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('record.customers_count', 1);

        $templateId = $templateResponse->json('record.id');

        $createResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns', [
                'template_id' => $templateId,
                'subject' => 'April MemoShot News',
                'preheader' => 'A quick update from MemoShot.',
                'headline' => 'Fresh event ideas',
                'body' => '<p>We have new photobooth options ready for your next event.</p>',
                'button_text' => 'Book now',
                'button_url' => 'https://memoshot.test/bookings/create',
                'group_ids' => [$groupId],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Campaign draft created.')
            ->assertJsonPath('record.subject', 'April MemoShot News')
            ->assertJsonPath('record.customers_count', 1);

        $campaignId = $createResponse->json('record.id');

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns/'.$campaignId.'/send', [
                'group_ids' => [$groupId],
            ])
            ->assertOk()
            ->assertJsonPath('record.status', 'sent')
            ->assertJsonPath('record.sent_count', 1);

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail) {
            return $mail->hasTo('casey@example.com')
                && $mail->campaign->subject === 'April MemoShot News'
                && $mail->result !== null;
        });

        $result = CampaignResult::query()->where('campaign_id', $campaignId)->firstOrFail();

        $this->assertDatabaseHas('campaign_results', [
            'id' => $result->id,
            'status' => 'sent',
            'email' => 'casey@example.com',
        ]);

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns/results/'.$result->id.'/bounce')
            ->assertOk()
            ->assertJsonPath('record.bounced_count', 1);

        $this->assertDatabaseHas('campaign_results', [
            'id' => $result->id,
            'status' => 'bounced',
        ]);
    }

    public function test_campaign_open_and_unsubscribe_are_tracked(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $group = SubscriberGroup::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'VIP Customers',
        ]);
        $customer = \App\Models\Customer::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Casey Customer',
            'email' => 'casey@example.com',
            'phone' => '0400111222',
        ]);
        $recipient = CampaignRecipient::query()->create([
            'subscriber_group_id' => $group->id,
            'recipient_type' => $customer->getMorphClass(),
            'recipient_id' => $customer->id,
        ]);
        $campaign = Campaign::query()->create([
            'tenant_id' => $tenant->id,
            'subject' => 'April MemoShot News',
            'body' => '<p>Hello</p>',
        ]);
        $result = CampaignResult::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'email' => $customer->email,
            'name' => $customer->full_name,
            'token' => 'track-token',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other',
        ]);

        $this->get('http://'.$otherTenant->slug.'.memoshot.test/campaigns/open/'.$result->token.'.gif')
            ->assertNotFound();

        $this->assertDatabaseHas('campaign_results', [
            'id' => $result->id,
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get('http://'.$tenant->slug.'.memoshot.test/campaigns/open/'.$result->token.'.gif')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertDatabaseHas('campaign_results', [
            'id' => $result->id,
            'status' => 'opened',
        ]);

        $this->get('http://'.$tenant->slug.'.memoshot.test/campaigns/unsubscribe/'.$result->token)
            ->assertOk()
            ->assertSee('You are unsubscribed');

        $this->assertDatabaseHas('campaign_results', [
            'id' => $result->id,
            'status' => 'unsubscribed',
        ]);
        $this->assertDatabaseMissing('campaign_recipients', [
            'id' => $recipient->id,
        ]);
    }

    public function test_campaign_recipients_only_store_polymorphic_membership(): void
    {
        [$tenant, $user] = $this->tenantUser();

        $groupResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns/groups', [
                'name' => 'Minimal Group',
            ])
            ->assertOk();

        $groupId = $groupResponse->json('record.id');
        $csv = "first_name,last_name,phone,email\nRiley,Minimal,0400999888,riley@example.com\n";

        $this->actingAs($user)
            ->post('http://'.$tenant->slug.'.memoshot.test/campaigns/groups/'.$groupId.'/import', [
                'file' => UploadedFile::fake()->createWithContent('minimal.csv', $csv),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $customer = Customer::query()->where('email', 'riley@example.com')->firstOrFail();

        $this->assertDatabaseHas('campaign_recipients', [
            'subscriber_group_id' => $groupId,
            'recipient_type' => $customer->getMorphClass(),
            'recipient_id' => $customer->id,
        ]);

        $this->assertFalse(Schema::hasColumn('campaign_recipients', 'email'));
        $this->assertFalse(Schema::hasColumn('campaign_recipients', 'name'));
        $this->assertFalse(Schema::hasColumn('campaign_recipients', 'phone'));
        $this->assertFalse(Schema::hasColumn('campaign_recipients', 'customer_id'));
        $this->assertFalse(Schema::hasColumn('campaign_recipients', 'lead_id'));
    }

    public function test_admin_can_add_existing_customers_and_leads_to_subscriber_group(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Avery Customer',
            'email' => 'avery@example.com',
            'phone' => '0400111333',
        ]);
        $lead = Lead::query()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Logan Lead',
            'customer_email' => 'logan@example.com',
            'customer_phone' => '0400444555',
            'status' => 'new',
            'last_activity_at' => now(),
        ]);

        $groupResponse = $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns/groups', [
                'name' => 'CRM Picks',
            ])
            ->assertOk();

        $groupId = $groupResponse->json('record.id');

        $this->actingAs($user)
            ->postJson('http://'.$tenant->slug.'.memoshot.test/campaigns/groups/'.$groupId.'/recipients', [
                'customer_ids' => [$customer->id],
                'lead_ids' => [$lead->id],
            ])
            ->assertOk()
            ->assertJsonPath('record.recipients_count', 2)
            ->assertJsonCount(2, 'record.recipients');

        $this->assertDatabaseHas('campaign_recipients', [
            'subscriber_group_id' => $groupId,
            'recipient_type' => $customer->getMorphClass(),
            'recipient_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('campaign_recipients', [
            'subscriber_group_id' => $groupId,
            'recipient_type' => $lead->getMorphClass(),
            'recipient_id' => $lead->id,
        ]);
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'photoboo',
        ]);

        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant, ['role' => 'owner']);

        return [$tenant, $user];
    }
}
