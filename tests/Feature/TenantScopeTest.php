<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_it_only_returns_records_for_the_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        TenantScopedDocument::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A document',
        ]);

        TenantScopedDocument::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B document',
        ]);

        app(CurrentTenant::class)->set($tenantA);

        $documents = TenantScopedDocument::query()->pluck('name');

        $this->assertSame(['Tenant A document'], $documents->all());
    }

    public function test_it_sets_tenant_id_automatically_on_create(): void
    {
        $tenant = Tenant::factory()->create();

        app(CurrentTenant::class)->set($tenant);

        $document = TenantScopedDocument::query()->create([
            'name' => 'Auto-assigned tenant document',
        ]);

        $this->assertSame($tenant->id, $document->tenant_id);
    }
}

class TenantScopedDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_documents';

    protected $fillable = [
        'tenant_id',
        'name',
    ];
}
