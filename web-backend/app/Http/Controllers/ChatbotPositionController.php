<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ChatbotPositionController extends Controller
{
    /**
     * Get the user's chatbot position preference
     */
    public function show(Request $request)
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'position' => 'bottom-right' // Default position
                ]
            ]);
        }

        $position = Cache::get("chatbot_position_{$userId}", 'bottom-right');

        return response()->json([
            'success' => true,
            'data' => [
                'position' => $position
            ]
        ]);
    }

    /**
     * Store/update the user's chatbot position preference
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'position' => 'required|string|in:bottom-right,bottom-left,top-right,top-left'
        ]);

        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Store in cache for 1 year
        Cache::put("chatbot_position_{$userId}", $validated['position'], 60 * 60 * 24 * 365);

        return response()->json([
            'success' => true,
            'data' => [
                'position' => $validated['position']
            ],
            'message' => 'Chatbot position saved'
        ]);
    }
}
