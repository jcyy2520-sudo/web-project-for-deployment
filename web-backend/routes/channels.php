<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat channel - user can only access their own chat
Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Conversation channel - user can only access conversations they're part of
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Check if user has access to this conversation
    $hasAccess = \App\Models\ChatMessage::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
    
    return $hasAccess;
});

// Appointments channel (for admin/staff)
Broadcast::channel('appointments', function ($user) {
    return $user->hasAnyRole(['admin', 'staff', 'cashier']);
});

// Appointment settings channel (for admin)
Broadcast::channel('appointment-settings', function ($user) {
    return $user->hasRole('admin');
});
