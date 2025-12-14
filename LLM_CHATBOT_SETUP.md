# Smart LLM-Powered Chatbot Setup Guide

## Overview
Your chatbot has been upgraded with an intelligent LLM backend that provides:
- **Real semantic understanding** (not just pattern matching)
- **Multi-turn context awareness**
- **Role-specific responses**
- **Automatic fallback system**
- **Support for Claude API (primary) and Ollama (self-hosted)**

## Quick Start

### Option 1: Claude API (Recommended - Best Intelligence)

#### Requirements:
- Anthropic API Key (from https://console.anthropic.com)
- Free tier: 5 requests/month (upgrade for production)

#### Setup:

1. **Get your API key:**
   - Go to https://console.anthropic.com/account/keys
   - Create a new API key
   - Copy it

2. **Add to .env file:**
   ```env
   ANTHROPIC_API_KEY=sk-ant-xxxxx
   USE_OLLAMA_LLM=false
   ```

3. **Test:**
   ```bash
   curl http://localhost:8000/api/chatbot/send-message \
     -X POST \
     -H "Content-Type: application/json" \
     -d '{"message": "What services do you offer?"}'
   ```

**Claude Model Selected:** `claude-3-sonnet-20240229`
- Speed: 5-15 seconds (fast)
- Intelligence: Excellent (understands legal context)
- Cost: ~$0.003 per request (at scale)
- Context: 200K tokens (very long conversations)

---

### Option 2: Ollama (Self-Hosted - Free)

#### Requirements:
- Ollama installed: https://ollama.ai
- ~7GB disk space for Mistral 7B
- ~8GB RAM recommended

#### Setup:

1. **Install Ollama:**
   ```bash
   # Windows: Download from https://ollama.ai
   # Mac: brew install ollama
   # Linux: curl https://ollama.ai/install.sh | sh
   ```

2. **Pull Mistral model:**
   ```bash
   ollama pull mistral
   ```

3. **Start Ollama (runs in background):**
   ```bash
   ollama serve
   ```
   - Listens on `http://localhost:11434` by default

4. **Add to .env file:**
   ```env
   USE_OLLAMA_LLM=true
   ANTHROPIC_API_KEY=  # Leave empty to skip Claude
   ```

5. **Test:**
   ```bash
   # First make sure Ollama is running
   curl http://localhost:11434/api/tags
   
   # Then test chatbot
   curl http://localhost:8000/api/chatbot/send-message \
     -X POST \
     -H "Content-Type: application/json" \
     -d '{"message": "What services do you offer?"}'
   ```

**Ollama Model Selected:** `mistral` (7B parameters)
- Speed: 20-60 seconds (slower but acceptable)
- Intelligence: Very good (better than Flan-T5)
- Cost: Free
- Context: 32K tokens (good for most conversations)

---

## How It Works

### Request Flow:

```
User Message
    ↓
ChatbotController::sendMessage()
    ↓
1. Detect intent & extract entities (NLU Service)
    ↓
2. Try SmartResponseBuilder (template-based for known intents)
    ↓
3. If confidence low or generic fallback:
       └→ Use LLM (Claude or Ollama)
           - Pass full conversation history
           - Include system context
           - Generate intelligent response
    ↓
4. Save to database & return response
```

### When LLM is Used:

✅ **Used for:**
- General questions ("Tell me about your services")
- Follow-up questions requiring context
- Low-confidence intents (< 60%)
- Questions that don't match known patterns

❌ **NOT used for:**
- Action execution (approve appointment, process payment)
- Appointment lookup (uses real-time data)
- High-confidence intents (90%+)

---

## Configuration

### Environment Variables

```env
# AI Provider
ANTHROPIC_API_KEY=sk-ant-xxxxx          # Claude API key (optional)
USE_OLLAMA_LLM=false                     # true = use Ollama, false = use Claude

# Ollama Settings (if using Ollama)
OLLAMA_API_URL=http://localhost:11434    # Ollama server URL
OLLAMA_MODEL=mistral                     # mistral, llama2, neural-chat, etc.

# LLM Settings
LLM_MAX_TOKENS=1024                      # Max response length
LLM_TEMPERATURE=0.7                      # Creativity (0.0-1.0)
LLM_REQUEST_TIMEOUT=30                   # Seconds
```

### Laravel Service Container

Services are auto-registered in `config/app.php`. If not present, add:

```php
'providers' => [
    // ... existing providers
    App\Providers\ChatbotServiceProvider::class,
],
```

---

## Testing

### Test LLM Status

```bash
curl http://localhost:8000/api/chatbot/capabilities
```

Response:
```json
{
  "success": true,
  "llm_available": true,
  "claude_available": true,
  "ollama_available": false,
  "primary_provider": "claude"
}
```

### Test with General Question

```bash
curl http://localhost:8000/api/chatbot/send-message \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "message": "I have a legal issue, can you explain what happens after my appointment?",
    "conversation_id": "test_123"
  }'
```

Expected response source: `llm` with reasoning about the process.

### Test with Known Intent

```bash
curl http://localhost:8000/api/chatbot/send-message \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What services do you offer?",
    "conversation_id": "test_123"
  }'
```

Expected response source: `realtime_data` (uses template with real data).

---

## Monitoring & Debugging

### Enable Debug Logging

In `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### View Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Watch for LLM calls
grep "LLMService\|llm_provider" storage/logs/laravel.log

# Check Claude health
php artisan tinker
>>> app(\App\Services\LLMService::class)->healthCheck()
```

### Common Issues

**Issue:** "LLM service unavailable"
- **Solution:** Check API key is correct in .env
- **Check:** `php artisan env:check`

**Issue:** Responses are slow (30+ seconds)
- **Possible cause:** Using Ollama, model is slow
- **Solution:** Use Claude instead, or upgrade hardware

**Issue:** "Ollama connection refused"
- **Solution:** Start Ollama: `ollama serve`
- **Check:** `curl http://localhost:11434/api/tags`

---

## Response Metadata

Every chatbot response includes metadata showing which provider was used:

```json
{
  "success": true,
  "ai_response": "...",
  "meta": {
    "source": "llm",           // or "realtime_data", "smart_builder", "fallback"
    "llm_provider": "claude",   // or "ollama"
    "llm_model": "claude-3-sonnet-20240229",
    "tokens_used": 156,
    "intent": "general_question",
    "intent_confidence": 0.45   // Low confidence triggered LLM
  }
}
```

---

## Performance Metrics

### Response Times

| Provider | Model | Speed | Quality |
|----------|-------|-------|---------|
| Claude | claude-3-sonnet | 5-15s | Excellent |
| Ollama | mistral | 20-60s | Very Good |
| Template | - | <100ms | Good (if matched) |

### Cost Estimation (Claude)

- Per request: ~$0.003 (at scale)
- 1000 requests: ~$3
- 10,000 requests: ~$30

---

## Migration from Flan-T5

**What changed:**
- ✅ Replaced `google/flan-t5-small` with Claude or Mistral
- ✅ Added context-aware responses
- ✅ Improved accuracy for legal domain
- ✅ Added conversation history support
- ✅ Better handling of complex questions
- ❌ Response time is slower (but more accurate)

**No breaking changes:**
- Same API endpoints
- Same response format
- Backward compatible

---

## Next Steps

1. **Choose your provider** (Claude or Ollama)
2. **Set up environment variables**
3. **Restart Laravel:** `php artisan optimize:clear`
4. **Test the chatbot**
5. **Monitor logs and adjust settings**

For production deployment:
- Use Claude API (paid tier)
- Enable caching for repeat questions
- Set up rate limiting
- Monitor costs and performance

---

## Support

- **Anthropic Docs:** https://docs.anthropic.com
- **Ollama Docs:** https://github.com/ollama/ollama
- **Your Laravel Logs:** `storage/logs/laravel.log`
