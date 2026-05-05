<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotLoadManagerService
{
    private const STATE_CACHE_KEY = 'chatbot_load_manager_state_v1';
    private const LOCK_CACHE_KEY = 'chatbot_load_manager_lock_v1';

    public function admit(array $context): array
    {
        if (!config('chatbot_unified.load.enabled', true)) {
            return [
                'admitted' => true,
                'mode' => 'normal',
                'token' => null,
                'snapshot' => $this->buildSnapshot($this->freshState()),
            ];
        }

        $waitBudgetMs = $this->resolveWaitBudgetMs($context);
        $pollIntervalMs = max(0, (int) config('chatbot_unified.load.wait_poll_ms', 75));
        $deadline = microtime(true) + ($waitBudgetMs / 1000);

        do {
            $decision = $this->withStateLock(function (array &$state) use ($context) {
                $maxActive = max(1, (int) config('chatbot_unified.load.max_active_requests', 12));
                $degradedThreshold = min(
                    $maxActive,
                    max(1, (int) config('chatbot_unified.load.degraded_threshold', max(1, $maxActive - 2)))
                );

                if (($state['active'] ?? 0) >= $maxActive) {
                    $state['soft_queue_total'] = (int) ($state['soft_queue_total'] ?? 0) + 1;

                    return [
                        'admitted' => false,
                        'mode' => 'waiting',
                        'token' => null,
                        'snapshot' => $this->buildSnapshot($state),
                    ];
                }

                $token = bin2hex(random_bytes(8));
                $state['reservations'][$token] = [
                    'started_at' => microtime(true),
                    'priority' => $this->resolvePriority($context),
                    'role' => $context['role'] ?? 'guest',
                    'streaming' => (bool) ($context['streaming'] ?? false),
                ];
                $state['active'] = count($state['reservations']);
                $state['admitted_total'] = (int) ($state['admitted_total'] ?? 0) + 1;
                $state['peak_active'] = max((int) ($state['peak_active'] ?? 0), $state['active']);

                $mode = $state['active'] >= $degradedThreshold ? 'degraded' : 'normal';
                if ($mode === 'degraded') {
                    $state['degraded_total'] = (int) ($state['degraded_total'] ?? 0) + 1;
                }

                return [
                    'admitted' => true,
                    'mode' => $mode,
                    'token' => $token,
                    'snapshot' => $this->buildSnapshot($state),
                ];
            });

            if ($decision['admitted']) {
                return $decision;
            }

            if ($pollIntervalMs <= 0 || microtime(true) >= $deadline) {
                break;
            }

            usleep($pollIntervalMs * 1000);
        } while (true);

        return $this->withStateLock(function (array &$state) use ($context) {
            $state['rejected_total'] = (int) ($state['rejected_total'] ?? 0) + 1;

            $snapshot = $this->buildSnapshot($state);

            Log::warning('Chatbot load admission rejected', [
                'role' => $context['role'] ?? 'guest',
                'streaming' => (bool) ($context['streaming'] ?? false),
                'state' => $snapshot['state'],
                'active_requests' => $snapshot['active_requests'],
                'max_active_requests' => $snapshot['max_active_requests'],
            ]);

            return [
                'admitted' => false,
                'mode' => 'busy',
                'token' => null,
                'snapshot' => $snapshot,
            ];
        });
    }

    public function release(?string $token): void
    {
        if (!$token || !config('chatbot_unified.load.enabled', true)) {
            return;
        }

        $this->withStateLock(function (array &$state) use ($token) {
            if (isset($state['reservations'][$token])) {
                unset($state['reservations'][$token]);
                $state['active'] = count($state['reservations']);
            }

            return null;
        });
    }

    public function snapshot(): array
    {
        return $this->withStateLock(function (array &$state) {
            return $this->buildSnapshot($state);
        });
    }

    public function publicStatus(): array
    {
        $snapshot = $this->snapshot();

        return [
            'status' => match ($snapshot['state']) {
                'overloaded' => 'busy',
                'degraded' => 'degraded',
                default => 'operational',
            },
            'load' => $snapshot['state'],
            'retry_after_seconds' => $snapshot['retry_after_seconds'],
        ];
    }

    private function withStateLock(callable $callback)
    {
        return Cache::lock(self::LOCK_CACHE_KEY, 5)->block(2, function () use ($callback) {
            $state = Cache::get(self::STATE_CACHE_KEY, $this->freshState());
            $state = $this->pruneExpiredReservations($state);

            $result = $callback($state);

            $state['active'] = count($state['reservations'] ?? []);
            $state['updated_at'] = now()->toIso8601String();
            Cache::put(self::STATE_CACHE_KEY, $state, now()->addSeconds($this->stateTtlSeconds()));

            return $result;
        });
    }

    private function pruneExpiredReservations(array $state): array
    {
        $state['reservations'] = $state['reservations'] ?? [];
        $staleAfterSeconds = max(5, (int) config('chatbot_unified.load.stale_request_seconds', 120));
        $now = microtime(true);

        foreach ($state['reservations'] as $token => $reservation) {
            $startedAt = (float) ($reservation['started_at'] ?? 0);
            if ($startedAt <= 0 || ($now - $startedAt) > $staleAfterSeconds) {
                unset($state['reservations'][$token]);
            }
        }

        $state['active'] = count($state['reservations']);

        return $state;
    }

    private function buildSnapshot(array $state): array
    {
        $active = count($state['reservations'] ?? []);
        $maxActive = max(1, (int) config('chatbot_unified.load.max_active_requests', 12));
        $warningThreshold = min($maxActive, max(1, (int) config('chatbot_unified.load.warning_threshold', max(1, $maxActive - 4))));
        $degradedThreshold = min($maxActive, max(1, (int) config('chatbot_unified.load.degraded_threshold', max(1, $maxActive - 2))));

        $stateLabel = 'normal';
        if ($active >= $maxActive) {
            $stateLabel = 'overloaded';
        } elseif ($active >= $degradedThreshold) {
            $stateLabel = 'degraded';
        } elseif ($active >= $warningThreshold) {
            $stateLabel = 'warning';
        }

        return [
            'state' => $stateLabel,
            'active_requests' => $active,
            'max_active_requests' => $maxActive,
            'retry_after_seconds' => max(1, (int) config('chatbot_unified.load.retry_after_seconds', 5)),
            'peak_active_requests' => (int) ($state['peak_active'] ?? 0),
            'soft_queue_total' => (int) ($state['soft_queue_total'] ?? 0),
            'rejected_total' => (int) ($state['rejected_total'] ?? 0),
            'degraded_total' => (int) ($state['degraded_total'] ?? 0),
        ];
    }

    private function resolvePriority(array $context): string
    {
        $role = $context['role'] ?? 'guest';
        $message = mb_strtolower((string) ($context['message'] ?? ''));

        if (preg_match('/\b(urgent|emergency|critical|asap|immediately|help now|failed payment|payment failed|stuck payment|refund issue)\b/i', $message)) {
            return 'high';
        }

        if (in_array($role, ['admin', 'staff', 'cashier'], true)) {
            return 'high';
        }

        if ($role === 'client') {
            return 'normal';
        }

        return 'low';
    }

    private function resolveWaitBudgetMs(array $context): int
    {
        $priority = $this->resolvePriority($context);

        return match ($priority) {
            'high' => max(0, (int) config('chatbot_unified.load.wait_ms.high', 1800)),
            'normal' => max(0, (int) config('chatbot_unified.load.wait_ms.normal', 900)),
            default => max(0, (int) config('chatbot_unified.load.wait_ms.low', 250)),
        };
    }

    private function freshState(): array
    {
        return [
            'reservations' => [],
            'active' => 0,
            'peak_active' => 0,
            'soft_queue_total' => 0,
            'rejected_total' => 0,
            'degraded_total' => 0,
            'admitted_total' => 0,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function stateTtlSeconds(): int
    {
        return max(60, (int) config('chatbot_unified.load.stale_request_seconds', 120) * 2);
    }
}