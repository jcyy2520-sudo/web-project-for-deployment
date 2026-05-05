<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function publicKey()
    {
        if (!WebPushService::isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Web push is not configured.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'public_key' => WebPushService::getPublicKey(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if (!WebPushService::isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Web push is not configured.',
            ], 503);
        }

        $validated = $request->validate([
            'endpoint' => 'required|url|max:512',
            'keys.p256dh' => 'required|string|max:512',
            'keys.auth' => 'required|string|max:512',
            'contentEncoding' => 'nullable|string|max:50',
        ]);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => (string) $request->userAgent(),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved.',
            'data' => $subscription,
        ]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:512',
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Push subscription removed.',
        ]);
    }
}