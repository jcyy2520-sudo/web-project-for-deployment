<?php

namespace App\Http\Controllers;

use App\Models\DatabaseBackup;
use App\Services\BackupService;
use App\Jobs\RestoreDatabaseBackup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    /**
     * Get backup statistics and list
     */
    public function index(Request $request): JsonResponse
    {
        $backupService = app(BackupService::class);

        $backups = DatabaseBackup::orderBy('completed_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'statistics' => $backupService->getStatistics(),
            'backups' => $backups,
        ]);
    }

    /**
     * Create manual backup
     */
    public function create(): JsonResponse
    {
        $backupService = app(BackupService::class);
        $backup = $backupService->backup('manual', auth()->id());

        if (!$backup) {
            return response()->json([
                'message' => 'Backup creation failed',
            ], 500);
        }

        return response()->json([
            'message' => 'Backup created successfully',
            'backup' => $backup,
        ], 201);
    }

    /**
     * Get specific backup
     */
    public function show($id): JsonResponse
    {
        $backup = DatabaseBackup::findOrFail($id);

        return response()->json([
            'backup' => $backup,
            'formatted_size' => $backup->formatted_size,
            'duration' => $backup->duration,
            'file_exists' => $backup->fileExists(),
        ]);
    }

    /**
     * Restore from backup - NOW ASYNC VIA QUEUE JOB
     * Prevents hanging on large backups and improves responsiveness
     */
    public function restore($id, Request $request): JsonResponse
    {
        $backup = DatabaseBackup::findOrFail($id);

        // Require confirmation
        if ($request->get('confirmed') !== true) {
            return response()->json([
                'message' => 'Confirmation required. Set confirmed=true to restore.',
            ], 400);
        }

        // Check if backup file exists
        if (!$backup->fileExists()) {
            return response()->json([
                'message' => 'Backup file not found',
            ], 404);
        }

        // Dispatch the restore job to queue
        RestoreDatabaseBackup::dispatch($backup, auth()->id());

        return response()->json([
            'message' => 'Database restore job queued. Restore is processing in the background.',
            'backup' => $backup,
            'status' => 'queued',
            'note' => 'Check backup status with GET /api/admin/backups/{id} to monitor progress',
        ], 202); // 202 Accepted - operation has been accepted for processing
    }

    /**
     * Delete backup
     */
    public function delete($id): JsonResponse
    {
        $backup = DatabaseBackup::findOrFail($id);
        $backup->deleteFile();
        $backup->delete();

        return response()->json([
            'message' => 'Backup deleted',
        ]);
    }

    /**
     * Cleanup old backups
     */
    public function cleanup(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $backupService = app(BackupService::class);
        $deleted = $backupService->cleanupOldBackups($days);

        return response()->json([
            'message' => "Deleted {$deleted} backups older than {$days} days",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Get backup statistics
     */
    public function statistics(): JsonResponse
    {
        $backupService = app(BackupService::class);

        return response()->json($backupService->getStatistics());
    }
}
