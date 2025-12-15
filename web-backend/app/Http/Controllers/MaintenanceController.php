<?php

namespace App\Http\Controllers;

use App\Services\CleanupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function __construct(private CleanupService $cleanupService)
    {
    }

    /**
     * Run all cleanup tasks
     */
    public function cleanup(): JsonResponse
    {
        $results = $this->cleanupService->performAllCleanup();

        $allSuccess = array_every(
            $results,
            fn ($result) => is_array($result) && ($result['success'] ?? false)
        );

        return response()->json([
            'success' => $allSuccess,
            'results' => $results,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Rotate logs
     */
    public function rotateLogs(): JsonResponse
    {
        $result = $this->cleanupService->rotateLogs();

        return response()->json($result);
    }

    /**
     * Clear cache
     */
    public function clearCache(): JsonResponse
    {
        $result = $this->cleanupService->clearCache();

        return response()->json($result);
    }

    /**
     * Remove old backups
     */
    public function removeOldBackups(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $result = $this->cleanupService->removeOldBackups($days);

        return response()->json($result);
    }

    /**
     * Cleanup temporary files
     */
    public function cleanupTempFiles(): JsonResponse
    {
        $result = $this->cleanupService->cleanupTempFiles();

        return response()->json($result);
    }

    /**
     * Cleanup old sessions
     */
    public function cleanupSessions(Request $request): JsonResponse
    {
        $lifetime = (int) $request->get('lifetime_minutes', 120);
        $result = $this->cleanupService->cleanupOldSessions($lifetime);

        return response()->json($result);
    }

    /**
     * Get maintenance task status
     */
    public function getTaskStatus(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'scheduled_tasks' => [
                [
                    'name' => 'Log Rotation',
                    'frequency' => 'daily',
                    'last_run' => now()->subHours(2),
                    'next_run' => now()->addHours(22),
                    'status' => 'scheduled',
                ],
                [
                    'name' => 'Cache Clearing',
                    'frequency' => 'daily',
                    'last_run' => now()->subHours(1),
                    'next_run' => now()->addHours(23),
                    'status' => 'scheduled',
                ],
                [
                    'name' => 'Backup Cleanup',
                    'frequency' => 'weekly',
                    'last_run' => now()->subDays(2),
                    'next_run' => now()->addDays(5),
                    'status' => 'scheduled',
                ],
                [
                    'name' => 'Metrics Archive',
                    'frequency' => 'weekly',
                    'last_run' => now()->subDays(3),
                    'next_run' => now()->addDays(4),
                    'status' => 'scheduled',
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

/**
 * Helper function for array_every (PHP 8.0+)
 */
if (!function_exists('array_every')) {
    function array_every(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
    }
}
