<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops all fake AI decision support tables that were never trained or used.
     */
    public function up(): void
    {
        $tables = [
            'ai_optimization_weights',
            'ai_slot_scores',
            'ai_security_events',
            'ai_drift_logs',
            'ai_circuit_breaker',
            'ai_outcome_logs',
            'ai_prediction_logs',
            'ai_training_runs',
            'ai_feature_snapshots',
            'ai_model_versions',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // These tables contained fake AI scaffolding and are not recreated.
    }
};
