<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'filename',
        'path',
        'size',
        'database_name',
        'status',
        'backup_type',
        'error_message',
        'created_by',
        'started_at',
        'completed_at',
        'last_restored_at',
        'is_verified',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_restored_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    /**
     * Get human-readable size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }
        return null;
    }

    /**
     * Scope: Get successful backups
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Get recent backups
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('completed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Get automatic backups
     */
    public function scopeAutomatic($query)
    {
        return $query->where('backup_type', 'automatic');
    }

    /**
     * Check if backup file exists
     */
    public function fileExists(): bool
    {
        return file_exists($this->path);
    }

    /**
     * Delete backup file
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return unlink($this->path);
        }
        return true;
    }

    /**
     * Get the user who created this backup
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
