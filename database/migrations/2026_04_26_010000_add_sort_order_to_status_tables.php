<?php

use App\Support\TenantStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(1)->after('name');
        });

        Schema::table('workspace_statuses', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(1)->after('name');
        });

        $this->backfillTaskStatusSortOrders();
        $this->backfillWorkspaceStatusSortOrders();
    }

    public function down(): void
    {
        Schema::table('task_statuses', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('workspace_statuses', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }

    private function backfillTaskStatusSortOrders(): void
    {
        $preferredNames = array_values(array_unique(['new', ...TenantStatuses::defaults(TenantStatuses::SCOPE_TASK)]));

        DB::table('task_statuses')
            ->select('tenant_id')
            ->distinct()
            ->pluck('tenant_id')
            ->each(function ($tenantId) use ($preferredNames): void {
                $records = collect(DB::table('task_statuses')
                    ->where('tenant_id', $tenantId)
                    ->orderBy('id')
                    ->get(['id', 'name']));

                $this->applySortOrderUpdates('task_statuses', $this->orderedRecords($records, $preferredNames));
            });
    }

    private function backfillWorkspaceStatusSortOrders(): void
    {
        DB::table('workspace_statuses')
            ->select('tenant_id', 'scope')
            ->distinct()
            ->orderBy('tenant_id')
            ->orderBy('scope')
            ->get()
            ->each(function ($row): void {
                $preferredNames = TenantStatuses::defaults((string) $row->scope);
                $records = collect(DB::table('workspace_statuses')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('scope', $row->scope)
                    ->orderBy('id')
                    ->get(['id', 'name']));

                $this->applySortOrderUpdates('workspace_statuses', $this->orderedRecords($records, $preferredNames));
            });
    }

    private function orderedRecords(Collection $records, array $preferredNames): Collection
    {
        $orderedIds = [];
        $ordered = collect();

        foreach ($preferredNames as $name) {
            $match = $records->first(fn ($record) => $this->normalizeName($record->name) === $this->normalizeName($name));

            if ($match && ! in_array($match->id, $orderedIds, true)) {
                $ordered->push($match);
                $orderedIds[] = $match->id;
            }
        }

        $remaining = $records
            ->filter(fn ($record) => ! in_array($record->id, $orderedIds, true))
            ->sortBy(fn ($record) => $this->normalizeName($record->name))
            ->values();

        return $ordered->concat($remaining)->values();
    }

    private function applySortOrderUpdates(string $table, Collection $records): void
    {
        $records->values()->each(function ($record, int $index) use ($table): void {
            DB::table($table)
                ->where('id', $record->id)
                ->update([
                    'sort_order' => $index + 1,
                ]);
        });
    }

    private function normalizeName(?string $name): string
    {
        return str_replace([' ', '-'], '_', strtolower(trim((string) $name)));
    }
};
