# Quick Integration Guide - Advanced NLU System

## Overview
This guide shows you how to integrate the new Advanced NLU System into your existing chatbot endpoints.

---

## Step 1: Inject Services into Your Controller

Update your `ChatbotController.php`:

```php
use App\Services\AdvancedNLPService;
use App\Services\AdvancedContentModerationService;
use App\Services\SmartIntentRecognitionService;
use App\Services\AdvancedNLUPipelineService;

class ChatbotController extends Controller
{
    private AdvancedNLUPipelineService $nluPipeline;
    
    public function __construct(
        // ... existing services
        AdvancedNLUPipelineService $nluPipeline
    ) {
        // ... existing assignments
        $this->nluPipeline = $nluPipeline;
    }
}
```

---

## Step 2: Use in Your Message Endpoint

Replace or enhance your existing message processing:

### Current Flow (Example)
```php
public function sendMessage(Request $request)
{
    $validated = $request->validate([
        'message' => 'required|string|max:5000',
    ]);

    $userMessage = $validated['message'];
    $userId = auth()->id();
    // ... process message
}
```

### Enhanced with NLU
```php
public function sendMessage(Request $request)
{
    $validated = $request->validate([
        'message' => 'required|string|max:5000',
    ]);

    $userMessage = $validated['message'];
    $userId = auth()->id();
    $user = auth()->user();

    // ===== NEW: Process through NLU Pipeline =====
    $nluResult = $this->nluPipeline->processInput(
        userInput: $userMessage,
        userId: $userId,
        conversationContext: [], // Add recent messages if available
        userRole: $user->role ?? 'guest'
    );

    // ===== Check if content is safe =====
    if (!$nluResult['is_safe']) {
        return response()->json([
            'success' => false,
            'message' => $nluResult['safety_response'],
            'blocked_reason' => $nluResult['violation_type'],
        ], 403);
    }

    // ===== Use detected intent and entities =====
    $intent = $nluResult['intent'];
    $intentConfidence = $nluResult['intent_confidence'];
    $entities = $nluResult['entities'];
    $normalizedInput = $nluResult['normalized_input'];

    // Now use these with your existing chatbot logic
    $aiResponse = $this->chatbotService->interpretAndRespond($userId, $normalizedInput);
    
    // Enhanced response with NLU metadata
    return response()->json([
        'success' => true,
        'message' => $aiResponse['reply'] ?? $aiResponse,
        'nlu_analysis' => [
            'intent' => $intent,
            'confidence' => $intentConfidence,
            'language' => $nluResult['language_detected'],
            'entities' => $entities,
        ],
        'processing_quality' => $nluResult['metadata']['processing_quality'],
    ]);
}
```

---

## Step 3: Add Safety-Aware Response Building

Enhance your response builder to use NLU confidence:

```php
private function buildSmartResponse(array $nluResult, int $userId): string
{
    $intent = $nluResult['intent'];
    $confidence = $nluResult['intent_confidence'];

    // High confidence: Use specific response
    if ($confidence >= 0.85) {
        return $this->responseBuilder->buildDeterministicResponse($intent, $nluResult['entities']);
    }

    // Medium confidence: Use LLM with context
    if ($confidence >= 0.6) {
        return $this->llmService->generateResponse(
            $nluResult['normalized_input'],
            context: [
                'detected_intent' => $intent,
                'intent_alternatives' => $nluResult['intent_alternatives'],
            ]
        );
    }

    // Low confidence: Ask for clarification
    if ($nluResult['needs_clarification']) {
        return $nluResult['suggested_clarification'] ?? "I'm not quite sure what you mean. Could you clarify?";
    }

    // Fallback
    return "I'm here to help. Could you rephrase that?";
}
```

---

## Step 4: Add Conversation Context (Optional)

Improve accuracy by passing recent messages:

```php
private function getConversationContext(int $userId, string $conversationId, int $limit = 5): array
{
    return ChatMessage::where('user_id', $userId)
        ->where('conversation_id', $conversationId)
        ->latest()
        ->limit($limit)
        ->pluck('message')
        ->toArray();
}

public function sendMessage(Request $request)
{
    // ... validation ...
    
    $conversationContext = $this->getConversationContext(
        auth()->id(),
        $request->input('conversation_id')
    );

    $nluResult = $this->nluPipeline->processInput(
        userInput: $userMessage,
        userId: auth()->id(),
        conversationContext: $conversationContext, // Add this
        userRole: auth()->user()->role
    );

    // ... rest of code
}
```

---

## Step 5: Add Diagnostics Endpoint (Optional)

Create an endpoint for debugging NLU:

```php
public function getNLUDiagnostics(Request $request)
{
    $validated = $request->validate([
        'message' => 'required|string',
    ]);

    // Only for admins/developers
    if (!auth()->user()->hasRole('admin')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $diagnostics = $this->nluPipeline->getDiagnostics(
        userInput: $validated['message'],
        userId: auth()->id(),
        userRole: auth()->user()->role
    );

    return response()->json($diagnostics);
}
```

Add to routes:
```php
Route::post('/chatbot/nlu/diagnostics', [ChatbotController::class, 'getNLUDiagnostics'])
    ->middleware('auth:sanctum');
```

---

## Step 6: Add Frontend Integration (Optional)

Show confidence scores and language detection in frontend:

```javascript
// In React/Vue component
const response = await fetch('/api/chatbot/message', {
    method: 'POST',
    body: JSON.stringify({ message: userInput }),
});

const data = await response.json();

// Display NLU metadata
console.log('Intent:', data.nlu_analysis.intent);
console.log('Confidence:', data.nlu_analysis.confidence);
console.log('Language:', data.nlu_analysis.language);

// Show quality indicator
if (data.processing_quality > 0.8) {
    showBadge('High confidence', 'green');
} else if (data.processing_quality > 0.6) {
    showBadge('Medium confidence', 'yellow');
} else {
    showBadge('Low confidence', 'orange');
}
```

---

## Step 7: Enable Real-Time Learning (Optional)

Allow the system to learn from new patterns:

```php
// After handling unsafe content
if (!$nluResult['is_safe']) {
    // Track patterns for future learning
    Log::channel('unsafe_content')->info($nluResult);
    
    // Optionally: Add to custom patterns for learning
    // $moderation->addCustomPattern('/pattern/', 'category');
}

// Or in admin panel, after reviewing content:
public function addModerationPattern(Request $request)
{
    if (!auth()->user()->hasRole('admin')) {
        abort(403);
    }

    $validated = $request->validate([
        'pattern' => 'required|string',
        'category' => 'required|string|in:profanity,hate_speech,harmful,custom',
    ]);

    $moderation = app(AdvancedContentModerationService::class);
    $moderation->addCustomPattern($validated['pattern'], $validated['category']);

    return response()->json(['success' => true]);
}
```

---

## Testing the Integration

### 1. Test in Tinker

```bash
php artisan tinker
```

```php
$pipeline = app(AdvancedNLUPipelineService::class);

// Test multilingual
$result = $pipeline->processInput("paano magbook ng appointment?");
dd($result);

// Test safety
$result = $pipeline->processInput("bad words here");
dd($result);

// Test fuzzy
$result = $pipeline->processInput("apontmnt status");
dd($result);
```

### 2. Test via API

```bash
curl -X POST http://localhost:8000/api/chatbot/message \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message": "can i book appointment tmrw?"}'
```

### 3. Test Diagnostics

```bash
curl -X POST http://localhost:8000/api/chatbot/nlu/diagnostics \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message": "pano mag book apt?"}'
```

---

## Configuration

No configuration required! The system works out-of-the-box with sensible defaults.

Optional environment variables:
```env
# Cache TTL for NLU results (seconds)
NLU_CACHE_TTL=3600

# Minimum fuzzy match score (0.0 - 1.0)
NLU_MIN_MATCH_SCORE=0.70

# Safety confidence threshold (0.0 - 1.0)
CONTENT_MODERATION_THRESHOLD=0.60
```

---

## Before & After Comparison

### Before
```json
{
  "response": "I'm here to help with appointments..."
}
```

### After
```json
{
  "success": true,
  "message": "I can help you book an appointment. What date works for you?",
  "nlu_analysis": {
    "intent": "appointment.book",
    "confidence": 0.92,
    "language": "english",
    "entities": {
      "appointment_entities": {
        "date": "tomorrow"
      }
    }
  },
  "processing_quality": 0.89
}
```

---

## Troubleshooting Integration Issues

### Issue: Services not found
**Solution:** Make sure service providers are registered in `config/app.php`

```php
'providers' => [
    // ... existing providers
    \App\Providers\NLUServiceProvider::class, // If you create one
],
```

Or manually: Create `app/Providers/NLUServiceProvider.php`:
```php
<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class NLUServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('AdvancedNLPService', \App\Services\AdvancedNLPService::class);
        $this->app->singleton('AdvancedContentModerationService', \App\Services\AdvancedContentModerationService::class);
        $this->app->singleton('SmartIntentRecognitionService', \App\Services\SmartIntentRecognitionService::class);
        $this->app->singleton('AdvancedNLUPipelineService', \App\Services\AdvancedNLUPipelineService::class);
    }
}
```

### Issue: Slow responses
**Solution:** Clear cache and check logs

```bash
php artisan cache:clear
php artisan config:cache
```

### Issue: Intent not recognized
**Solution:** Check diagnostics endpoint to see what's happening

```bash
curl -X POST http://localhost:8000/api/chatbot/nlu/diagnostics \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message": "your test message"}'
```

---

## Next Steps

1. ✅ Inject services into controller
2. ✅ Use NLU pipeline in message endpoint
3. ✅ Add safety checks
4. ✅ Test with various inputs
5. ✅ Monitor performance
6. ✅ Add real-time learning as needed

---

**Your chatbot now has real-time, intelligent multilingual NLU!** 🎉
