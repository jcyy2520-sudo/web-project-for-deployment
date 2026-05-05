<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('code', 255)->change();
        });

        Schema::table('password_reset_codes', function (Blueprint $table) {
            $table->string('code', 255)->change();
        });

        $this->hashExistingCodes('verification_codes');
        $this->hashExistingCodes('password_reset_codes');
    }

    public function down(): void
    {
        // Intentionally left as a no-op. Reverting to 6-character storage would break active hashed codes.
    }

    private function hashExistingCodes(string $table): void
    {
        DB::table($table)
            ->select(['id', 'code'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    if ($row->code === null || $this->isHashed($row->code)) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['code' => Hash::make($row->code)]);
                }
            });
    }

    private function isHashed(string $value): bool
    {
        return (($info = password_get_info($value))['algoName'] ?? 'unknown') !== 'unknown';
    }
};