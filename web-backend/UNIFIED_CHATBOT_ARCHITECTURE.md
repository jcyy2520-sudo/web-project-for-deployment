# Unified AI Chatbot Architecture

## Overview

This document describes the refactored AI chatbot system that implements an **LLM-first architecture** with proper RAG (Retrieval-Augmented Generation) pipeline.

### What Changed

| Before (Pattern-First) | After (LLM-First) |
|------------------------|-------------------|
| Hardcoded intent patterns | Vector embeddings for semantic understanding |
| 15+ service dependencies | 3 core services |
| LLM as fallback | LLM as primary |
| Confidence thresholds (0.85, 0.3) | Semantic similarity scores |
| Multiple handlers per intent | Unified response pipeline |
| No conversation context | Full conversation history |
| No feedback loop | Comprehensive feedback system |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      User Message                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  1. EMBED                                                       │
│     Convert message to vector embedding                         │
│     (VectorEmbeddingService)                                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  2. RETRIEVE                                                    │
│     Semantic search for relevant knowledge                      │
│     (Knowledge Base with pre-computed embeddings)               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  3. AUGMENT                                                     │
│     Combine:                                                    │
│     - Retrieved knowledge context                               │
│     - Conversation history (last 10 messages)                   │
│     - Real-time system data (appointments, services, etc.)      │
│     - User context (role, permissions)                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  4. GENERATE                                                    │
│     Send augmented prompt to LLM                                │
│     (Claude or Ollama)                                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  5. RESPOND + LOG                                               │
│     - Validate response                                         │
│     - Log interaction for feedback loop                         │
│     - Return to user                                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Services

### 1. UnifiedChatbotService
**File:** `app/Services/UnifiedChatbotService.php`

The main entry point. Orchestrates the entire pipeline:
- Safety checks
- Context gathering
- Semantic retrieval
- LLM generation
- Response validation

```php
$result = $chatbotService->processMessage(
    $userMessage,
    $userId,
    $conversationId,
    ['language' => 'english']
);
```

### 2. VectorEmbeddingService
**File:** `app/Services/VectorEmbeddingService.php`

Handles semantic understanding:
- Generates embeddings (via Ollama or OpenAI)
- Performs semantic search
- Manages knowledge base indexing
- Falls back to keyword search if embeddings unavailable

```php
// Semantic search
$results = $embeddingService->semanticSearch($query, $category, $limit);

// Index document
$embeddingService->indexDocument($title, $content, 'services', 'service');
```

### 3. ChatbotFeedbackService
**File:** `app/Services/ChatbotFeedbackService.php`

Implements the feedback loop:
- Logs every interaction
- Collects user feedback (thumbs up/down, corrections)
- Tracks wrong answers
- Exports data for retraining

```php
// Log interaction
$interactionId = $feedbackService->logInteraction([...]);

// Record feedback
$feedbackService->recordFeedback($interactionId, [
    'is_helpful' => false,
    'correction' => 'The correct answer is...',
]);
```

---

## API Endpoints

### New V2 Endpoints (LLM-First)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/chatbot/v2/send-message` | Send message, get response |
| POST | `/api/chatbot/v2/stream` | Streaming response (SSE) |
| GET | `/api/chatbot/v2/status` | Health check |
| GET | `/api/chatbot/v2/history` | Get chat history |
| POST | `/api/chatbot/v2/feedback` | Submit feedback |
| GET | `/api/chatbot/v2/analytics` | Feedback analytics (admin) |

### Request Example

```bash
curl -X POST https://api.example.com/api/chatbot/v2/send-message \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What services do you offer?",
    "conversation_id": "chat_123"
  }'
```

### Response Example

```json
{
  "success": true,
  "conversation_id": "chat_123",
  "user_message": "What services do you offer?",
  "ai_response": "We offer the following legal services:\n\n1. **Notary Services** - ₱500\n2. **Legal Consultation** - ₱1,000/hour\n3. **Document Review** - ₱750\n...",
  "meta": {
    "source": "llm",
    "provider": "claude",
    "model": "claude-3-sonnet-20240229",
    "context_sources": 3,
    "processing_time_ms": 1234,
    "interaction_id": "abc-123-def"
  },
  "timestamp": "2026-01-03T10:30:00.000Z"
}
```

---

## Setup Instructions

### 1. Run Database Migration

```bash
cd web-backend
php artisan migrate
```

This creates:
- `chatbot_interaction_logs` - Stores all interactions
- `chatbot_feedback` - Stores user feedback

### 2. Index Knowledge Base

```bash
# Index from knowledge bundle file
php artisan chatbot:index-knowledge

# Or rebuild entire index
php artisan chatbot:index-knowledge --rebuild

# Or index only database services
php artisan chatbot:index-knowledge --services
```

### 3. Configure Environment

```env
# LLM Provider (claude or ollama)
LLM_PRIMARY_PROVIDER=claude
ANTHROPIC_API_KEY=your-claude-api-key

# Or use Ollama (free, local)
USE_OLLAMA_LLM=true
OLLAMA_MODEL=mistral

# Embeddings (for semantic search)
USE_OLLAMA_EMBEDDINGS=true
OLLAMA_EMBEDDING_MODEL=nomic-embed-text

# Or use OpenAI embeddings
# OPENAI_API_KEY=your-openai-key
```

### 4. Start Ollama (if using local LLM)

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull models
ollama pull mistral
ollama pull nomic-embed-text

# Start server
ollama serve
```

---

## Feedback Loop

The feedback system enables continuous improvement:

### Collecting Feedback

Users can rate responses:

```javascript
// Frontend example
const submitFeedback = async (interactionId, isHelpful) => {
  await fetch('/api/chatbot/v2/feedback', {
    method: 'POST',
    body: JSON.stringify({
      interaction_id: interactionId,
      is_helpful: isHelpful,
    }),
  });
};
```

### For Corrections

```javascript
const submitCorrection = async (interactionId, correction) => {
  await fetch('/api/chatbot/v2/feedback', {
    method: 'POST',
    body: JSON.stringify({
      interaction_id: interactionId,
      is_correct: false,
      correction: 'The actual office hours are 8am-5pm',
      category: 'wrong_info',
    }),
  });
};
```

### Reviewing Feedback (Admin)

```bash
GET /api/chatbot/v2/analytics

Response:
{
  "total_interactions": 1500,
  "satisfaction_rate": 87.5,
  "marked_wrong_count": 25,
  "common_issues": [
    {"category": "outdated", "count": 10},
    {"category": "unclear", "count": 8}
  ]
}
```

---

## Streaming Responses

For better UX, use the streaming endpoint:

```javascript
const eventSource = new EventSource('/api/chatbot/v2/stream?message=Hello');

eventSource.addEventListener('token', (e) => {
  const data = JSON.parse(e.data);
  appendToChat(data.content); // Show token by token
});

eventSource.addEventListener('done', () => {
  eventSource.close();
});

eventSource.addEventListener('error', (e) => {
  console.error('Streaming error:', e);
  eventSource.close();
});
```

---

## Migration from Legacy Endpoints

The legacy endpoints (`/api/chatbot/send-message`) still work but are deprecated.

### Recommended Migration Path

1. Update frontend to use `/api/chatbot/v2/send-message`
2. Add feedback UI using the `interaction_id` from responses
3. Enable streaming for better UX
4. Monitor feedback analytics to identify improvements

### Key Differences

| Aspect | Legacy | V2 (Unified) |
|--------|--------|--------------|
| Response source | Pattern match → LLM fallback | Always LLM |
| Context | Limited | Full RAG + History |
| Feedback | None | Built-in |
| Streaming | Separate endpoint | Integrated |
| Dependencies | 12+ services | 3 services |

---

## Troubleshooting

### LLM Not Responding

1. Check API key: `ANTHROPIC_API_KEY` or `OPENAI_API_KEY`
2. Check Ollama: `curl http://localhost:11434/api/version`
3. Check logs: `tail -f storage/logs/laravel.log`

### Semantic Search Not Working

1. Index knowledge: `php artisan chatbot:index-knowledge`
2. Check embeddings: `curl http://localhost:11434/api/embeddings -d '{"model":"nomic-embed-text","prompt":"test"}'`
3. Verify documents: Check `knowledge_base` table has entries with embeddings

### Slow Responses

1. Enable caching in `.env`: `CACHE_DRIVER=redis`
2. Pre-compute embeddings: Run indexer command
3. Use streaming endpoint for perceived speed

---

## Files Reference

### New Files Created

| File | Purpose |
|------|---------|
| `app/Services/UnifiedChatbotService.php` | Main LLM-first pipeline |
| `app/Services/VectorEmbeddingService.php` | Semantic search |
| `app/Services/ChatbotFeedbackService.php` | Feedback loop |
| `app/Http/Controllers/UnifiedChatbotController.php` | V2 API endpoints |
| `app/Models/ChatbotInteractionLog.php` | Interaction logging |
| `app/Models/ChatbotFeedback.php` | Feedback storage |
| `app/Console/Commands/IndexChatbotKnowledge.php` | CLI indexer |
| `config/chatbot_unified.php` | Simplified config |
| `database/migrations/2026_01_03_000001_create_chatbot_feedback_tables.php` | DB tables |

### Deprecated (Still Functional)

The following services are no longer primary but remain for backward compatibility:
- `ChatbotNLUService` - Intent pattern matching
- `ChatbotSmartResponseBuilder` - Template responses
- `ClarificationEngineService` - Clarification prompts
- `IntentDetectionEngine` - Hardcoded intents
- Multiple intent handlers in `ChatbotService`

---

## Best Practices

1. **Always use conversation_id** - Maintains context across messages
2. **Enable streaming** - Better UX, users see response generating
3. **Collect feedback** - Essential for continuous improvement
4. **Monitor analytics** - Identify problem areas early
5. **Keep knowledge indexed** - Run indexer after content changes
6. **Use RAG context** - Don't expect LLM to know your specific data

---

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Run health check: `GET /api/chatbot/v2/status`
- Review this documentation
