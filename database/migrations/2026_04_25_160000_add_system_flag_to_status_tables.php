<?php

use App\Models\TaskStatus;
use App\Models\WorkspaceStatus;
use App\Support\TenantStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_statuses', function (Blueprint $table) {
            $table->boolean('system')->default(false)->after('name');
        });

        Schema::table('task_statuses', function (Blueprint $table) {
            $table->boolean('system')->default(false)->after('name');
        });

        WorkspaceStatus::query()
            ->each(function (WorkspaceStatus $status): void {
                $status->forceFill([
                    'system' => TenantStatuses::isSystemStatus($status->scope, $status->name),
                ])->saveQuietly();
            });

        TaskStatus::query()
            ->each(function (TaskStatus $status): void {
                $status->forceFill([
                    'system' => TenantStatuses::isSystemStatus(TenantStatuses::SCOPE_TASK, $status->name),
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->dropColumn('system');
        });

        Schema::table('workspace_statuses', function (Blueprint $table) {
            $table->dropColumn('system');
        });
    }
};
