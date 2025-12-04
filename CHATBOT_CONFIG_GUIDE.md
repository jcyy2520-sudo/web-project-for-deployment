# Chatbot Environment Configuration

## Backend Environment Variables

The chatbot uses the Hugging Face token stored securely in the Laravel backend. No additional environment variables are required, but here are the current settings:

### Current Configuration

**Hugging Face Token**: Configure using environment variables (see below)
```
Model: google/flan-t5-small
```

### Security Best Practices

#### ⚠️ IMPORTANT: Secure Your Token

The current implementation stores the token directly in the controller. For production, consider:

**Option 1: Move to Environment Variable (Recommended)**

1. Add to your `.env` file:
```env
HUGGINGFACE_API_KEY=your_hugging_face_token_here
HUGGINGFACE_API_URL=https://api-inference.huggingface.co/models/google/flan-t5-small
```

2. Update `ChatbotController.php`:
```php
private const HF_API_URL = null; // Will use config
private const HF_TOKEN = null;   // Will use config

// In methods, use:
$token = config('services.huggingface.api_key');
$url = config('services.huggingface.api_url');
```

3. Add to `config/services.php`:
```php
'huggingface' => [
    'api_key' => env('HUGGINGFACE_API_KEY'),
    'api_url' => env('HUGGINGFACE_API_URL', 'https://api-inference.huggingface.co/models/google/flan-t5-small'),
],
```

**Option 2: Move to Config File**

1. Create `config/chatbot.php`:
```php
return [
    'hf_api_url' => env('HUGGINGFACE_API_URL', 'https://api-inference.huggingface.co/models/google/flan-t5-small'),
    'hf_token' => env('HUGGINGFACE_API_KEY'),
    'max_history' => 10,
    'system_prompt' => env('CHATBOT_SYSTEM_PROMPT', 'You are a helpful customer support assistant...'),
];
```

### Frontend Environment Variables

The frontend doesn't need any chatbot-specific environment variables. All API calls use the existing API URL configuration in `AuthContext.jsx`.

## Database Configuration

Ensure your `.env` file has proper database configuration:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=root
DB_PASSWORD=
```

## Testing the Configuration

### Test Backend Connectivity

```bash
# Navigate to backend
cd web-backend

# Test database connection
php artisan tinker
>>> DB::connection()->getPDO()
>>> exit
```

### Test Hugging Face API

```bash
# Using curl
curl -X POST "https://api-inference.huggingface.co/models/google/flan-t5-small" \
  -H "Authorization: Bearer YOUR_HUGGING_FACE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"inputs":"What is 2+2?"}'
```

### Test Full Stack

After running migrations:

1. Login to your application
2. Open browser DevTools (F12)
3. Go to Network tab
4. Open chat and send a message
5. Look for `/api/chatbot/send-message` request
6. Should see 200 status with AI response

## Deployment Considerations

### For Vercel/Production:

1. **Environment Variables**
   - Set `HUGGINGFACE_API_KEY` in your hosting platform
   - Ensure it's not committed to version control

2. **Database Migrations**
   - Run migrations on your production database:
   ```bash
   php artisan migrate --force
   ```

3. **CORS Configuration**
   - Verify frontend domain is allowed in `config/cors.php`

4. **API Rate Limiting**
   - Consider adding rate limiting for chatbot routes
   - Add to `routes/api.php`:
   ```php
   Route::middleware(['throttle:30,1'])->prefix('chatbot')->group(...)
   ```

## Troubleshooting Configuration

### Issue: Database connection error
```
Check: DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD in .env
Restart: php artisan migrate
```

### Issue: 401 Unauthorized from Hugging Face
```
Check: Token is correct
Verify: Token still valid on huggingface.co
Solution: Generate new token if expired
```

### Issue: CORS errors
```
Check: Frontend domain in config/cors.php
Verify: Credentials are included in requests
```

### Issue: Timeout errors
```
Check: Hugging Face API status
Increase: Timeout in ChatbotController.php (currently 30s)
Consider: Using a faster model
```

## Model Configuration

### Switching Models

The chatbot currently uses `google/flan-t5-small`. Here are alternatives:

**Faster Options:**
```php
// Smaller, faster responses
'distilgpt2' // ~100 tokens, ~500ms
'gpt2'       // ~124 tokens, ~800ms

// Update in ChatbotController.php:
private const HF_API_URL = 'https://api-inference.huggingface.co/models/distilgpt2';
```

**Better Quality Options:**
```php
// Larger, better quality
'google/flan-t5-base'    // ~250M params
'google/flan-t5-large'   // ~780M params (slower)

// Update in ChatbotController.php:
private const HF_API_URL = 'https://api-inference.huggingface.co/models/google/flan-t5-base';
```

## Monitoring & Logging

### View Chat Logs
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Filter for chatbot errors
grep -i "chatbot\|huggingface" storage/logs/laravel.log
```

### Database Query Logging
Enable in `config/database.php`:
```php
'log_queries' => env('DB_LOG_QUERIES', false),
'log_slow_queries' => 2000, // 2 seconds
```

## Performance Optimization

### Caching Suggestions

```php
// Cache conversation summaries
Cache::remember("conversation_{$userId}", 3600, function() {
    return ChatMessage::where('user_id', $userId)->get();
});

// Cache system prompt
Cache::rememberForever('chatbot_system_prompt', function() {
    return config('chatbot.system_prompt');
});
```

### Database Optimization

```sql
-- Monitor chat_messages table growth
SELECT COUNT(*) FROM chat_messages;

-- Archive old messages (optional)
DELETE FROM chat_messages 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Add indexes if needed
ALTER TABLE chat_messages ADD INDEX idx_user_date (user_id, created_at);
```

---

**Last Updated**: December 4, 2025
**Status**: Ready for Production (with recommendations implemented)
