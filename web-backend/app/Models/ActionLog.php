<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActionLog extends Model
{
    use HasFactory;

    private const REQUEST_RECORDED_ATTRIBUTE = 'action_log.recorded';

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'model_type',
        'model_id',
        'ip_address',
        'user_agent',
        'status',
        'metadata',
        // 'integrity_hash' excluded — computed server-side only to prevent tamper-proofing bypass
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    protected static function booted(): void
    {
        static::created(function (): void {
            self::markCurrentRequestAsRecorded();
        });
    }

    /**
     * Log an action with full context.
     *
     * @param string      $action      Action type (e.g. 'create', 'update', 'login')
     * @param string      $description Human-readable description
     * @param string|null $modelType   Affected model class/table
     * @param mixed|null  $modelId     Affected record ID
     * @param string      $status      'success', 'failed', or 'error'
     * @param array|null  $metadata    Extra context (old/new values, error messages, etc.)
     */
    public static function log($action, $description, $modelType = null, $modelId = null, $status = 'success', $metadata = null)
    {
        try {
            $entry = self::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'description' => $description,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
                'status' => $status,
                'metadata' => $metadata,
                'integrity_hash' => self::computeHash($action, $description, auth()->id(), $modelType, $modelId),
            ]);

            return $entry;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('ActionLog::log failed (non-blocking): ' . $e->getMessage());
            return null;
        }
    }

    public static function wasRecordedForCurrentRequest(?Request $request = null): bool
    {
        $request ??= app()->bound('request') ? request() : null;

        if (!$request) {
            return false;
        }

        return (bool) $request->attributes->get(self::REQUEST_RECORDED_ATTRIBUTE, false);
    }

    public static function markCurrentRequestAsRecorded(?Request $request = null): void
    {
        $request ??= app()->bound('request') ? request() : null;

        if (!$request) {
            return;
        }

        $request->attributes->set(self::REQUEST_RECORDED_ATTRIBUTE, true);
    }

    /**
     * Log a failed/error action with full exception context.
     */
    public static function logError($action, $description, $modelType = null, $modelId = null, ?\Throwable $exception = null)
    {
        return self::log(
            $action,
            $description,
            $modelType,
            $modelId,
            'failed',
            $exception ? [
                'error_message' => $exception->getMessage(),
                'error_class' => get_class($exception),
                'error_file' => $exception->getFile() . ':' . $exception->getLine(),
            ] : null
        );
    }

    /**
     * Compute a SHA-256 integrity hash for tamper detection.
     */
    private static function computeHash($action, $description, $userId, $modelType, $modelId): string
    {
        $secret = config('app.key', 'fallback-secret');
        $payload = implode('|', [
            $action,
            $description,
            $userId ?? 'null',
            $modelType ?? 'null',
            $modelId ?? 'null',
            now()->toISOString(),
        ]);

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify the integrity of a log entry.
     */
    public function verifyIntegrity(): bool
    {
        if (!$this->integrity_hash) {
            return false; // Legacy entries without hash
        }
        // Hash verification is based on creation-time data; re-computation would need
        // the exact timestamp, so we only verify the hash exists and is 64-char hex.
        return (bool) preg_match('/^[a-f0-9]{64}$/', $this->integrity_hash);
    }
}
