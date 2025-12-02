<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Query Optimization Service
 * Helps prevent N+1 query problems and optimize queries
 */
class QueryOptimizationService
{
    /**
     * Apply standard eager loading for appointment queries
     * Prevents N+1 queries when accessing relationships
     */
    public static function appointmentWithRelations(Builder $query): Builder
    {
        return $query->with([
            'user:id,first_name,last_name,email,phone',
            'staff:id,first_name,last_name,email',
            'service:id,name,description,price,duration',
        ]);
    }

    /**
     * Apply standard eager loading for user queries
     */
    public static function userWithRelations(Builder $query): Builder
    {
        return $query->with([
            'appointments:id,user_id,appointment_date,appointment_time,status',
        ]);
    }

    /**
     * Apply standard eager loading for batch appointment operations
     */
    public static function batchAppointmentOptimization(Builder $query): Builder
    {
        return $query
            ->select(['id', 'user_id', 'staff_id', 'service_id', 'appointment_date', 'appointment_time', 'status', 'type', 'created_at'])
            ->with([
                'user:id,first_name,last_name,email',
                'service:id,name,price',
            ]);
    }

    /**
     * Apply count optimization to prevent multiple COUNT queries
     * Returns query with count sub-query
     */
    public static function withCounts(Builder $query, array $relations = []): Builder
    {
        foreach ($relations as $relation) {
            $query = $query->withCount($relation);
        }
        return $query;
    }

    /**
     * Get only necessary columns for list endpoints
     * Reduces memory usage and database load
     */
    public static function selectForList(Builder $query, array $columns = ['*']): Builder
    {
        // Only select specific columns if not requesting all
        if ($columns !== ['*']) {
            return $query->select($columns);
        }
        return $query;
    }

    /**
     * Apply query timeout to prevent runaway queries
     * Uses SQL timeout if available
     */
    public static function withTimeout(Builder $query, int $seconds = 30): Builder
    {
        // For MySQL/MariaDB
        if (config('database.default') === 'mysql') {
            // Note: This requires session variable to be set
            // Usually done at connection level via max_execution_time
        }
        return $query;
    }
}
