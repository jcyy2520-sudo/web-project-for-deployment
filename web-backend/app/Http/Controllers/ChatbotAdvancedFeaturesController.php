<?php

namespace App\Http\Controllers;

use App\Services\WebSocketService;
use App\Services\WorkflowService;
use App\Services\ActionPermissionService;
use App\Services\ConversationThreadService;
use App\Services\ErrorHandlingService;
use App\Services\ChatbotMetricsService;
use App\Models\ConversationThread;
use App\Models\WorkflowExecution;
use App\Models\UserLongTermMemory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotAdvancedFeaturesController
 * 
 * Handles all advanced chatbot features:
 * - WebSocket real-time communication
 * - Workflow orchestration
 * - Conversation threading
 * - Metrics and analytics
 * - Error handling and recovery
 */
class ChatbotAdvancedFeaturesController extends Controller
{
    private WebSocketService $websocketService;
    private WorkflowService $workflowService;
    private ActionPermissionService $permissionService;
    private ConversationThreadService $threadService;
    private ErrorHandlingService $errorService;
    private ChatbotMetricsService $metricsService;

    public function __construct(
        WebSocketService $websocketService,
        WorkflowService $workflowService,
        ActionPermissionService $permissionService,
        ConversationThreadService $threadService,
        ErrorHandlingService $errorService,
        ChatbotMetricsService $metricsService
    ) {
        $this->websocketService = $websocketService;
        $this->workflowService = $workflowService;
        $this->permissionService = $permissionService;
        $this->threadService = $threadService;
        $this->errorService = $errorService;
        $this->metricsService = $metricsService;
    }

    // ==================== WebSocket Endpoints ====================

    /**
     * Initialize WebSocket connection
     */
    public function initializeWebSocket(Request $request)
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $connectionId = $request->input('connection_id') ?? uniqid('ws_');
            $sessionId = $request->header('X-Session-ID') ?? $request->session()->getId();

            $this->websocketService->registerConnection($connectionId, $userId, $sessionId);

            return response()->json([
                'success' => true,
                'connection_id' => $connectionId,
                'message' => 'WebSocket connection established',
            ]);
        } catch (\Exception $e) {
            Log::error('WebSocket initialization error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize WebSocket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subscribe to real-time updates
     */
    public function subscribeToUpdates(Request $request)
    {
        try {
            $request->validate([
                'connection_id' => 'required|string',
                'channels' => 'required|array',
                'channels.*' => 'string',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $connectionId = $request->input('connection_id');
            $channels = $request->input('channels');

            foreach ($channels as $channel) {
                if (str_starts_with($channel, 'conversation:')) {
                    $conversationId = str_replace('conversation:', '', $channel);
                    $this->websocketService->subscribeToConversation($conversationId, $userId, $connectionId);
                } else {
                    $this->websocketService->subscribeToChannel($userId, $channel, $connectionId);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscribed to channels',
                'channels' => $channels,
            ]);
        } catch (\Exception $e) {
            Log::error('Subscribe error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Subscription failed',
            ], 500);
        }
    }

    /**
     * Get pending WebSocket messages
     */
    public function getPendingMessages(Request $request)
    {
        try {
            $request->validate([
                'connection_id' => 'required|string',
            ]);

            $connectionId = $request->input('connection_id');
            $messages = $this->websocketService->getPendingMessages($connectionId);

            return response()->json([
                'success' => true,
                'messages' => $messages,
                'count' => count($messages),
            ]);
        } catch (\Exception $e) {
            Log::error('Get messages error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get messages',
            ], 500);
        }
    }

    // ==================== Workflow Endpoints ====================

    /**
     * Execute a workflow
     */
    public function executeWorkflow(Request $request)
    {
        try {
            $request->validate([
                'workflow_name' => 'required|string',
                'context' => 'required|array',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $workflowName = $request->input('workflow_name');
            $context = $request->input('context');

            // Validate workflow is allowed
            $validation = $this->workflowService->validateWorkflow($workflowName, $context, $userId);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['reason'] ?? 'Workflow validation failed',
                ], 400);
            }

            // Execute workflow
            $result = $this->workflowService->executeWorkflow($workflowName, $context, $userId);

            // Store execution record
            if ($result['success']) {
                WorkflowExecution::create([
                    'workflow_id' => $result['workflow_id'],
                    'user_id' => $userId,
                    'workflow_name' => $workflowName,
                    'steps' => $result['steps_executed'],
                    'context' => $context,
                    'results' => $result['results'],
                    'status' => 'completed',
                    'total_steps' => count($result['steps_executed']),
                    'completed_steps' => count($result['steps_executed']),
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Workflow execution error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Workflow execution failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available workflows
     */
    public function getAvailableWorkflows(Request $request)
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $workflows = $this->workflowService->getAvailableWorkflows($userId);

            return response()->json([
                'success' => true,
                'workflows' => $workflows,
                'count' => count($workflows),
            ]);
        } catch (\Exception $e) {
            Log::error('Get workflows error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflows',
            ], 500);
        }
    }

    // ==================== Permission Endpoints ====================

    /**
     * Check if user can perform an action
     */
    public function checkActionPermission(Request $request)
    {
        try {
            $request->validate([
                'action' => 'required|string',
                'context' => 'nullable|array',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $action = $request->input('action');
            $context = $request->input('context', []);

            $permission = $this->permissionService->canPerformAction($userId, $action, $context);

            return response()->json([
                'success' => true,
                'permission' => $permission,
            ]);
        } catch (\Exception $e) {
            Log::error('Permission check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Permission check failed',
            ], 500);
        }
    }

    /**
     * Get user's permitted actions
     */
    public function getPermittedActions(Request $request)
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $actions = $this->permissionService->getPermittedActions($userId);

            return response()->json([
                'success' => true,
                'actions' => $actions,
                'count' => count($actions),
            ]);
        } catch (\Exception $e) {
            Log::error('Get actions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get actions',
            ], 500);
        }
    }

    // ==================== Conversation Threading Endpoints ====================

    /**
     * Create a new conversation thread
     */
    public function createThread(Request $request)
    {
        try {
            $request->validate([
                'topic' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $topic = $request->input('topic');
            $metadata = $request->input('metadata', []);

            $result = $this->threadService->createThread($userId, $topic, $metadata);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Create thread error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create thread',
            ], 500);
        }
    }

    /**
     * Get all conversation threads for user
     */
    public function getUserThreads(Request $request)
    {
        try {
            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $limit = $request->query('limit', 20);
            $result = $this->threadService->getUserThreads($userId, $limit);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Get threads error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get threads',
            ], 500);
        }
    }

    /**
     * Switch active conversation
     */
    public function switchThread(Request $request)
    {
        try {
            $request->validate([
                'conversation_id' => 'required|string',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $conversationId = $request->input('conversation_id');
            $result = $this->threadService->switchThread($userId, $conversationId);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Switch thread error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch thread',
            ], 500);
        }
    }

    /**
     * Get suggestions for current conversation
     */
    public function getConversationSuggestions(Request $request)
    {
        try {
            $request->validate([
                'conversation_id' => 'required|string',
            ]);

            $userId = auth('sanctum')->id() ?? auth()->id();
            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $conversationId = $request->input('conversation_id');
            $result = $this->threadService->getSuggestions($userId, $conversationId);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Get suggestions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions',
            ], 500);
        }
    }

    // ==================== Metrics Endpoints ====================

    /**
     * Record user satisfaction
     */
    public function recordSatisfaction(Request $request)
    {
        try {
            $request->validate([
                'conversation_id' => 'required|string',
                'rating' => 'required|integer|between:1,5',
                'feedback' => 'nullable|string',
            ]);

            $conversationId = $request->input('conversation_id');
            $rating = $request->input('rating');
            $feedback = $request->input('feedback');

            $result = $this->metricsService->recordUserSatisfaction($conversationId, $rating, $feedback);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Record satisfaction error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to record satisfaction',
            ], 500);
        }
    }

    /**
     * Get conversation quality score
     */
    public function getConversationQuality(Request $request)
    {
        try {
            $request->validate([
                'conversation_id' => 'required|string',
            ]);

            $conversationId = $request->input('conversation_id');
            $quality = $this->metricsService->getConversationQualityScore($conversationId);

            return response()->json([
                'success' => true,
                'quality' => $quality,
            ]);
        } catch (\Exception $e) {
            Log::error('Get quality error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get quality score',
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(Request $request)
    {
        try {
            $period = $request->query('period', 'day');
            $metrics = $this->metricsService->getPerformanceMetrics($period);

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Get metrics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get metrics',
            ], 500);
        }
    }

    /**
     * Identify performance bottlenecks
     */
    public function identifyBottlenecks(Request $request)
    {
        try {
            $period = $request->query('period', 'day');
            $bottlenecks = $this->metricsService->identifyBottlenecks($period);

            return response()->json([
                'success' => true,
                'bottlenecks' => $bottlenecks,
                'count' => count($bottlenecks),
            ]);
        } catch (\Exception $e) {
            Log::error('Identify bottlenecks error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to identify bottlenecks',
            ], 500);
        }
    }

    // ==================== Error Handling Endpoints ====================

    /**
     * Get error summary
     */
    public function getErrorSummary(Request $request)
    {
        try {
            $hours = $request->query('hours', 24);
            $summary = $this->errorService->getErrorSummary($hours);

            return response()->json([
                'success' => true,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Get error summary error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get error summary',
            ], 500);
        }
    }

    /**
     * Get WebSocket statistics
     */
    public function getWebSocketStats(Request $request)
    {
        try {
            $stats = $this->websocketService->getStatistics();

            return response()->json([
                'success' => true,
                'statistics' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Get WebSocket stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
            ], 500);
        }
    }
}
