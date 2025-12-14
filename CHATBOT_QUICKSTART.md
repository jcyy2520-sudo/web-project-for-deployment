# Smart Chatbot - Quick Start Guide

## What's New

Your chatbot is now **intelligent, role-aware, and real-time**. Here's what changed:

### Key Improvements

✅ **Role-Based Responses** - Different behavior for Users, Admins, Cashiers
✅ **Real-Time Data** - Always uses actual database data, never guesses
✅ **Smart Intent Detection** - Understands user intent even with typos
✅ **Fuzzy Matching** - Handles misspellings: "apointmnt" → "appointment"
✅ **Entity Extraction** - Automatically finds IDs, dates, amounts
✅ **Sentiment Analysis** - Detects mood and urgency
✅ **Permission Checking** - Enforces role-based access control
✅ **Intelligent Caching** - Balances performance and data freshness

## Core Services (New)

### 1. ChatbotRoleAwarenessService
**Purpose**: Detect user role and manage permissions

```php
$roleService = app(ChatbotRoleAwarenessService::class);

// Get role information
$roleInfo = $roleService->detectUserRole($userId);
// Returns: primary_role, capabilities, permissions, etc.

// Check if user can perform action
$canApprove = $roleService->canPerformAction($userId, 'approve_appointment');

// Check if user can view resource
$canView = $roleService->canViewResource($userId, 'payments');
```

### 2. ChatbotNLUService
**Purpose**: Understand user intent and extract information

```php
$nluService = app(ChatbotNLUService::class);

// Detect what user is trying to do
$intent = $nluService->detectIntent('Cancel my appointment');
// Returns: intent='cancel_appointment', confidence=0.95

// Extract specific information
$entities = $nluService->extractEntities('I need #123 rescheduled');
// Returns: ['appointment_id' => 123]

// Understand mood
$sentiment = $nluService->analyzeSentiment('I am really frustrated!');
// Returns: sentiment='negative', score=5/5, has_urgency=true
```

### 3. ChatbotRealTimeDataService
**Purpose**: Fetch actual data from database (never fabricates)

```php
$dataService = app(ChatbotRealTimeDataService::class);

// Get USER'S appointments
$appointments = $dataService->getUserAppointments($userId);

// Get PENDING appointments (admin view)
$pending = $dataService->getPendingAppointments();

// Get services
$services = $dataService->getAvailableServices();

// Check business hours
$hours = $dataService->getBusinessHours();
```

### 4. ChatbotSmartResponseBuilder
**Purpose**: Build intelligent responses based on context

```php
$builder = app(ChatbotSmartResponseBuilder::class);

$response = $builder->build([
    'intent' => 'view_appointments',
    'user_id' => 123,
    'role_info' => $roleInfo,
    'entities' => $entities,
    'sentiment' => $sentiment,
]);

// Returns: formatted response tailored to user's role
```

## How It Works

### The Chatbot Flow

```
User sends message
    ↓
1️⃣ System detects user's ROLE (Client, Admin, Cashier, Guest)
    ↓
2️⃣ System analyzes user's MESSAGE
   - What do they want? (Intent: view appointments, approve, etc.)
   - What are they talking about? (Entities: appointment #123, date 2024-12-15)
   - How do they feel? (Sentiment: angry, frustrated, neutral)
    ↓
3️⃣ System fetches REAL DATA from database
   - No guessing, no made-up numbers
   - Only what actually exists in the system
    ↓
4️⃣ System checks PERMISSIONS
   - Can this user do what they're asking?
   - Client can only see their own appointments
   - Admin can see everything
    ↓
5️⃣ System builds SMART RESPONSE
   - Tailored to user's role
   - Based on actual data
   - Clear next steps
    ↓
Response sent with metadata
```

## Examples

### Example 1: Client with Typo

```
User:     "Show my apts"
System:   Detects "apts" = "appointments" (fuzzy matching)
Detects:  Role=Client, Intent=view_appointments
Fetches:  User's 5 actual appointments
Response: "You have 5 appointments: [list of real data]"
```

### Example 2: Admin Approving

```
User:     "Approve appointment #15"
System:   Detects role=Admin, Intent=approve_appointment, Entity=ID:15
Checks:   ✅ Admin can approve (permission check)
Fetches:  Real appointment #15 data
Response: "Found appointment #15. Ready to approve?  [details shown]"
```

### Example 3: Cashier with Frustrated Tone

```
User:     "I NEED TO PROCESS PAYMENT NOW!!!"
System:   Sentiment=ANGRY (negative 5/5), Urgency=HIGH
Detects:  Role=Cashier, Intent=process_payment
Response: "I understand this is urgent! [empathetic response]"
          [shows pending payments]
```

### Example 4: Guest User

```
User:     "What services do you offer?"
System:   Detects role=Guest (not logged in)
Fetches:  Public services list
Response: "We offer: Notary, Affidavit... [details]"
          "Ready to book? Please register first!"
```

## Supported Intents

### For All Users
- `view_services` - See available services
- `service_details` - Learn about a service
- `service_pricing` - Check prices
- `view_availability` - See time slots
- `business_hours` - When are you open?
- `help` - What can you do?

### For Clients
- `view_appointments` - My bookings
- `check_appointment_status` - Status check
- `book_appointment` - Make booking
- `cancel_appointment` - Cancel booking
- `reschedule_appointment` - Change date/time
- `view_payments` - Payment history
- `check_payment_status` - Is it paid?
- `request_refund` - Get money back
- `check_refund_status` - Refund status?

### For Admins
- `view_pending_appointments` - Approvals needed
- `approve_appointment` - Say yes to booking
- `decline_appointment` - Say no to booking
- `complete_appointment` - Mark as done
- `view_system_status` - System health

### For Cashiers
- `view_pending_payments` - Who owes?
- `process_payment` - Mark as paid
- `view_pending_refunds` - Refund requests
- `shift_report` - Daily report

## Testing in Postman

### Test 1: Send Message

```
POST /api/chatbot/send-message
Body: {
  "message": "Show my appointments",
  "conversation_id": "chat_123"
}

Response:
{
  "success": true,
  "ai_response": "You have 3 appointments: ...",
  "meta": {
    "role": "client",
    "intent": "view_appointments",
    "intent_confidence": 0.95
  }
}
```

### Test 2: Get Suggested Questions

```
GET /api/chatbot/suggested-questions

Response: [
  "View my appointments",
  "Check my payment status",
  "Request a refund",
  ...
]
```

### Test 3: Get Conversations

```
GET /api/chatbot/conversations

Response: [
  {
    "conversation_id": "chat_123_...",
    "title": "Show my appointments",
    "message_count": 5,
    "last_message": "Here are your appointments...",
    "created_at": "2024-12-11T10:30:00Z"
  }
]
```

## File Structure

```
app/Services/
├── ChatbotRoleAwarenessService.php    ← Role detection & permissions
├── ChatbotNLUService.php               ← Intent, entity, sentiment
├── ChatbotRealTimeDataService.php      ← Database data fetching
├── ChatbotSmartResponseBuilder.php     ← Response generation
├── ChatbotService.php                  ← (existing, still used)
└── ChatbotAnalyticsService.php         ← Analytics logging

app/Http/Controllers/
└── ChatbotController.php               ← API endpoints (updated)

Models/
├── Appointment.php
├── Payment.php
├── Refund.php
├── Service.php
├── User.php
└── ChatMessage.php
```

## Permission Levels

### Client (Regular User)
- View own appointments
- Check own payment status
- Request refund for own payments
- Book new appointments
- Manage own profile

### Admin (Full Access)
- View ALL appointments
- Manage ALL appointments
- Approve/decline appointments
- View system analytics
- Manage users
- Configure system

### Cashier (Payment Operations)
- View pending payments
- Process payments
- View refund requests
- Approve/process refunds
- Generate shift reports

### Guest (Limited Access)
- View public services
- Check business hours
- View availability
- View system status
- Encouraged to register

## Common Scenarios

### Scenario 1: Client Books Appointment

```
User:     "I want to book an appointment"
System:   Shows available services and times
          Guides through booking process
Result:   Appointment created (pending approval)
```

### Scenario 2: Admin Reviews Pending

```
User:     "Show pending appointments"
System:   Lists all pending with user details
          Provides approve/decline options
Result:   Admin can approve or decline inline
```

### Scenario 3: Cashier Processes Payment

```
User:     "Process payment for #15"
System:   Shows appointment #15 details
          Confirms amount due
Result:   Payment marked as received
```

### Scenario 4: Frustrated Client

```
User:     "WHY IS MY REFUND TAKING SO LONG?!"
System:   Detects high urgency
          Prioritizes response
          Shows refund status and timeline
Result:   Empathetic, helpful response
```

## Troubleshooting

**Q: System says "unauthorized" for action I should be able to do**
A: Check your role. Clients can only see own data. Admins have full access.

**Q: Getting generic response instead of data**
A: Make sure you're logged in and your user has proper permissions.

**Q: Data seems outdated**
A: System caches data for performance. Real-time data updates every 5 minutes.

**Q: Intent not detected**
A: Try being more specific. System will ask clarifying questions if unsure.

## Next Steps

1. **Test the chatbot** - Try different intents
2. **Check analytics** - See what users are asking
3. **Review logs** - See what the system is doing
4. **Customize responses** - Modify SmartResponseBuilder as needed
5. **Add more intents** - Extend NLUService for custom needs

## Support & Documentation

- **Full Guide**: See `CHATBOT_IMPLEMENTATION_GUIDE.md`
- **Code Examples**: Check test files
- **Logs**: `storage/logs/laravel.log`
- **Analytics**: Check chatbot analytics dashboard

---

**Version**: 2.0 (Role-Aware, Real-Time)
**Last Updated**: December 11, 2024
