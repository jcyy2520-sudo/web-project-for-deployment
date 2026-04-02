<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * HTTP client for the Python ML microservice.
 * Handles all communication between Laravel and the ML prediction service.
 */
class MLServiceClient
{
    private string $baseUrl;
    private int $timeout;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('ml.url', 'http://127.0.0.1:8100');
        $this->timeout = config('ml.timeout', 10);
        $this->apiKey = config('ml.api_key', '');
    }

    /**
     * Check if the ML service is running and reachable.
     */
    public function isAvailable(): bool
    {
        return Cache::remember('ml_service_available', 30, function () {
            try {
                $response = Http::timeout(3)->get("{$this->baseUrl}/health");
                return $response->ok();
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    /**
     * Get model status and metadata.
     */
    public function getStatus(): array
    {
        return $this->get('/status');
    }

    /**
     * Check if a trained model exists.
     */
    public function hasTrainedModel(): bool
    {
        try {
            $status = $this->getStatus();
            return $status['model']['has_model'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get data quality report for training readiness.
     */
    public function getDataQuality(): array
    {
        return $this->get('/data-quality');
    }

    /**
     * Trigger ML model training.
     */
    public function train(): array
    {
        return $this->post('/train', [], 120); // Training can take longer
    }

    /**
     * Predict appointment risk (cancellation/no-show).
     */
    public function predictRisk(int $appointmentId): array
    {
        return $this->post('/predict/risk', [
            'appointment_id' => $appointmentId,
        ]);
    }

    /**
     * Rank time slots for a given date by predicted success.
     */
    public function predictSlotRank(string $date): array
    {
        return $this->post('/predict/slot-rank', [
            'date' => $date,
        ]);
    }

    /**
     * Log outcome feedback for retraining.
     */
    public function logFeedback(int $appointmentId, string $outcome, ?string $staffFeedback = null, ?string $reason = null): array
    {
        return $this->post('/feedback', [
            'appointment_id' => $appointmentId,
            'actual_outcome' => $outcome,
            'staff_feedback' => $staffFeedback,
            'feedback_reason' => $reason,
        ]);
    }

    // ─── Internal HTTP Methods ───────────────────────────────────────

    private function get(string $path): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers())
                ->get("{$this->baseUrl}{$path}");

            if (!$response->ok()) {
                Log::warning("ML service GET {$path} returned {$response->status()}", [
                    'body' => $response->body(),
                ]);
                return ['error' => "ML service returned status {$response->status()}"];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("ML service GET {$path} failed: {$e->getMessage()}");
            return ['error' => 'ML service unavailable'];
        }
    }

    private function post(string $path, array $data, int $timeout = null): array
    {
        try {
            $response = Http::timeout($timeout ?? $this->timeout)
                ->withHeaders($this->headers())
                ->post("{$this->baseUrl}{$path}", $data);

            if (!$response->ok()) {
                Log::warning("ML service POST {$path} returned {$response->status()}", [
                    'body' => $response->body(),
                ]);
                return ['error' => "ML service returned status {$response->status()}"];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("ML service POST {$path} failed: {$e->getMessage()}");
            return ['error' => 'ML service unavailable'];
        }
    }

    private function headers(): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey) {
            $headers['X-API-Key'] = $this->apiKey;
        }
        return $headers;
    }
}
