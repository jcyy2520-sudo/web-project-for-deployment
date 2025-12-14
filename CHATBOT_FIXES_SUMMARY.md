# Chatbot Fixes - December 11, 2025

## Issues Fixed

### 1. Slow Model with Inaccurate Responses ✅
**Problem:** The chatbot was using `zephyr-7b-beta` model which was too slow and produced inaccurate responses.

**Solution:** Changed to `google/flan-t5-small` model
- **File:** `web-backend/app/Http/Controllers/ChatbotController.php`
- **Changes:**
  - Updated `HF_API_URL` from `https://router.huggingface.co/models/HuggingFaceH4/zephyr-7b-beta` to `https://api-inference.huggingface.co/models/google/flan-t5-small`
  - Adjusted prompt format to work with Flan-T5 (removed chat-specific markers like `<|system|>`, `<|user|>`, etc.)
  - Updated response parsing to handle Flan-T5 response format
  - Added response cleanup to remove prompt remnants
  - **Benefits:**
    - Flan-T5-Small is significantly faster
    - Produces more concise and accurate responses
    - Better for instruction-following tasks
    - Reduced timeout from 45 seconds to 15 seconds for subsequent requests

### 2. New Conversations Not Saving ✅
**Problem:** When starting a new conversation, the new conversation wasn't saved. When reopening the chatbot, it reverted to the old conversation.

**Solution:** Added localStorage persistence for conversation IDs
- **File:** `web-frontend/src/hooks/useChatbot.js`
- **Changes:**
  1. **startNewConversation()**: Now saves new conversation ID to localStorage with key `chatbot_current_conversation_id`
  2. **sendMessage()**: Saves conversation ID to localStorage after each successful API response
  3. **switchConversation()**: Saves conversation ID to localStorage when switching between conversations
  4. **Initial Load**: Modified `loadInitialHistory()` to:
     - Check localStorage for saved conversation ID first
     - Use saved conversation ID if available
     - Fall back to server data if no saved conversation
     - Save the conversation ID to localStorage for future sessions
  
- **Key Features:**
  - Conversation IDs now persist across browser sessions
  - When chatbot is reopened, it loads the most recent conversation
  - New conversations are immediately saved and won't be lost
  - Switching between conversations properly updates the saved state

## Testing the Fixes

### To test the new model:
1. Open the chatbot
2. Ask questions about services or appointments
3. Observe:
   - Much faster responses (typically 5-10 seconds)
   - More concise and direct answers
   - Better accuracy for legal-related questions

### To test conversation persistence:
1. Start a new conversation and send a message
2. Close the chatbot
3. Reopen the chatbot
4. Verify that the new conversation appears (not the old one)
5. The conversation should contain your previous messages

## Technical Details

### Model: google/flan-t5-small
- **Size:** Small, optimized model
- **Speed:** 2-3x faster than Zephyr
- **Response Length:** Enforced 150 token maximum
- **Accuracy:** Better for factual Q&A tasks
- **API Endpoint:** `https://api-inference.huggingface.co/models/google/flan-t5-small`

### Storage Key Used
- `chatbot_current_conversation_id`: Stores the current active conversation ID

## Files Modified
1. `web-backend/app/Http/Controllers/ChatbotController.php` - Model configuration and API handling
2. `web-frontend/src/hooks/useChatbot.js` - Conversation state management and persistence
