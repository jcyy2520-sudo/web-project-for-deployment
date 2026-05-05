<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_slot_capacities', function (Blueprint $table) {
            $table->string('scope_key', 64)->default('')->after('specific_date');
        });

        $records = DB::table('time_slot_capacities')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $seen = [];
        $idsToDelete = [];

        foreach ($records as $record) {
            $normalizedDay = $record->specific_date ? null : $this->normalizeDayOfWeek($record->day_of_week);
            $scopeKey = $this->makeScopeKey($normalizedDay, $record->specific_date);
            $signature = implode('|', [$scopeKey, $record->start_time, $record->end_time]);

            if (isset($seen[$signature])) {
                $idsToDelete[] = $record->id;
                continue;
            }

            $seen[$signature] = true;

            DB::table('time_slot_capacities')
                ->where('id', $record->id)
                ->update([
                    'day_of_week' => $normalizedDay,
                    'scope_key' => $scopeKey,
                ]);
        }

        if ($idsToDelete !== []) {
            DB::table('time_slot_capacities')
                ->whereIn('id', $idsToDelete)
                ->delete();
        }

        Schema::table('time_slot_capacities', function (Blueprint $table) {
            $table->unique(['scope_key', 'start_time', 'end_time'], 'tsc_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('time_slot_capacities', function (Blueprint $table) {
            $table->dropUnique('tsc_scope_key_unique');
            $table->dropColumn('scope_key');
        });
    }

    private function normalizeDayOfWeek(?string $dayOfWeek): ?string
    {
        return $dayOfWeek !== null && $dayOfWeek !== ''
            ? strtolower($dayOfWeek)
            : null;
    }

    private function makeScopeKey(?string $dayOfWeek, $specificDate = null): string
    {
        if ($specificDate instanceof DateTimeInterface) {
            $specificDate = $specificDate->format('Y-m-d');
        }

        if ($specificDate) {
            return 'date:' . $specificDate;
        }

        return $dayOfWeek ? 'day:' . strtolower($dayOfWeek) : 'all';
    }
};