<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_designs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('customer_email');
            $table->string('title')->default('Client design draft');
            $table->json('design_data')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_designs');
    }
};
