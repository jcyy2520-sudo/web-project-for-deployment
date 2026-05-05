<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public static function isConfigured(): bool
    {
        return filled(env('VAPID_PUBLIC_KEY'))
            && filled(env('VAPID_PRIVATE_KEY'))
            && filled(env('VAPID_SUBJECT'));
    }

    public static function getPublicKey(): ?string
    {
        return self::isConfigured() ? env('VAPID_PUBLIC_KEY') : null;
    }

    public static function sendNotification(Notification $notification): void
    {
        $notification->loadMissing('user.notificationPreferences', 'user.pushSubscriptions');

        $user = $notification->user;
        if (!$user instanceof User) {
            return;
        }

        if (!self::shouldSendToUser($user, $notification)) {
            return;
        }

        $subscriptions = $user->pushSubscriptions
            ->where('is_active', true)
            ->values();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => env('VAPID_SUBJECT'),
                    'publicKey' => env('VAPID_PUBLIC_KEY'),
                    'privateKey' => env('VAPID_PRIVATE_KEY'),
                ],
            ]);
            $webPush->setReuseVAPIDHeaders(true);

            $payload = json_encode(self::buildPayload($notification, $user), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return;
            }

            foreach ($subscriptions as $subscriptionModel) {
                $subscription = Subscription::create([
                    'endpoint' => $subscriptionModel->endpoint,
                    'publicKey' => $subscriptionModel->public_key,
                    'authToken' => $subscriptionModel->auth_token,
                    'contentEncoding' => $subscriptionModel->content_encoding ?: 'aes128gcm',
                ]);

                $webPush->queueNotification($subscription, $payload);
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $subscriptionModel = $subscriptions->firstWhere('endpoint', $endpoint);

                if ($report->isSuccess()) {
                    $subscriptionModel?->forceFill([
                        'last_used_at' => now(),
                        'is_active' => true,
                    ])->save();
                    continue;
                }

                Log::warning('Web push delivery failed', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);

                if ($report->isSubscriptionExpired() && $subscriptionModel) {
                    $subscriptionModel->update(['is_active' => false]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Web push send failed', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private static function shouldSendToUser(User $user, Notification $notification): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $preferences = $user->notificationPreferences;
        if (!$preferences) {
            return true;
        }

        if (!$preferences->push_notifications) {
            return false;
        }

        if ($preferences->isInQuietHours()) {
            return false;
        }

        $type = $notification->type;

        $appointmentTypes = [
            'appointment_approved',
            'appointment_declined',
            'appointment_cancelled',
            'appointment_completed',
            'appointment_no_show',
            'appointment_status_updated',
            'appointment_ready_for_payment',
        ];

        if (in_array($type, $appointmentTypes, true) && !$preferences->push_appointment_updates) {
            return false;
        }

        return true;
    }

    private static function buildPayload(Notification $notification, User $user): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $path = Arr::get($data, 'url', self::defaultPathForNotification($notification, $user));

        return [
            'title' => $notification->title,
            'body' => $notification->message,
            'icon' => '/logo-192.png',
            'badge' => '/logo-192.png',
            'tag' => 'notification-' . $notification->id,
            'renotify' => true,
            'data' => array_merge($data, [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'related_id' => $notification->related_id,
                'related_type' => $notification->related_type,
                'url' => $path,
            ]),
        ];
    }

    private static function defaultPathForNotification(Notification $notification, User $user): string
    {
        if ($notification->related_type === 'Appointment') {
            return match ($user->role) {
                'cashier' => '/cashier',
                'staff', 'admin' => '/staff/appointments',
                default => '/appointments',
            };
        }

        return match ($user->role) {
            'cashier' => '/cashier',
            'staff', 'admin' => '/dashboard',
            default => '/dashboard',
        };
    }
}