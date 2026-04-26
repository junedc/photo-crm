<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_portal_access_id');
            $table->string('email');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('client_portal_access_id', 'client_portal_codes_client_portal_access_id_foreign')->references('id')->on('client_portal_accesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_codes');
    }
};
