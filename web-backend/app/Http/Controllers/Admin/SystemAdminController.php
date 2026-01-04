<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Appointment;
use App\Models\DatabaseBackup;
use App\Models\FrontendErrorLog;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

/**
 * System Administrator Dashboard Controller
 * 
 * Provides comprehensive system administration capabilities including:
 * - System health monitoring
 * - User/role management
 * - Security monitoring
 * - Backup management
 * - Performance metrics
 * - Configuration management
 */
class SystemAdminController extends Controller
{
    /**
     * Get comprehensive system dashboard data
     */
    public function dashboard(): JsonResponse
    {
        $this->logAdminAction('view_dashboard', 'system', null, 'Viewed system dashboard');

        return response()->json([
            'system_health' => $this->getSystemHealth(),
            'security_status' => $this->getSecurityStatus(),
            'user_statistics' => $this->getUserStatistics(),
            'backup_status' => $this->getBackupStatus(),
            'recent_activity' => $this->getRecentActivity(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'configuration_warnings' => $this->getConfigurationWarnings(),
        ]);
    }

    /**
     * Get system health status
     */
    public function health(): JsonResponse
    {
        return response()->json($this->getSystemHealth());
    }

    /**
     * Get detailed system health checks
     */
    private function getSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => now()->toISOString(),
        ];

        // Database connection check
        try {
            DB::connection()->getPdo();
            $dbLatency = $this->measureDbLatency();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'latency_ms' => $dbLatency,
                'name' => DB::connection()->getDatabaseName(),
            ];
        } catch (\Exception $e) {
            $health['status'] = 'critical';
            $health['checks']['database'] = [
                'status' => 'critical',
                'error' => 'Database connection failed',
            ];
        }

        // Cache connection check
        try {
            Cache::put('health_check', 'ok', 10);
            $cacheOk = Cache::get('health_check') === 'ok';
            $health['checks']['cache'] = [
                'status' => $cacheOk ? 'healthy' : 'degraded',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'degraded',
                'error' => 'Cache not available',
            ];
        }

        // Disk space check
        $diskFree = disk_free_space(storage_path());
        $diskTotal = disk_total_space(storage_path());
        $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
        $health['checks']['disk'] = [
            'status' => $diskUsedPercent > 90 ? 'critical' : ($diskUsedPercent > 80 ? 'warning' : 'healthy'),
            'used_percent' => round($diskUsedPercent, 2),
            'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
            'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
        ];

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
        $health['checks']['memory'] = [
            'status' => $memoryPercent > 90 ? 'warning' : 'healthy',
            'used_mb' => round($memoryUsage / 1024 / 1024, 2),
            'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
            'used_percent' => round($memoryPercent, 2),
        ];

        // Queue check
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $health['checks']['queue'] = [
                'status' => $failedJobs > 100 ? 'warning' : 'healthy',
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ];
        } catch (\Exception $e) {
            $health['checks']['queue'] = [
                'status' => 'unknown',
                'error' => 'Queue tables not available',
            ];
        }

        // Set overall status based on checks
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'critical') {
                $health['status'] = 'critical';
                break;
            }
            if ($check['status'] === 'warning' && $health['status'] !== 'critical') {
                $health['status'] = 'degraded';
            }
        }

        return $health;
    }

    /**
     * Get security status and recent threats
     */
    private function getSecurityStatus(): array
    {
        $last24h = now()->subHours(24);
        $last7d = now()->subDays(7);

        return [
            'failed_logins_24h' => AuditLog::where('action', 'login_failed')
                ->where('created_at', '>=', $last24h)
                ->count(),
            'failed_logins_7d' => AuditLog::where('action', 'login_failed')
                ->where('created_at', '>=', $last7d)
                ->count(),
            'suspicious_ips' => AuditLog::where('action', 'login_failed')
                ->where('created_at', '>=', $last24h)
                ->groupBy('ip_address')
                ->havingRaw('COUNT(*) >= 5')
                ->count(),
            'blocked_ips' => $this->getBlockedIpsCount(),
            'last_security_scan' => Cache::get('last_security_scan', 'Never'),
            'frontend_errors_24h' => FrontendErrorLog::where('created_at', '>=', $last24h)->count(),
            'critical_errors_24h' => FrontendErrorLog::where('created_at', '>=', $last24h)
                ->where('severity', 'critical')
                ->count(),
            'configuration_issues' => count($this->getConfigurationWarnings()),
        ];
    }

    /**
     * Get user statistics
     */
    private function getUserStatistics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'users_by_role' => User::groupBy('role')
                ->selectRaw('role, COUNT(*) as count')
                ->pluck('count', 'role'),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_users_this_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'admins' => User::where('role', 'admin')->select('id', 'username', 'email', 'last_login_at')->get(),
        ];
    }

    /**
     * Get backup status
     */
    private function getBackupStatus(): array
    {
        $latestBackup = DatabaseBackup::orderBy('completed_at', 'desc')->first();
        $backupCount = DatabaseBackup::where('status', 'completed')->count();
        
        $backupScheduleEnabled = true; // From Kernel.php
        $nextBackupTime = now()->setTime(3, 0)->addDay(); // Daily at 3 AM

        return [
            'enabled' => $backupScheduleEnabled,
            'total_backups' => $backupCount,
            'latest_backup' => $latestBackup ? [
                'id' => $latestBackup->id,
                'filename' => $latestBackup->filename,
                'size' => $latestBackup->formatted_size ?? 'Unknown',
                'completed_at' => $latestBackup->completed_at,
                'status' => $latestBackup->status,
            ] : null,
            'backup_health' => $latestBackup && $latestBackup->completed_at->diffInHours(now()) < 48 
                ? 'healthy' 
                : 'warning',
            'next_scheduled' => $nextBackupTime->toISOString(),
            'storage_used' => $this->getBackupStorageUsed(),
        ];
    }

    /**
     * Get recent admin activity
     */
    private function getRecentActivity(): array
    {
        return AuditLog::with('user')
            ->whereNotNull('user_id')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'description' => $log->description,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'username' => $log->user->username,
                        'role' => $log->user->role,
                    ] : null,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at,
                    'status' => $log->status,
                ];
            })
            ->toArray();
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'database_latency_ms' => $this->measureDbLatency(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'average_response_time_ms' => $this->getAverageResponseTime(),
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Get configuration warnings
     */
    private function getConfigurationWarnings(): array
    {
        $warnings = [];

        // Check APP_DEBUG
        if (config('app.debug') === true) {
            $warnings[] = [
                'severity' => 'critical',
                'message' => 'APP_DEBUG is enabled. This exposes sensitive information in production.',
                'fix' => 'Set APP_DEBUG=false in .env file',
            ];
        }

        // Check APP_ENV
        if (config('app.env') === 'local') {
            $warnings[] = [
                'severity' => 'warning',
                'message' => 'APP_ENV is set to "local". Consider changing to "production" for deployment.',
                'fix' => 'Set APP_ENV=production in .env file',
            ];
        }

        // Check session security
        if (!config('session.secure') && config('app.env') === 'production') {
            $warnings[] = [
                'severity' => 'warning',
                'message' => 'Session cookies are not marked as secure.',
                'fix' => 'Set SESSION_SECURE_COOKIES=true in .env file',
            ];
        }

        // Check backup status
        $latestBackup = DatabaseBackup::orderBy('completed_at', 'desc')->first();
        if (!$latestBackup || $latestBackup->completed_at->diffInHours(now()) > 48) {
            $warnings[] = [
                'severity' => 'warning',
                'message' => 'No recent database backup found. Last backup was more than 48 hours ago.',
                'fix' => 'Run php artisan backup:database or check backup schedule',
            ];
        }

        // Check disk space
        $diskFree = disk_free_space(storage_path());
        $diskTotal = disk_total_space(storage_path());
        $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
        if ($diskUsedPercent > 80) {
            $warnings[] = [
                'severity' => $diskUsedPercent > 90 ? 'critical' : 'warning',
                'message' => "Disk space is at {$diskUsedPercent}% capacity.",
                'fix' => 'Free up disk space or increase storage capacity',
            ];
        }

        return $warnings;
    }

    /**
     * User Management - List all users with filtering
     */
    public function listUsers(Request $request): JsonResponse
    {
        $this->logAdminAction('list_users', 'user', null, 'Listed all users');

        // Include soft-deleted users AND all users regardless of is_active status
        // This ensures we see all users including test/seeded users and inactive ones
        $query = User::withTrashed();

        // Only filter by role if explicitly requested
        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        // Only filter by active status if explicitly requested
        if ($request->has('status') && $request->status) {
            if ($request->status === 'active') {
                $query->where('is_active', true)->whereNull('deleted_at');
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'deleted') {
                $query->whereNotNull('deleted_at');
            }
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Add status indicator to each user for better frontend display
        $users->getCollection()->transform(function ($user) {
            $user->status_display = $user->trashed() 
                ? 'deleted' 
                : ($user->is_active ? 'active' : 'inactive');
            return $user;
        });

        return response()->json($users);
    }

    /**
     * Update user role
     */
    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:user,staff,cashier,admin',
        ]);

        $oldRole = $user->role;
        $user->update(['role' => $validated['role']]);

        $this->logAdminAction(
            'update_user_role',
            'user',
            $user->id,
            "Changed role from {$oldRole} to {$validated['role']}",
            ['role' => $oldRole],
            ['role' => $validated['role']]
        );

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(User $user): JsonResponse
    {
        $oldStatus = $user->is_active;
        $user->update(['is_active' => !$user->is_active]);

        $action = $user->is_active ? 'activated' : 'deactivated';
        $this->logAdminAction(
            "user_{$action}",
            'user',
            $user->id,
            "User account {$action}",
            ['is_active' => $oldStatus],
            ['is_active' => $user->is_active]
        );

        return response()->json([
            'message' => "User account {$action} successfully",
            'user' => $user,
        ]);
    }

    /**
     * Run manual backup
     */
    public function runBackup(): JsonResponse
    {
        $this->logAdminAction('manual_backup', 'system', null, 'Initiated manual backup');

        try {
            $backupService = app(BackupService::class);
            $backup = $backupService->backup('manual', auth()->id());

            if ($backup) {
                return response()->json([
                    'message' => 'Backup created successfully',
                    'backup' => $backup,
                ], 201);
            }

            return response()->json([
                'message' => 'Backup creation failed',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Manual backup failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Backup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache(): JsonResponse
    {
        $this->logAdminAction('clear_cache', 'system', null, 'Cleared application cache');

        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');

            return response()->json([
                'message' => 'Cache cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get security audit report
     */
    public function securityAudit(Request $request): JsonResponse
    {
        $this->logAdminAction('view_security_audit', 'security', null, 'Viewed security audit report');

        $startDate = $request->get('start_date', now()->subDays(30));
        $endDate = $request->get('end_date', now());

        return response()->json([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'failed_logins' => AuditLog::where('action', 'login_failed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get(),
            'suspicious_activity' => $this->getSuspiciousActivity($startDate, $endDate),
            'admin_actions' => AuditLog::with('user')
                ->whereHas('user', function ($q) {
                    $q->where('role', 'admin');
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get(),
            'configuration_changes' => AuditLog::where('entity_type', 'settings')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    /**
     * Log admin action to audit log
     */
    private function logAdminAction(
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log admin action: ' . $e->getMessage());
        }
    }

    /**
     * Helper methods
     */
    private function measureDbLatency(): float
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function parseBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }

    private function getBlockedIpsCount(): int
    {
        // This would integrate with your IP blocking mechanism
        return 0;
    }

    private function getBackupStorageUsed(): string
    {
        $backupPath = storage_path('app/backups');
        if (!is_dir($backupPath)) {
            return '0 MB';
        }

        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($backupPath)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return round($size / 1024 / 1024, 2) . ' MB';
    }

    private function getCacheHitRate(): float
    {
        // Would need cache statistics tracking
        return 0.0;
    }

    private function getAverageResponseTime(): float
    {
        // Would need request timing middleware
        return 0.0;
    }

    private function getRequestsPerMinute(): int
    {
        return Cache::get('requests_per_minute', 0);
    }

    private function getSuspiciousActivity($startDate, $endDate): array
    {
        // Get IPs with many failed logins
        $suspiciousIps = AuditLog::where('action', 'login_failed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= 5')
            ->selectRaw('ip_address, COUNT(*) as attempt_count, MAX(created_at) as last_attempt')
            ->get();

        return $suspiciousIps->toArray();
    }
}
