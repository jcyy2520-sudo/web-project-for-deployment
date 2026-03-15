<?php

namespace App\Services;

use App\Models\ActionLog;
use Illuminate\Support\Collection;
use Exception;

/**
 * LoggingService: Handles all logging-related business logic
 * Responsibilities: Action logging, audit trails
 */
class LoggingService
{
    /**
     * Log an action
     */
    public function logAction(array $data): ActionLog
    {
        try {
            $log = ActionLog::create([
                'user_id' => $data['user_id'] ?? null,
                'action' => $data['action'],
                'model' => $data['model'] ?? null,
                'model_id' => $data['model_id'] ?? null,
                'changes' => $data['changes'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $log;
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::warning('LoggingService::logAction failed (non-blocking): ' . $e->getMessage());
            return new ActionLog();
        }
    }

    /**
     * Get logs by user
     */
    public function getLogsByUser(int $userId): Collection
    {
        try {
            return ActionLog::where('user_id', $userId)->orderBy('created_at', 'desc')->get();
        } catch (Exception $e) {
            throw new Exception('Failed to fetch logs: ' . $e->getMessage());
        }
    }

    /**
     * Get logs by action
     */
    public function getLogsByAction(string $action): Collection
    {
        try {
            return ActionLog::where('action', $action)->orderBy('created_at', 'desc')->get();
        } catch (Exception $e) {
            throw new Exception('Failed to fetch logs: ' . $e->getMessage());
        }
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs(int $limit = 50): Collection
    {
        try {
            return ActionLog::orderBy('created_at', 'desc')->limit($limit)->get();
        } catch (Exception $e) {
            throw new Exception('Failed to fetch logs: ' . $e->getMessage());
        }
    }
}
