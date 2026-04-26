<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telescope_entries_tags', function (Blueprint $table): void {
            $table->char('entry_uuid', 36);
            $table->string('tag');
            $table->primary(['entry_uuid', 'tag']);
            $table->index('tag', 'telescope_entries_tags_tag_index');
            $table->foreign('entry_uuid', 'telescope_entries_tags_entry_uuid_foreign')->references('uuid')->on('telescope_entries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telescope_entries_tags');
    }
};
