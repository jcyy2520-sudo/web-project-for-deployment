# Chatbot Advanced Features Implementation Guide

## Overview

This document outlines all the advanced features implemented to resolve the 8 critical issues in the chatbot system:

1. ✅ True Real-Time Communication (WebSocket)
2. ✅ Conversation Memory & Context (Persistent long-term memory)
3. ✅ Action Execution (Multi-step workflows)
4. ✅ Accuracy & Knowledge Base (RAG system)
5. ✅ Role-Based Access Control (Granular permissions)
6. ✅ User Experience (Conversation threading)
7. ✅ Error Handling (Advanced recovery)
8. ✅ Monitoring & Analytics (Quality metrics)

---

## 1. True Real-Time Communication

### WebSocket Implementation

**Service**: `App\Services\WebSocketService`

**Features**:
- Bidirectional real-time communication (replaces SSE polling)
- Connection management and lifecycle handling
- Channel-based subscription system
- Message queuing for reliable delivery
- Circuit breaker for fault tolerance

**API Endpoints**:
```
POST   /api/chatbot/advanced/websocket/init
POST   /api/chatbot/advanced/websocket/subscribe
GET    /api/chatbot/advanced/websocket/messages
GET    /api/chatbot/advanced/websocket/stats
```

**Usage Example**:
```php
// Initialize WebSocket connection
POST /api/chatbot/advanced/websocket/init
{
    "connection_id": "ws_123456"
}

// Subscribe to channels
POST /api/chatbot/advanced/websocket/subscribe
{
    "connection_id": "ws_123456",
    "channels": [
        "user:123",
        "conversation:conv_abc"
    ]
}

// Receive messages periodically
GET /api/chatbot/advanced/websocket/messages?connection_id=ws_123456
```

**Benefits**:
- Instant message delivery (no polling delay)
- Real-time conversation updates
- Presence detection
- Bidirectional data sync

---

## 2. Conversation Memory & Context

### Enhanced Memory Service

**Service**: `App\Services\ChatbotMemoryService` (enhanced)

**Features**:
- Extended context window (50 messages vs. 10)
- Cross-session memory persistence
- Conversation summarization
- Topic continuity tracking
- User preference learning

**Data Models**:
- `UserLongTermMemory` - Persistent user memory across sessions
- `ConversationSummary` - AI-generated summaries of conversations
- `ConversationThread` - Parallel conversations with threading

**Key Improvements**:
```php
// Get comprehensive conversation context
$context = $memoryService->getConversationContext($userId, $conversationId);

// Returns:
{
    "recent_messages": [...],           // Last 50 messages
    "conversation_summary": "...",       // AI summary
    "user_preferences": {...},          // Learned preferences
    "topics_discussed": [...],          // Detected topics
    "related_context": {...},           // Related conversations
    "sentiment_trend": "positive"       // Sentiment analysis
}
```

**Database Storage**:
- Messages cached for 50-message context window
- Summaries generated after 30+ messages
- User preferences stored with relevance scores
- Automatic cleanup of expired memories

---

## 3. Multi-Step Action Execution & Workflows

### Workflow Orchestration Service

**Service**: `App\Services\WorkflowService`

**Available Workflows**:
1. `approve_and_notify` - Approve appointment + send notification
2. `complete_payment_workflow` - Validate → Process → Receipt → Update
3. `refund_workflow` - Validate → Process → Cancel → Confirm
4. `appointment_cancellation` - Check deadline → Cancel → Refund → Notify

**Features**:
- Multi-step action chaining
- Conditional execution based on results
- Automatic rollback on failure
- Auto-confirmation for safe actions
- Workflow history and logging

**API Endpoint**:
```
POST /api/chatbot/advanced/workflow/execute
```

**Usage Example**:
```php
// Execute a workflow
POST /api/chatbot/advanced/workflow/execute
{
    "workflow_name": "complete_payment_workflow",
    "context": {
        "appointment_id": 123,
        "amount": 500
    }
}

// Response
{
    "success": true,
    "workflow_id": "uuid",
    "steps_executed": [
        "validate_payment",
        "process_payment",
        "send_receipt",
        "update_appointment_status"
    ],
    "results": {...}
}
```

**Auto-Confirmable Actions**:
Actions that execute immediately without user confirmation:
- send_notification
- send_email
- send_confirmation
- log_action
- update_status

**Fallible Actions** (require confirmation):
- approve_appointment
- cancel_appointment
- process_payment
- process_refund

---

## 4. Accuracy & Knowledge Base (RAG)

### Retrieval-Augmented Generation

**Service**: `App\Services\SemanticEmbeddingsService` (enhanced)

**Features**:
- Document embedding and indexing
- Semantic similarity search
- Fact verification against knowledge base
- Hallucination prevention
- Vector search with top-K retrieval

**Database**:
- `KnowledgeBase` - Indexed document chunks with embeddings

**Usage**:
```php
// Index a document
$embeddingService->indexDocument(
    title: "Refund Policy",
    content: "Full refund policy details...",
    category: "policies",
    documentType: "policy"
);

// Search knowledge base
$results = $embeddingService->semanticSearch(
    query: "How long for refund?",
    topK: 5
);

// Verify fact against knowledge base
$isValid = $embeddingService->verifyFact(
    claim: "Refunds take 5-7 days",
    threshold: 0.85
);
```

**Integration with LLM**:
```php
// RAG-enhanced prompt
$context = $embeddingService->retrieveContext($userQuery);
$llmResponse = $llm->generate(
    systemPrompt: "You are a helpful assistant. Use only the provided context.",
    context: $context,
    userQuery: $userQuery
);
```

---

## 5. Granular Role-Based Access Control

### Action Permission Service

**Service**: `App\Services\ActionPermissionService`

**Features**:
- Per-action permission checking (not just role-level)
- Dynamic capability checking
- Daily action limits
- Constraint validation
- Action metadata and descriptions

**Permission Structure**:
```php
'admin' => [
    'view_all_appointments' => true,
    'approve_appointment' => true,
    'process_refund' => true,
    'manage_settings' => true,
    // ... 25+ actions
],
'cashier' => [
    'process_payment' => true,
    'approve_refund' => false,  // Cannot approve, only process
    'view_shift_report' => true,
    // ... 8 actions
],
'client' => [
    'view_own_appointments' => true,
    'request_refund' => true,
    'cancel_own_appointment' => true,
    // ... limited actions
]
```

**API Endpoint**:
```
POST /api/chatbot/advanced/permission/check
GET  /api/chatbot/advanced/permission/actions
```

**Usage Example**:
```php
// Check permission
POST /api/chatbot/advanced/permission/check
{
    "action": "approve_refund",
    "context": {
        "refund_id": 123,
        "amount": 1000
    }
}

// Response
{
    "success": true,
    "permission": {
        "allowed": true,
        "role": "admin",
        "action": "approve_refund"
    }
}

// Get permitted actions
GET /api/chatbot/advanced/permission/actions

// Returns
{
    "success": true,
    "actions": [
        "view_all_appointments",
        "approve_appointment",
        "process_payment",
        // ... all permitted actions
    ]
}
```

**Action Constraints**:
```php
'approve_refund' => [
    'requires_status' => ['pending'],      // Must be pending
    'requires_data' => ['refund_id'],      // Must have refund_id
    'max_daily_limit' => 100,              // Max 100 per day
]
```

---

## 6. Conversation Threading & UX

### Conversation Thread Service

**Service**: `App\Services\ConversationThreadService`

**Features**:
- Multiple parallel conversations per user
- Smart context switching
- Thread organization and tagging
- Smart suggestions based on history
- Proactive notifications

**API Endpoints**:
```
POST   /api/chatbot/advanced/thread/create
GET    /api/chatbot/advanced/thread/list
POST   /api/chatbot/advanced/thread/switch
GET    /api/chatbot/advanced/thread/suggestions
```

**Usage Example**:
```php
// Create new conversation thread
POST /api/chatbot/advanced/thread/create
{
    "topic": "Appointment Questions",
    "metadata": {"service_id": 5}
}

// Response
{
    "success": true,
    "thread": {
        "conversation_id": "uuid",
        "session_id": "uuid",
        "topic": "Appointment Questions",
        "title": "Appointment Questions - Dec 13, 14:30",
        "created_at": "2025-12-13T14:30:00Z"
    }
}

// List user's threads
GET /api/chatbot/advanced/thread/list?limit=20

// Switch to different conversation
POST /api/chatbot/advanced/thread/switch
{
    "conversation_id": "uuid"
}

// Get smart suggestions for current conversation
GET /api/chatbot/advanced/thread/suggestions?conversation_id=uuid

// Response
{
    "success": true,
    "suggestions": [
        "View my upcoming appointments",
        "Check payment status",
        "Request a refund"
    ],
    "topics_detected": ["appointments", "payments"]
}
```

**Thread Status**:
- `active` - Currently active conversation
- `paused` - User has switched to another
- `archived` - Conversation completed
- `completed` - Conversation finished

---

## 7. Advanced Error Handling & Recovery

### Error Handling Service

**Service**: `App\Services\ErrorHandlingService`

**Features**:
- Retry logic with exponential backoff
- Circuit breaker pattern
- Graceful degradation
- Intelligent error messages
- Error context logging
- Recovery tracking

**API Endpoint**:
```
GET /api/chatbot/advanced/errors/summary
```

**Usage Example**:
```php
// Execute with automatic retry
$result = $errorService->executeWithRetry(
    callback: function() {
        return $this->externalAPI->fetchData();
    },
    maxRetries: 3,
    retryableExceptions: ['TimeoutException', 'ConnectionException']
);

// Response
{
    "success": true,
    "result": {...},
    "attempts": 2  // Failed once, succeeded on retry
}

// Check circuit breaker status
$isOpen = $errorService->isCircuitBreakerOpen('external_api_service');

// Record error with context
$errorId = $errorService->logErrorWithContext(
    exception: $e,
    context: ['user_id' => 123, 'action' => 'process_payment']
);

// Get error summary
GET /api/chatbot/advanced/errors/summary?hours=24

// Response
{
    "success": true,
    "summary": {
        "total_errors": 15,
        "errors_by_type": {
            "QueryException": 8,
            "ValidationException": 5,
            "TimeoutException": 2
        },
        "recent_errors": [...]
    }
}
```

**Fallback Strategies**:
- Return cached data when service unavailable
- Provide degraded but functional UI
- Queue requests for later processing
- Transparent error messages to users

---

## 8. Monitoring & Analytics

### Chatbot Metrics Service

**Service**: `App\Services\ChatbotMetricsService`

**Metrics Tracked**:
1. **Message Metrics**: Length, response time, sentiment
2. **Action Metrics**: Success rate, execution time
3. **Error Metrics**: Error type, frequency
4. **Quality Metrics**: Conversation quality score
5. **Satisfaction Metrics**: User ratings (1-5)
6. **Performance Metrics**: Response times, error rates

**Quality Score Calculation**:
- Message engagement (20%)
- Sentiment analysis (30%)
- Response time performance (20%)
- Error rate (15%)
- User satisfaction (15%)
- Grade: A (90+), B (80+), C (70+), D (60+), F (<60)

**API Endpoints**:
```
POST   /api/chatbot/advanced/metrics/satisfaction
GET    /api/chatbot/advanced/metrics/quality
GET    /api/chatbot/advanced/metrics/performance
GET    /api/chatbot/advanced/metrics/bottlenecks
```

**Usage Example**:
```php
// Record user satisfaction
POST /api/chatbot/advanced/metrics/satisfaction
{
    "conversation_id": "uuid",
    "rating": 4,
    "feedback": "Very helpful assistant!",
    "would_recommend": true
}

// Get conversation quality score
GET /api/chatbot/advanced/metrics/quality?conversation_id=uuid

// Response
{
    "success": true,
    "quality": {
        "score": 87.5,
        "grade": "B",
        "factors": {
            "message_engagement": 75,
            "sentiment": 85,
            "response_time": 92,
            "error_rate": 80,
            "satisfaction": 88
        },
        "message_count": 15,
        "duration_seconds": 420
    }
}

// Get performance metrics for period
GET /api/chatbot/advanced/metrics/performance?period=day

// Response
{
    "success": true,
    "metrics": {
        "period": "day",
        "total_conversations": 156,
        "total_messages": 2340,
        "average_quality_score": 82.3,
        "average_response_time_ms": 1250,
        "error_rate": 0.02,
        "average_satisfaction": 4.1
    }
}

// Identify bottlenecks
GET /api/chatbot/advanced/metrics/bottlenecks?period=day

// Response
{
    "success": true,
    "bottlenecks": [
        {
            "type": "slow_response",
            "severity": "high",
            "avg_response_ms": 3500,
            "message": "Response times are consistently slow",
            "recommendation": "Check database queries and LLM API performance"
        },
        {
            "type": "low_satisfaction",
            "severity": "medium",
            "avg_rating": 2.8,
            "message": "User satisfaction scores are low",
            "recommendation": "Review conversation quality and user feedback"
        }
    ]
}
```

**Database Storage**:
- `ChatbotAnalytics` - Individual metrics
- `PerformanceMetricsSnapshots` - Aggregated metrics
- `UserSatisfactionRatings` - User feedback

---

## Database Schema

### New Tables Created

1. **conversation_threads** - Multi-threaded conversations
2. **workflow_executions** - Workflow execution history
3. **user_long_term_memory** - Persistent user memory
4. **conversation_summaries** - AI-generated summaries
5. **action_audit_trail** - Permission audit log
6. **chatbot_errors** - Error tracking
7. **user_satisfaction_ratings** - Satisfaction tracking
8. **performance_metrics_snapshots** - Metrics aggregation
9. **websocket_connections** - WebSocket session tracking

### Migration Command
```bash
php artisan migrate
```

---

## Configuration

### Environment Variables

Add to `.env`:
```env
# WebSocket Configuration
WEBSOCKET_ENABLED=true
WEBSOCKET_TIMEOUT=3600

# RAG/Knowledge Base
RAG_ENABLED=true
EMBEDDING_MODEL=nomic-embed-text
OLLAMA_EMBEDDINGS_URL=http://localhost:11434/api/embeddings

# Error Handling
CIRCUIT_BREAKER_ENABLED=true
CIRCUIT_BREAKER_THRESHOLD=5

# Metrics
METRICS_ENABLED=true
METRICS_CACHE_TTL=3600
```

---

## Testing

### Unit Tests

```bash
# Test WebSocket service
php artisan test --filter=WebSocketServiceTest

# Test Workflow service
php artisan test --filter=WorkflowServiceTest

# Test Permission service
php artisan test --filter=ActionPermissionServiceTest

# Test Metrics service
php artisan test --filter=ChatbotMetricsServiceTest
```

### Manual Testing

```bash
# Test WebSocket
curl -X POST http://localhost:8000/api/chatbot/advanced/websocket/init \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test Workflow
curl -X POST http://localhost:8000/api/chatbot/advanced/workflow/execute \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"workflow_name": "approve_and_notify", "context": {...}}'

# Test Permissions
curl -X POST http://localhost:8000/api/chatbot/advanced/permission/check \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test Metrics
curl -X GET http://localhost:8000/api/chatbot/advanced/metrics/performance \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Performance Optimization

### Caching Strategy
- User preferences cached for 1 hour
- Conversation context cached for real-time access
- Metrics snapshots cached for 1 hour
- WebSocket messages queued efficiently

### Database Indexes
- User ID + timestamp on all relevant tables
- Conversation ID indexes for faster lookups
- Status indexes for filtering
- Created_at indexes for time-based queries

### Query Optimization
- Eager loading of relationships
- Pagination on list endpoints (limit 20 default)
- Select specific columns when not needed full model
- Use raw queries for complex aggregations

---

## Deployment Checklist

- [ ] Run database migrations: `php artisan migrate`
- [ ] Register service providers in `AppServiceProvider`
- [ ] Update routes in `routes/api.php`
- [ ] Configure `.env` variables
- [ ] Set up cache backend (Redis recommended)
- [ ] Configure WebSocket service (optional but recommended)
- [ ] Set up knowledge base documents
- [ ] Test all endpoints thoroughly
- [ ] Monitor metrics dashboard
- [ ] Set up error alerting

---

## Support & Troubleshooting

### Common Issues

**WebSocket not connecting?**
- Check connection_id is unique
- Verify user is authenticated (auth:sanctum middleware)
- Check cache driver is configured

**Workflows not executing?**
- Verify user has permission for all workflow actions
- Check context includes all required data
- Review workflow definition constraints

**Metrics not recording?**
- Ensure `recordMessageMetrics()` is called after each message
- Check database tables exist (run migrations)
- Verify cache is working

**Performance slow?**
- Check database indexes are created
- Review query performance with `php artisan queries:monitoring`
- Enable query caching
- Check WebSocket message queue size

---

## Future Enhancements

1. **Real-time WebSocket** with Pusher/Soketi
2. **Advanced RAG** with hybrid search (semantic + keyword)
3. **Conversation branching** with alternative paths
4. **ML-based suggestions** with reinforcement learning
5. **Multi-language** RAG with language-specific embeddings
6. **Advanced RBAC** with attribute-based access control
7. **Real-time collaboration** on conversations
8. **Analytics dashboard** with visualization

---

## Version History

- **v1.0** (Dec 13, 2025) - Initial implementation
  - WebSocket real-time communication
  - Multi-step workflow orchestration
  - Conversation threading
  - Advanced metrics and monitoring
  - Granular permission checking
  - Enhanced error handling
  - Long-term memory persistence
  - RAG knowledge base integration
