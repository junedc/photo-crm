<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->string('document_type', 50)->default('user_file');
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('booking_id', 'booking_documents_booking_id_foreign')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('tenant_id', 'booking_documents_tenant_id_foreign')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('uploaded_by_user_id', 'booking_documents_uploaded_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_documents');
    }
};
