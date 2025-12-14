# Smart Chatbot Upgrade - Setup Guide

This guide explains how to set up and configure the enhanced AI chatbot system.

## New Features Implemented

### 1. ✅ Advanced LLM Integration (Replaces Flan-T5)
- **Multiple Providers**: Claude (Anthropic), OpenAI GPT-4, Mistral, and self-hosted Ollama
- **Automatic Fallback**: If one provider fails, system tries alternatives
- **Smart Model Selection**: Different model tiers (fast, default, smart)

### 2. ✅ Real-Time Communication (WebSocket)
- **Laravel Broadcasting**: Events for real-time message sync
- **Private Channels**: Secure per-user chat channels
- **Multi-device Sync**: Messages sync across devices/tabs

### 3. ✅ Streaming Responses
- **Server-Sent Events (SSE)**: Real-time token streaming
- **No More Waiting**: See AI responses as they're generated
- **Abort Support**: Stop generation mid-stream

### 4. ✅ Vector Knowledge Base (RAG)
- **Semantic Search**: Find relevant info using embeddings
- **Knowledge Indexing**: Index services, FAQs, policies
- **Context Injection**: Relevant knowledge added to prompts

### 5. ✅ Enhanced Conversation Memory
- **50-message Context**: Increased from 10 messages
- **Cross-Session Memory**: Remember past conversations
- **User Preference Learning**: Adapt to user patterns

### 6. ✅ AI Personality System
- **4 Personalities**: Professional, Friendly, Expert, Concise
- **Role-Specific Tone**: Different approach per user role
- **Sentiment Adaptation**: Respond to user mood

### 7. ✅ Smart Action Suggestions
- **Predictive Actions**: Suggest relevant actions
- **Quick Action Buttons**: One-click actions
- **History-Based Ranking**: Prioritize frequently used actions

---

## Environment Configuration

Add these to your `.env` file:

```env
# ============================================
# LLM PROVIDERS (Choose one or more)
# ============================================

# Claude (Anthropic) - Recommended for complex reasoning
ANTHROPIC_API_KEY=sk-ant-xxxxx

# OpenAI - Good general-purpose option
OPENAI_API_KEY=sk-xxxxx

# Mistral AI - European alternative
MISTRAL_API_KEY=xxxxx

# Ollama (Self-hosted) - For privacy/cost savings
USE_OLLAMA_LLM=false
OLLAMA_MODEL=mistral

# ============================================
# EMBEDDING PROVIDERS (For Knowledge Base)
# ============================================

# Uses OpenAI embeddings by default if OPENAI_API_KEY is set
EMBEDDING_MODEL=text-embedding-3-small

# Alternative: Voyage AI embeddings
VOYAGE_API_KEY=xxxxx

# Alternative: Ollama embeddings (local)
USE_OLLAMA_EMBEDDINGS=false
OLLAMA_EMBEDDING_MODEL=nomic-embed-text

# ============================================
# CHATBOT SETTINGS
# ============================================

# Default personality: professional, friendly, expert, concise
CHATBOT_PERSONALITY=professional

# Max tokens for LLM response
LLM_MAX_TOKENS=2048

# Temperature (0.0-1.0, higher = more creative)
LLM_TEMPERATURE=0.7

# Request timeout in seconds
LLM_TIMEOUT=60

# ============================================
# BROADCASTING (WebSocket)
# ============================================

# Options: pusher, redis, log
BROADCAST_DRIVER=pusher

# Pusher credentials (get from pusher.com)
PUSHER_APP_ID=xxxxx
PUSHER_APP_KEY=xxxxx
PUSHER_APP_SECRET=xxxxx
PUSHER_APP_CLUSTER=us2

# Or use Laravel Reverb (self-hosted)
# BROADCAST_DRIVER=reverb
# REVERB_APP_ID=xxxxx
# REVERB_APP_KEY=xxxxx
# REVERB_APP_SECRET=xxxxx
# REVERB_HOST=localhost
# REVERB_PORT=8080
```

---

## Installation Steps

### 1. Install PHP Dependencies

```bash
cd web-backend
composer require guzzlehttp/guzzle
```

### 2. Run Migrations

```bash
php artisan migrate
```

This creates:
- `chatbot_knowledge_base` - For storing indexed knowledge
- `chatbot_conversation_embeddings` - For semantic memory
- `user_preferences` - For personalization

### 3. Index Knowledge Base (Optional but Recommended)

Create an Artisan command to index your services:

```bash
php artisan tinker
```

```php
$embeddingService = app(\App\Services\EmbeddingService::class);

// Index services
$count = $embeddingService->indexServices();
echo "Indexed {$count} services\n";

// Index FAQs
$faqs = [
    [
        'question' => 'How do I book an appointment?',
        'answer' => 'Go to the booking page, select a service, choose a date and time, then confirm your booking.',
        'category' => 'booking',
    ],
    [
        'question' => 'What is the refund policy?',
        'answer' => 'Refunds are available within 24 hours of booking. Cancelled appointments may be eligible for refunds.',
        'category' => 'refund',
    ],
    // Add more FAQs...
];
$count = $embeddingService->indexFAQs($faqs);
echo "Indexed {$count} FAQs\n";
```

### 4. Set Up Broadcasting (For Real-Time)

If using Pusher:

```bash
composer require pusher/pusher-php-server
```

Frontend setup (already configured if using Echo):

```javascript
// In your main.js or App.jsx
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_KEY,
    cluster: import.meta.env.VITE_PUSHER_CLUSTER,
    forceTLS: true
});
```

### 5. Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## API Endpoints

### New Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/chatbot/stream` | Send message with streaming response (SSE) |
| GET | `/api/chatbot/status` | Get LLM status and features |
| GET | `/api/chatbot/suggestions` | Get smart action suggestions |
| POST | `/api/chatbot/search-knowledge` | Search knowledge base |
| POST | `/api/chatbot/preferences` | Set user preference |

### Streaming Example

```javascript
const response = await fetch('/api/chatbot/stream', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'text/event-stream',
  },
  body: JSON.stringify({
    message: 'What services do you offer?',
    conversation_id: 'chat_123',
    personality: 'friendly',
    stream: true,
  }),
});

const reader = response.body.getReader();
// Process SSE events...
```

---

## Frontend Integration

### Using the New Hook

Replace `useChatbot` with `useStreamingChatbot`:

```jsx
import useStreamingChatbot from '../hooks/useStreamingChatbot';

function ChatbotModal() {
  const {
    messages,
    loading,
    streaming,
    streamingText,
    sendMessage,
    stopStreaming,
    personality,
    changePersonality,
    actionSuggestions,
    quickActions,
    // ... other values
  } = useStreamingChatbot();

  return (
    <div>
      {/* Messages */}
      {messages.map(msg => (
        <div key={msg.id}>{msg.message}</div>
      ))}
      
      {/* Streaming indicator */}
      {streaming && (
        <div className="streaming">
          {streamingText}
          <button onClick={stopStreaming}>Stop</button>
        </div>
      )}
      
      {/* Quick Actions */}
      {quickActions.map(action => (
        <button key={action.action} onClick={() => handleAction(action)}>
          {action.label}
        </button>
      ))}
      
      {/* Personality Selector */}
      <select value={personality} onChange={e => changePersonality(e.target.value)}>
        <option value="professional">Professional</option>
        <option value="friendly">Friendly</option>
        <option value="expert">Expert</option>
        <option value="concise">Concise</option>
      </select>
    </div>
  );
}
```

---

## Testing

### Test LLM Status

```bash
curl http://localhost:8000/api/chatbot/status
```

Expected response:
```json
{
  "success": true,
  "data": {
    "llm": {
      "claude": true,
      "openai": false,
      "ollama": false,
      "available_provider": "claude"
    },
    "streaming_supported": true,
    "features": {
      "streaming": true,
      "websocket": true,
      "memory": true,
      "rag": true,
      "smart_suggestions": true
    }
  }
}
```

### Test Streaming

```bash
curl -X POST http://localhost:8000/api/chatbot/stream \
  -H "Content-Type: application/json" \
  -H "Accept: text/event-stream" \
  -d '{"message": "Hello", "stream": true}'
```

---

## Cost Optimization

### LLM Provider Costs (Approximate)

| Provider | Model | Cost per 1K tokens |
|----------|-------|-------------------|
| Claude | claude-3-sonnet | ~$0.003 input, $0.015 output |
| OpenAI | gpt-4o | ~$0.005 input, $0.015 output |
| OpenAI | gpt-4o-mini | ~$0.00015 input, $0.0006 output |
| Mistral | mistral-large | ~$0.004 input, $0.012 output |
| Ollama | mistral (local) | Free (your hardware) |

### Optimization Tips

1. **Use fast models for simple queries**: Configure model tiers
2. **Enable caching**: Similar questions return cached responses
3. **Limit context**: Trim conversation history to essentials
4. **Use Ollama for development**: Free local inference

---

## Troubleshooting

### "No LLM provider available"
- Check API keys in `.env`
- Verify API key is valid (test with curl)
- Check `php artisan config:clear`

### Streaming not working
- Ensure output buffering is disabled
- Check for reverse proxy buffering (nginx: `proxy_buffering off;`)
- Verify `text/event-stream` content type

### WebSocket not connecting
- Check Pusher/Reverb credentials
- Verify CORS settings
- Check browser console for errors

### Embeddings not generating
- Verify OpenAI/Voyage API key
- Check if tables exist (`chatbot_knowledge_base`)
- Test with `php artisan tinker`: `app(EmbeddingService::class)->isAvailable()`

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend                              │
│  ┌──────────────────┐  ┌──────────────────┐                │
│  │  ChatbotModal    │  │ useStreaming     │                │
│  │                  │──│ Chatbot Hook     │                │
│  └──────────────────┘  └──────────────────┘                │
│           │                    │                            │
│           │ HTTP/SSE           │ WebSocket                  │
└───────────┼────────────────────┼────────────────────────────┘
            │                    │
┌───────────▼────────────────────▼────────────────────────────┐
│                        Backend                               │
│  ┌──────────────────┐  ┌──────────────────┐                │
│  │ ChatbotStream    │  │ Broadcasting     │                │
│  │ Controller       │  │ (Pusher/Reverb)  │                │
│  └────────┬─────────┘  └──────────────────┘                │
│           │                                                 │
│  ┌────────▼─────────┐  ┌──────────────────┐                │
│  │ AdvancedLLM      │  │ Memory           │                │
│  │ Service          │──│ Service          │                │
│  └────────┬─────────┘  └──────────────────┘                │
│           │                                                 │
│  ┌────────▼─────────┐  ┌──────────────────┐                │
│  │ Claude/OpenAI/   │  │ Embedding        │                │
│  │ Mistral/Ollama   │  │ Service (RAG)    │                │
│  └──────────────────┘  └──────────────────┘                │
└─────────────────────────────────────────────────────────────┘
```

---

## Next Steps

1. **Add more knowledge**: Index more FAQs, policies, help articles
2. **Fine-tune prompts**: Adjust system prompts for your business
3. **Monitor usage**: Track costs and usage patterns
4. **A/B test personalities**: See which users prefer
5. **Add human handoff**: Escalate complex issues to human agents
