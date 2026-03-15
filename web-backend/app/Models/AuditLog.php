<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'status',
        'error_message'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public static function log($action, $entityType, $entityId = null, $description = null, $oldValues = null, $newValues = null, $status = 'success', $errorMessage = null)
    {
        try {
            return self::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status' => $status,
                'error_message' => $errorMessage
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('AuditLog::log failed (non-blocking): ' . $e->getMessage());
            return null;
        }
    }
}
