<?php

namespace App\Services;

use App\Models\AlertEvent;
use App\Models\AlertRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackAlertService
{
    /**
     * Send alert to Slack
     */
    public function sendAlert(AlertRule $rule, AlertEvent $event): bool
    {
        try {
            if (!$rule->slack_webhook) {
                Log::warning('No Slack webhook configured for rule: ' . $rule->name);
                return false;
            }

            $payload = $this->buildSlackMessage($rule, $event);
            $response = Http::post($rule->slack_webhook, $payload);

            if ($response->successful()) {
                // Extract message timestamp if available
                $messageId = $response->json('ts') ?? $response->json('ok');
                $event->markSent($messageId);
                return true;
            } else {
                Log::error('Failed to send Slack alert', [
                    'rule_id' => $rule->id,
                    'event_id' => $event->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Slack alert service error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build Slack message payload
     */
    private function buildSlackMessage(AlertRule $rule, AlertEvent $event): array
    {
        $color = $this->getSeverityColor($event->severity);
        
        return [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $rule->name,
                    'text' => $event->message,
                    'fields' => [
                        [
                            'title' => 'Severity',
                            'value' => strtoupper($event->severity),
                            'short' => true,
                        ],
                        [
                            'title' => 'Type',
                            'value' => $rule->type,
                            'short' => true,
                        ],
                        [
                            'title' => 'Channel',
                            'value' => $rule->channel,
                            'short' => true,
                        ],
                        [
                            'title' => 'Time',
                            'value' => $event->created_at->format('Y-m-d H:i:s'),
                            'short' => true,
                        ],
                    ],
                    'footer' => 'Alert System',
                    'ts' => $event->created_at->timestamp,
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'View Dashboard',
                            'url' => config('app.url') . '/admin/alerts',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get color for severity level
     */
    private function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'critical' => '#FF0000',
            'warning' => '#FFA500',
            'info' => '#0099FF',
            default => '#808080',
        };
    }

    /**
     * Send digest/summary to Slack
     */
    public function sendDigest(string $webhookUrl, array $summary): bool
    {
        try {
            $payload = [
                'attachments' => [
                    [
                        'color' => '#36a64f',
                        'title' => 'Daily Alert Summary',
                        'fields' => [
                            [
                                'title' => 'Critical Alerts',
                                'value' => $summary['critical'] ?? 0,
                                'short' => true,
                            ],
                            [
                                'title' => 'Warnings',
                                'value' => $summary['warning'] ?? 0,
                                'short' => true,
                            ],
                            [
                                'title' => 'Info Messages',
                                'value' => $summary['info'] ?? 0,
                                'short' => true,
                            ],
                            [
                                'title' => 'Acknowledged',
                                'value' => $summary['acknowledged'] ?? 0,
                                'short' => true,
                            ],
                        ],
                        'footer' => 'Alert System',
                        'ts' => now()->timestamp,
                    ],
                ],
            ];

            $response = Http::post($webhookUrl, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Slack digest: ' . $e->getMessage());
            return false;
        }
    }
}
