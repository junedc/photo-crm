<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('daily_rate', 10, 2);
            $table->string('maintenance_status')->default('ready');
            $table->unsignedBigInteger('maintenance_status_id')->nullable();
            $table->date('last_maintained_at')->nullable();
            $table->text('maintenance_notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('maintenance_status_id', 'equipment_maintenance_status_id_foreign')->references('id')->on('workspace_statuses')->nullOnDelete();
            $table->foreign('package_id', 'equipment_package_id_foreign')->references('id')->on('packages')->nullOnDelete();
            $table->foreign('tenant_id', 'equipment_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
