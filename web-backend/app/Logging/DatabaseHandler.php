<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use App\Models\ErrorLog;

class DatabaseHandler extends AbstractProcessingHandler
{
    /**
     * Write log record to database
     */
    protected function write(LogRecord $record): void
    {
        try {
            // Don't log to database in testing or if table doesn't exist
            if (app()->environment('testing')) {
                return;
            }

            // Get request information if available
            $ipAddress = null;
            $userAgent = null;
            $url = null;
            $method = null;
            $requestData = null;
            $userId = null;

            if (request()) {
                $ipAddress = request()->ip();
                $userAgent = request()->userAgent();
                $url = request()->fullUrl();
                $method = request()->method();
                $userId = auth()->id();

                // Safely capture request data (avoid sensitive data)
                try {
                    $allData = request()->all();
                    // Remove sensitive fields
                    $sensitiveFields = ['password', 'pin', 'token', 'secret', 'api_key', 'credit_card'];
                    foreach ($sensitiveFields as $field) {
                        unset($allData[$field]);
                    }
                    $requestData = count($allData) > 0 ? $allData : null;
                } catch (\Exception $e) {
                    // Ignore if we can't get request data
                }
            }

            ErrorLog::create([
                'level' => $record['level_name'],
                'message' => $record['message'],
                'exception' => $record['extra']['exception'] ?? null,
                'stack_trace' => $record['extra']['stack_trace'] ?? null,
                'file' => $record['extra']['file'] ?? null,
                'line' => $record['extra']['line'] ?? null,
                'context' => $record['context'] ?? null,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
                'url' => $url,
                'method' => $method,
                'request_data' => $requestData,
                'user_id' => $userId,
            ]);
        } catch (\Exception $e) {
            // Silently fail to avoid infinite recursion
        }
    }
}
