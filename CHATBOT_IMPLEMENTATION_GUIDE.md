# Smart Role-Aware Chatbot Implementation

## Overview

Your chatbot system has been significantly improved with advanced features for real-time, role-aware, intelligent responses. The system now:

- **Detects user roles** (Client, Admin, Cashier, Guest) automatically
- **Analyzes user intent** using advanced NLU with fuzzy matching
- **Extracts entities** from messages (appointment IDs, dates, amounts, etc.)
- **Fetches real-time data** without fabrication
- **Builds smart responses** tailored to the user's role and context
- **Enforces security** with role-based permission checking

## Architecture

### Core Services

#### 1. **ChatbotRoleAwarenessService**
Detects and manages user roles with detailed capabilities and permissions.

```php
$roleService = app(ChatbotRoleAwarenessService::class);
$roleInfo = $roleService->detectUserRole($userId);

// Returns:
$roleInfo = [
    'user_id' => 123,
    'primary_role' => 'admin', // 'client', 'admin', 'cashier', 'guest'
    'display_name' => 'Administrator',
    'is_authenticated' => true,
    'capabilities' => [ /* role-specific capabilities */ ],
    'role_description' => 'System administrator...',
    'context_hints' => [ /* tailored hints for responses */ ],
];

// Check permissions
$canApproveAppointment = $roleService->canPerformAction($userId, 'approve_appointment');
$canViewPayments = $roleService->canViewResource($userId, 'payments');
```

**Supported Roles:**
- **Client**: View own appointments, check payment status, request refunds, book appointments
- **Admin**: Full system control, manage appointments, view analytics, manage users
- **Cashier**: Handle payments, process refunds, generate shift reports, verify receipts
- **Guest**: View public services, schedules, register

#### 2. **ChatbotNLUService**
Advanced Natural Language Understanding with intent recognition, entity extraction, and sentiment analysis.

```php
$nluService = app(ChatbotNLUService::class);

// Detect intent
$intentData = $nluService->detectIntent('I want to cancel my appointment');
// Returns: intent='cancel_appointment', confidence=0.95

// Extract entities
$entities = $nluService->extractEntities('I need appointment #123 rescheduled to 2024-12-15 at 14:00');
// Returns: ['appointment_id' => 123, 'date' => [...], 'time' => [...]]

// Analyze sentiment
$sentiment = $nluService->analyzeSentiment('I\'m really frustrated with this service');
// Returns: ['sentiment' => 'negative', 'score' => 5, 'has_urgency' => false]

// Get clarification questions
$questions = $nluService->buildClarificationQuestions('cancel_appointment', $entities);
// Returns: ["Which appointment would you like to cancel? Please provide the appointment ID."]
```

**Intent Categories:**
- Appointment management (view, check status, book, cancel, reschedule)
- Payment operations (view, check status, process)
- Refund handling (request, check status, view)
- Service inquiries (details, pricing, availability)
- Admin functions (pending items, approval, completion)
- Cashier operations (shift reports, payment verification)
- System information (status, help)

#### 3. **ChatbotRealTimeDataService**
Fetches real-time system data with intelligent caching. Never fabricates data.

```php
$dataService = app(ChatbotRealTimeDataService::class);

// Get user appointments
$appointments = $dataService->getUserAppointments($userId, 'pending');
// Always returns ACTUAL data from database, never assumes

// Get pending items (admin/cashier view)
$pending = $dataService->getPendingAppointments(50);
$payments = $dataService->getPendingPayments(50);
$refunds = $dataService->getPendingRefunds(50);

// Get appointment details
$appointment = $dataService->getAppointmentDetails($appointmentId);

// Get service and availability information
$services = $dataService->getAvailableServices();
$hours = $dataService->getBusinessHours();
$availability = $dataService->getDateAvailability('2024-12-15');

// Get system status
$status = $dataService->getSystemStatus();

// Clear relevant caches (call after data modifications)
$dataService->clearCache('appointments');
```

**Data Caching Strategy:**
- Standard data (5 minutes): Appointments, services, business hours
- Critical data (1 minute): Pending items, payment status
- No cache for: User-specific sensitive data

#### 4. **ChatbotSmartResponseBuilder**
Builds contextual, role-aware, intelligent responses based on intent, entities, role, and sentiment.

```php
$responseBuilder = app(ChatbotSmartResponseBuilder::class);

$context = [
    'intent' => 'view_appointments',
    'user_id' => 123,
    'role_info' => $roleInfo,
    'entities' => $entities,
    'sentiment' => $sentiment,
    'message' => 'Show my appointments',
];

$response = $responseBuilder->build($context);
// Returns:
$response = [
    'response' => 'You have 3 appointments:\n• 2024-12-15 at 10:00 - Notary Service (Status: APPROVED)...',
    'metadata' => [
        'intent' => 'view_appointments',
        'role' => 'client',
        'is_authenticated' => true,
        'timestamp' => '2024-12-11T10:30:00Z',
        'entities_used' => 0,
        'data_sources' => ['appointments_table'],
    ],
];
```

**Response Types:**
- Data display (formatted lists, summaries)
- Action confirmations (with details)
- Clarification requests (when ambiguous)
- Error messages (when permission denied or data not found)
- Role-specific guidance

### Integration in Controller

The `ChatbotController` now uses the new services in this workflow:

```
1. Validate input & rate limiting
   ↓
2. Detect user role (RoleAwarenessService)
   ↓
3. Analyze message (NLUService)
   - Detect intent
   - Extract entities
   - Analyze sentiment
   ↓
4. Fetch real-time data (RealTimeDataService)
   ↓
5. Build response (SmartResponseBuilder)
   - Role-aware content
   - Data-driven facts
   - Smart follow-ups
   ↓
6. Log analytics
   ↓
7. Return response with metadata
```

## Usage Examples

### Example 1: Client Viewing Appointments

**Request:**
```json
{
  "message": "Show my apts",
  "conversation_id": "chat_123_1234567890_abc123"
}
```

**Processing:**
1. Role detected: `client`
2. Intent detected: `view_appointments` (fuzzy match handles "apts")
3. Real-time data fetched: 5 appointments
4. Response built with actual data

**Response:**
```json
{
  "success": true,
  "user_message": "Show my apts",
  "ai_response": "You have **5 appointments**:\n• **2024-12-15 at 10:00** - Notary Service (Status: APPROVED)...",
  "meta": {
    "role": "client",
    "intent": "view_appointments",
    "intent_confidence": 0.92,
    "sentiment": "neutral",
    "response_time_ms": 145
  }
}
```

### Example 2: Admin Approving Appointment

**Request:**
```json
{
  "message": "approve appointment #15",
  "conversation_id": "chat_456_1234567890_def456"
}
```

**Processing:**
1. Role detected: `admin`
2. Intent detected: `approve_appointment`
3. Entity extracted: `appointment_id: 15`
4. Permission check: `admin` can perform `approve_appointment` ✅
5. Response built with action confirmation

**Response:**
```json
{
  "success": true,
  "user_message": "approve appointment #15",
  "ai_response": "Perfect! I found appointment #15:\n• **User**: John Doe\n• **Date**: 2024-12-16 at 14:00\n• **Service**: Notary Service\n\nPlease confirm by replying **yes** to approve.",
  "meta": {
    "role": "admin",
    "intent": "approve_appointment",
    "entities_found": 1
  }
}
```

### Example 3: Fuzzy Intent Matching

**Request:**
```json
{
  "message": "I ned too cancul my apointmnt",
  "conversation_id": "chat_789..."
}
```

**Processing:**
1. NLU corrects: "apointmnt" → "appointment"
2. Fuzzy matching detects: `cancel_appointment` (90% confidence)
3. Entity extraction detects intent to identify which appointment
4. Response asks for clarification

**Response:**
```json
{
  "success": true,
  "ai_response": "I understand you want to cancel an appointment. Which appointment would you like to cancel? Please provide the appointment ID or the date.",
  "meta": {
    "intent": "cancel_appointment",
    "intent_confidence": 0.90,
    "requires_clarification": true
  }
}
```

### Example 4: Sentiment-Aware Response

**Request:**
```json
{
  "message": "I'm really angry! This refund is taking forever! HELP ME NOW!",
  "conversation_id": "chat_999..."
}
```

**Processing:**
1. Sentiment analysis: `negative`, score 5/5, urgency keywords detected
2. Response flagged as priority
3. Empathetic tone applied

**Response:**
```json
{
  "success": true,
  "ai_response": "I completely understand your frustration—this is important. Let me check your refund status right away...",
  "meta": {
    "sentiment": "negative",
    "sentiment_score": 5,
    "requires_priority_attention": true,
    "tone_adjustment": "empathetic"
  }
}
```

## Role-Specific Behavior

### For Clients
- View only their own appointments
- Check payment status
- Request refunds
- Book appointments
- No access to admin functions

### For Admins
- View all appointments, payments, refunds
- Approve/decline/complete appointments
- View system analytics
- Manage settings
- Full system access

### For Cashiers
- View pending payments
- Process payments
- View refund requests
- Approve/process refunds
- Generate shift reports
- Cannot access user management or settings

### For Guests
- View public services
- Check business hours
- View availability
- Prompted to register

## Security Features

**Role-Based Permission Checking**
```php
// Example: Only cashiers can process payments
if (!$roleService->canPerformAction($userId, 'process_payments')) {
    return response('Unauthorized', 403);
}
```

**Resource Access Control**
```php
// Check if user can view specific resource type
$canView = $roleService->canViewResource($userId, 'payments', 'own');
if (!$canView) {
    return response('Cannot access this resource', 403);
}
```

**Logged Actions**
All interactions are logged with:
- User ID and role
- Intent and sentiment
- Entities extracted
- Response time
- Success/failure status
- Permission checks

## Advanced Features

### Entity Extraction
Automatically detects and extracts:
- **Appointment IDs**: `#123`, `appointment 456`
- **Dates**: `2024-12-15`, `12/15/24`, `next Friday`
- **Times**: `14:00`, `2:30 PM`, `in 30 minutes`
- **Amounts**: `$100`, `100 PHP`, `₱500.50`
- **Statuses**: `pending`, `approved`, `completed`, `cancelled`
- **Services**: `notary`, `affidavit`, `apostille`

### Sentiment Analysis
Analyzes user sentiment and urgency:
- **Positive**: "Thank you", "Love your service"
- **Negative**: "Angry", "Frustrated", "Disappointed"
- **Neutral**: General inquiries
- **Urgency**: "ASAP", "Emergency", "Critical"

### Fuzzy Matching
Handles:
- Typos: "apointmnt" → "appointment"
- Slang: "apts" → "appointments"
- Abbreviations: "refund req" → "request refund"
- Informal language: "wanna book" → "want to book"

### Intelligent Caching
- Balances performance and data freshness
- Different TTL for different data types
- Automatic cache invalidation after modifications

## Configuration

### Environment Variables
```env
CHATBOT_ADMIN_USER_ID=1          # Pin admin user for messages
HUGGINGFACE_API_KEY=hf_...       # AI fallback (optional)
```

### Database Requirements
Ensure these tables exist:
- `users` - User roles and authentication
- `appointments` - Appointment data
- `payments` - Payment records
- `refunds` - Refund requests
- `services` - Service catalog
- `appointment_settings` - Business hours, settings
- `chat_messages` - Conversation history
- `chatbot_analytics` - Analytics/logging

## Testing

### Unit Tests
Test individual services:
```bash
php artisan test tests/Unit/ChatbotRoleAwarenessServiceTest.php
php artisan test tests/Unit/ChatbotNLUServiceTest.php
php artisan test tests/Unit/ChatbotRealTimeDataServiceTest.php
```

### Integration Tests
Test the complete flow:
```bash
php artisan test tests/Feature/ChatbotControllerTest.php
```

### Manual Testing Examples

**Test 1: Role Detection**
```php
$service = app(ChatbotRoleAwarenessService::class);
$adminRole = $service->detectUserRole(1); // Admin user
$clientRole = $service->detectUserRole(2); // Client user
$guestRole = $service->detectUserRole(null); // Guest
```

**Test 2: Intent Detection**
```php
$nlu = app(ChatbotNLUService::class);
$intent = $nlu->detectIntent("Cancel my appointment");
$entities = $nlu->extractEntities("I need appointment #123 on 2024-12-15");
```

**Test 3: Real-Time Data**
```php
$data = app(ChatbotRealTimeDataService::class);
$appointments = $data->getUserAppointments(1);
$pending = $data->getPendingAppointments();
```

**Test 4: Smart Response**
```php
$builder = app(ChatbotSmartResponseBuilder::class);
$response = $builder->build([
    'intent' => 'view_appointments',
    'user_id' => 1,
    'role_info' => [...],
    'entities' => [],
    'sentiment' => ['sentiment' => 'neutral'],
    'message' => 'Show my appointments',
]);
```

## Troubleshooting

### Issue: "No role detected"
**Cause**: User not found or invalid user ID
**Solution**: Ensure user exists in database and is active

### Issue: "Intent not detected"
**Cause**: Message too ambiguous or unique phrasing
**Solution**: System defaults to general question; user gets clarification request

### Issue: "No data returned"
**Cause**: No matching records in database
**Solution**: System correctly returns "no data" rather than fabricating

### Issue: "Permission denied"
**Cause**: User role doesn't allow the action
**Solution**: User gets clear message about why action is unavailable

### Issue: "Cache stale"
**Cause**: Data modified but cache not cleared
**Solution**: Call `$dataService->clearCache('type')` after modifications

## Future Enhancements

1. **Action Execution**: Actually execute actions (approve, process, etc.) with user confirmation
2. **Multi-language**: Full translation support beyond English
3. **Learning**: Track failed intents and improve over time
4. **Integration**: Webhook support for external systems
5. **Analytics Dashboard**: Visual analytics of chatbot interactions
6. **A/B Testing**: Test different response styles
7. **Custom Intents**: Admin-defined custom intents and responses

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Enable debug mode in `.env`: `APP_DEBUG=true`
3. Review this documentation
4. Check test files for usage examples

---

**Implementation Date**: December 2024
**Chatbot Version**: 2.0 (Role-Aware, Real-Time)
