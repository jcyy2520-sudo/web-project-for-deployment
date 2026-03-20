<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\BlackoutDate;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\TimeSlotCapacity;
use App\Models\UnavailableDate;
use App\Models\User;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentStatusMail;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * AgentToolRegistry — Function-Calling Tool Definitions for the AI Agent
 *
 * Implements a tool-use / function-calling architecture. The chatbot executes
 * tools directly on behalf of users rather than merely describing how to use the UI.
 *
 * Each tool has:
 *   - name: unique identifier
 *   - description: what the tool does (sent to LLM for reasoning)
 *   - parameters: JSON-schema-like parameter definitions
 *   - required_role: minimum role to execute
 *   - is_destructive: whether it modifies data (requires confirmation)
 *   - handler: callable that executes the tool
 *
 * Security:
 *   - Every tool checks role permissions before execution via ActionPermissionService
 *   - Destructive tools require explicit user confirmation
 *   - All executions are audit-logged
 *   - Users can only access their own data (enforced at query level)
 *   - Input validation and injection prevention on all arguments
 *
 * v2 Improvements:
 *   - get_available_slots now checks weekends, blackout dates, lunch breaks, and capacity rules
 *   - book_appointment now validates weekends, blackouts, lunch, daily limits, and uses pessimistic locking
 *   - Added admin analytics tools: demand forecast, no-show patterns, auto-alerts, appointment stats
 *   - Added decision support tools: workload optimization, customer insights, engagement scores
 *   - Added admin bulk cancel tool for mass operations
 *   - Added get_unavailable_dates and get_alternative_slots for scheduling intelligence
 *   - Added get_notifications for user notification access
 */
class AgentToolRegistry
{
    private ChatbotRealTimeDataService $dataService;
    private ActionPermissionService $permissionService;

    /** @var array<string, array> Registered tools */
    private array $tools = [];

    public function __construct(
        ChatbotRealTimeDataService $dataService,
        ActionPermissionService $permissionService
    ) {
        $this->dataService = $dataService;
        $this->permissionService = $permissionService;
        $this->registerCoreTools();
        $this->registerAnalyticsTools();
        $this->registerDecisionSupportTools();
    }

    /**
     * Get tool definitions formatted for LLM function-calling prompts.
     * Filtered by the user's role — only shows tools the user can invoke.
     */
    public function getToolDefinitionsForRole(string $role): array
    {
        $definitions = [];
        foreach ($this->tools as $name => $tool) {
            if ($this->permissionService->canUseAgentTool($role, $name)) {
                $definitions[] = [
                    'name' => $name,
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                    'is_destructive' => $tool['is_destructive'],
                ];
            }
        }
        return $definitions;
    }

    /**
     * Check if a tool name exists in the registry.
     */
    public function toolExists(string $toolName): bool
    {
        return isset($this->tools[$toolName]);
    }

    /**
     * Get compact tool descriptions for the system prompt.
     */
    public function getToolPromptSection(string $role): string
    {
        return Cache::remember("agent_tools_prompt_v4_{$role}", 300, function () use ($role) {
            $tools = $this->getToolDefinitionsForRole($role);
            if (empty($tools)) {
                return '';
            }

            $section = "## AVAILABLE TOOLS\n";
            $section .= "You have access to real tools that perform REAL actions in the system. When you call a tool, it actually executes — appointments are really booked, really cancelled, payments really processed.\n\n";
            $section .= "### HOW TO CALL A TOOL\n";
            $section .= "Output a JSON block in this exact format:\n";
            $section .= "```tool_call\n{\"tool\": \"tool_name\", \"arguments\": {\"param\": \"value\"}}\n```\n\n";
            $section .= "### CRITICAL RULES FOR TOOL USAGE\n";
            $section .= "1. **You MUST use tools for ANY user request that involves system data or actions.** Do NOT describe what would happen — actually DO it.\n";
            $section .= "2. **For destructive tools (marked ⚠️):** Gather ALL required parameters through conversation, then output the ```tool_call``` block IMMEDIATELY. Do NOT ask 'shall I proceed?' or 'would you like me to do this?' — the system will AUTOMATICALLY show the user a Confirm/Cancel button. Your ONLY job is to collect the data and output the tool_call block. The system handles user confirmation.\n";
            $section .= "3. **For read-only tools:** Call them IMMEDIATELY without asking permission. The user asked for data — give them data.\n";
            $section .= "4. **NEVER say 'I have completed the action' or 'Done!' without actually calling the tool.** If you didn't output a ```tool_call``` block, the action was NOT performed. This is the #1 most critical rule.\n";
            $section .= "5. **NEVER give manual instructions** like 'go to dashboard and click...' when a tool can do it. USE THE TOOL.\n";
            $section .= "6. **After a tool executes:** Report the specific result (appointment ID, date, status, amount) — not vague confirmations.\n";
            $section .= "7. **ANTI-HALLUCINATION (ABSOLUTE):** The ONLY way to book an appointment is by outputting a ```tool_call``` block for `book_appointment`. The ONLY way to cancel is by outputting a ```tool_call``` block for `cancel_appointment`. If you say 'Your appointment has been booked' or 'Your appointment has been cancelled' WITHOUT a tool_call block in your response, NOTHING happened — the user is misled and the system state is unchanged. NEVER claim an action was performed unless you output the tool_call block.\n";
            $section .= "8. **IMMEDIATE EXECUTION:** Once you have all required parameters (e.g., service_id, date, time for booking), output the tool_call block in the SAME response. Do NOT send a separate message asking for confirmation — output the tool_call block NOW and the system handles the rest.\n\n";

            // Group tools by category for better LLM comprehension
            $categories = [
                'Booking' => ['get_my_appointments', 'get_appointment_details', 'get_available_slots', 'get_available_services', 'get_unavailable_dates', 'get_alternative_slots', 'book_appointment', 'cancel_appointment', 'reschedule_appointment'],
                'Payments' => ['get_my_payments', 'check_payment_status', 'request_refund'],
                'Admin' => ['admin_get_pending_appointments', 'admin_approve_appointment', 'admin_decline_appointment', 'admin_get_system_stats', 'admin_bulk_cancel_appointments'],
                'Analysis' => ['get_demand_forecast', 'get_risk_assessment', 'predict_busy_days', 'predict_no_show', 'get_scheduling_recommendation'],
            ];

            foreach ($categories as $categoryName => $toolNames) {
                $categoryTools = array_filter($tools, fn($t) => in_array($t['name'], $toolNames));
                if (empty($categoryTools)) continue;

                $section .= "### {$categoryName}\n";
                foreach ($categoryTools as $tool) {
                    $destructiveFlag = $tool['is_destructive'] ? ' ⚠️' : '';
                    $section .= "- **{$tool['name']}**{$destructiveFlag}: {$tool['description']}\n";
                    if (!empty($tool['parameters'])) {
                        $params = array_map(fn($p) => "`{$p['name']}` ({$p['type']}" . ($p['required'] ? ', required' : '') . ")", $tool['parameters']);
                        $section .= "  Parameters: " . implode(', ', $params) . "\n";
                    }
                }
                $section .= "\n";
            }

            $section .= "### TOOL USAGE GUIDELINES\n";
            $section .= "- After a tool returns results, report the result directly and concisely in 1 sentence. DO NOT incorporate it naturally into a conversational paragraph. Avoid all conversational filler like 'Great!', 'I understand', 'Let me know', 'Would you like...'.\n";
            $section .= "- If a tool fails, output 1 sentence stating the exact error, then immediately present alternatives. DO NOT ask conversational questions like 'Would you like to try another date?'.\n";
            $section .= "- NEVER fabricate tool results. Only report what the tool actually returns.\n";
            $section .= "- When a user asks about their appointments without a specific date, query relevant time ranges.\n";
            $section .= "- When recommending slots, prefer get_scheduling_recommendation for AI-optimized suggestions.\n";
            $section .= "### APPOINTMENT BOOKING WORKFLOW (TASK-EXECUTOR MODE)\n";
            $section .= "Your goal: complete the booking AS FAST AS POSSIBLE without unnecessary conversation.\n\n";
            $section .= "NEW FEATURE: Users can now book MULTIPLE services in a single appointment. If a user mentions multiple services (e.g., 'I want a consultation and a contract review'), collect all service IDs/names and book them together.\n\n";
            $section .= "**FAST PATH**: If the user provides service(s) + date + time in one message (or context), skip data collection entirely. Check availability silently and output the `book_appointment` tool_call block IMMEDIATELY.\n\n";
            $section .= "**Steps:**\n";
            $section .= "1. If the user hasn't specified any service → call `get_available_services` to show options and ask which service(s) they want. Also ask for date + time in the SAME message. Include available hours: 8:00 AM–11:00 AM, 1:00 PM–5:00 PM.\n";
            $section .= "2. After the user selects one service, briefly ask if they want to add another service (e.g., 'Added. Would you like to add another service, or shall we proceed with date and time?')\n";
            $section .= "3. If the user hasn't specified a date/time → ask for date AND time together in ONE message (include available time ranges).\n";
            $section .= "4. Once you have a date → call `get_available_slots` for that date. Do NOT narrate this — just do it and respond with the result.\n";
            $section .= "5. If the slot is unavailable → in the SAME response, state why AND suggest 2-3 available alternatives from the slot data. NEVER just say 'not available' without alternatives.\n";
            $section .= "6. If the slot is available → check the booking limit from LIVE DATA section (or call `check_booking_limit` if not available).\n";
            $section .= "6. If the user has reached their limit → tell them when they can book again. Do NOT proceed.\n";
            $section .= "7. If all checks pass → OUTPUT THE `book_appointment` tool_call block IMMEDIATELY. Do NOT generate a text summary first and do NOT ask 'shall I proceed?'. The UI handles confirmation.\n";
            $section .= "8. NEVER say 'Your appointment has been booked' without outputting a tool_call block. That is a hallucination.\n\n";

            $section .= "### CONVERSATION MEMORY (CRITICAL)\n";
            $section .= "- ALWAYS remember info the user already provided in the conversation. Do NOT ask for it again.\n";
            $section .= "- NEVER restart the booking process from scratch.\n";
            $section .= "- Example: User said 'Book affidavit tomorrow' → You only need to ask for TIME.\n\n";

            $section .= "### CONFIRMATION SUMMARY HANDLING\n";
            $section .= "Do NOT generate your own text-based confirmation summary. Output the `book_appointment` tool_call IMMEDIATELY. The system automatically handles showing the confirmation UI to the user.\n\n";

            $section .= "### SMART SUGGESTIONS WHEN SLOT IS UNAVAILABLE\n";
            $section .= "If a requested slot is unavailable, AUTOMATICALLY suggest alternatives in the same response.\n";
            $section .= "NEVER just say 'not available' without providing alternatives.\n\n";

            $section .= "### AUTOMATIC SLOT CHECKING\n";
            $section .= "- ALWAYS check availability silently. NEVER ask 'Would you like me to check?'\n";
            $section .= "- NEVER narrate: 'Let me check...' or 'I will verify...'. Just do it and report the result.\n\n";

            $section .= "### BOOKING CONFIRMATION (AFTER SUCCESS)\n";
            $section .= "After the booking tool succeeds, confirm with specific details:\n";
            $section .= "'Appointment booked successfully.\nDate: [Date]\nTime: [Time]\nService: [Service]\nA confirmation has been sent to your email.'\n\n";

            $section .= "\n### APPOINTMENT CANCELLATION WORKFLOW (FOLLOW THIS EXACTLY)\n";
            $section .= "When a user wants to cancel an appointment:\n";
            $section .= "1. If the user didn't specify which appointment → call `cancel_appointment` with NO params to list their cancellable (pending) appointments.\n";
            $section .= "2. Show the list and ask the user which appointment to cancel (by ID, date, or time).\n";
            $section .= "3. When the user specifies → output the `cancel_appointment` tool_call block with the `appointment_id` IMMEDIATELY.\n";
            $section .= "4. Only **pending** appointments can be cancelled. Approved, declined, or completed appointments cannot be cancelled.\n";
            $section .= "5. The system will show the user a Confirm/Cancel button. Do NOT ask for verbal confirmation yourself.\n";
            $section .= "6. NEVER say 'Your appointment has been cancelled' without outputting a tool_call block.\n";
            $section .= "\n### WHEN TO USE TOOLS (MANDATORY)\n";
            $section .= "Use a tool IMMEDIATELY when the user:\n";
            $section .= "- Asks to **see/view/check/show** anything (appointments, payments, services, slots) → call the appropriate get_ tool\n";
            $section .= "- Asks to **book/schedule/reserve** → follow the APPOINTMENT BOOKING WORKFLOW above\n";
            $section .= "- Asks to **cancel** → follow the APPOINTMENT CANCELLATION WORKFLOW above\n";
            $section .= "- Asks to **reschedule/change** → call reschedule_appointment\n";
            $section .= "- Asks **'when can I come?'** or about availability → call get_available_slots or get_scheduling_recommendation\n";
            $section .= "- Asks about **payments/billing** → call get_my_payments or check_payment_status\n";
            $section .= "\n### WHEN NOT TO USE TOOLS\n";
            $section .= "Do NOT use tools when the user:\n";
            $section .= "- Is making casual conversation or asking general questions\n";
            $section .= "- Asks about services, policies, office info, or how things work (answer from context — this is your INFORMATIONAL CHATBOT role)\n";
            $section .= "- Needs clarification before you can determine which tool to call\n";
            $section .= "\n### ANALYTICS & DECISION SUPPORT GUIDELINES\n";
            $section .= "- The LIVE SYSTEM DATA section already contains a demand forecast summary, slot utilization, and no-show patterns. Use that data FIRST for quick answers.\n";
            $section .= "- For deeper or custom analysis (e.g., longer date ranges, quality reports, client engagement), use the appropriate tool.\n";
            $section .= "- When asked 'which day will be busy?' or 'when is peak time?', answer from the demand forecast data in LIVE SYSTEM DATA. Only call get_demand_forecast if the user needs a different time range.\n";
            $section .= "- For ML-powered predictions (predict_busy_days, predict_no_show), the ML service may not be available. If it returns an error, fall back to the statistical data from get_demand_forecast or get_no_show_patterns.\n";
            $section .= "- NEVER guess or hallucinate analytics data. If the data is not in your context and the tool fails, say clearly: 'I don't have that data right now.'\n";
            $section .= "- When presenting analytics, cite specific numbers and dates from the data. Be precise.\n";

            return $section;
        });
    }

    /**
     * Execute a tool by name with arguments.
     * Validates role, ownership, and logs the execution.
     */
    public function executeTool(string $toolName, array $arguments, int $userId, string $role): array
    {
        if (!isset($this->tools[$toolName])) {
            return ['success' => false, 'error' => 'The requested action is not available.'];
        }

        $tool = $this->tools[$toolName];

        // Permission check
        if (!$this->permissionService->canUseAgentTool($role, $toolName)) {
            Log::warning('AgentTool: Permission denied', [
                'tool' => $toolName,
                'role' => $role,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'You do not have permission to perform this action.'];
        }

        // Global rate limiting: max tool calls per user per minute
        $rateLimitKey = "agent_tool_ratelimit_{$userId}";
        $callCount = (int) Cache::get($rateLimitKey, 0);
        $maxCallsPerMinute = config('chatbot_unified.agent.max_tool_calls_per_message', 5) * 2;
        if ($callCount >= $maxCallsPerMinute) {
            Log::warning('AgentTool: Rate limited', ['user_id' => $userId, 'count' => $callCount]);
            return ['success' => false, 'error' => 'Too many requests. Please wait a moment.'];
        }
        Cache::put($rateLimitKey, $callCount + 1, 60);

        // Per-tool rate limiting for destructive/sensitive tools
        if ($tool['is_destructive'] ?? false) {
            $perToolKey = "agent_tool_ratelimit_{$userId}_{$toolName}";
            $perToolCount = (int) Cache::get($perToolKey, 0);
            if ($perToolCount >= 3) { // Max 3 destructive calls per tool per 5 minutes
                Log::warning('AgentTool: Per-tool rate limited', ['user_id' => $userId, 'tool' => $toolName]);
                return ['success' => false, 'error' => 'This action has been performed too many times recently. Please wait.'];
            }
            Cache::put($perToolKey, $perToolCount + 1, 300);
        }

        // Validate and sanitize tool arguments against injection
        $validationResult = $this->validateToolArguments($toolName, $arguments);
        if (!$validationResult['valid']) {
            Log::warning('AgentTool: Invalid arguments', [
                'tool' => $toolName,
                'user_id' => $userId,
                'reason' => $validationResult['reason'],
            ]);
            return ['success' => false, 'error' => 'Invalid input provided. Please check your request and try again.'];
        }
        $arguments = $validationResult['sanitized'];

        // Audit log
        Log::info('AgentTool: Executing', [
            'tool' => $toolName,
            'user_id' => $userId,
            'role' => $role,
            'arguments' => $this->sanitizeForLog($arguments),
        ]);

        try {
            $handler = $tool['handler'];
            $result = $handler($arguments, $userId, $role);

            Log::info('AgentTool: Completed', [
                'tool' => $toolName,
                'user_id' => $userId,
                'success' => $result['success'] ?? false,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('AgentTool: Execution failed', [
                'tool' => $toolName,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Tool execution failed. Please try again.'];
        }
    }

    /**
     * Validate tool arguments: type checking, injection prevention, value bounds.
     */
    private function validateToolArguments(string $toolName, array $arguments): array
    {
        $tool = $this->tools[$toolName] ?? null;
        if (!$tool) {
            return ['valid' => false, 'reason' => 'Unknown tool', 'sanitized' => []];
        }

        $sanitized = [];
        $paramDefs = collect($tool['parameters'] ?? []);

        foreach ($arguments as $key => $value) {
            $paramDef = $paramDefs->firstWhere('name', $key);
            if (!$paramDef) {
                continue;
            }

            $type = $paramDef['type'] ?? 'string';
            switch ($type) {
                case 'integer':
                    if (!is_numeric($value)) {
                        return ['valid' => false, 'reason' => "Parameter '{$key}' must be a number", 'sanitized' => []];
                    }
                    $sanitized[$key] = (int) $value;
                    if ($sanitized[$key] < 0 || $sanitized[$key] > 10000) {
                        return ['valid' => false, 'reason' => "Parameter '{$key}' out of range", 'sanitized' => []];
                    }
                    break;

                case 'string':
                    if (!is_string($value)) {
                        $value = (string) $value;
                    }
                    if (mb_strlen($value) > 500) {
                        return ['valid' => false, 'reason' => "Parameter '{$key}' too long", 'sanitized' => []];
                    }
                    // Date format validation for date-like parameters
                    $dateParams = ['date', 'date_from', 'date_to', 'start_date', 'end_date', 'new_date'];
                    if (in_array($key, $dateParams) && !empty($value)) {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !strtotime($value)) {
                            return ['valid' => false, 'reason' => "Parameter '{$key}' must be a valid date (YYYY-MM-DD)", 'sanitized' => []];
                        }
                    }
                    if ($this->containsInjectionPattern($value)) {
                        Log::warning('AgentTool: Injection pattern in argument', [
                            'tool' => $toolName, 'param' => $key,
                        ]);
                        return ['valid' => false, 'reason' => "Invalid characters in '{$key}'", 'sanitized' => []];
                    }
                    $sanitized[$key] = trim($value);
                    break;

                default:
                    $sanitized[$key] = $value;
            }
        }

        foreach ($paramDefs as $paramDef) {
            if (($paramDef['required'] ?? false) && !isset($sanitized[$paramDef['name']])) {
                return ['valid' => false, 'reason' => "Missing required parameter: {$paramDef['name']}", 'sanitized' => []];
            }
        }

        return ['valid' => true, 'reason' => null, 'sanitized' => $sanitized];
    }

    /**
     * Detect SQL injection, command injection, and path traversal patterns.
     */
    private function containsInjectionPattern(string $value): bool
    {
        $patterns = [
            // SQL injection patterns (expanded)
            '/(\bunion\b.*\bselect\b|\binsert\b.*\binto\b|\bdelete\b.*\bfrom\b|\bdrop\b.*\btable\b)/i',
            '/(\bsleep\s*\(|\bwaitfor\b.*\bdelay\b|\bbenchmark\s*\()/i',
            '/(\bexec\s*\(|\bxp_cmdshell\b|\bsp_executesql\b)/i',
            '/(\bor\b\s+\d+\s*=\s*\d+|\bor\b\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?)/i',
            '/(\band\b\s+\d+\s*=\s*\d+)/i',
            '/(\balter\b.*\btable\b|\bcreate\b.*\btable\b|\btruncate\b)/i',
            '/(\bload_file\s*\(|\binto\s+outfile\b|\binto\s+dumpfile\b)/i',
            // Command injection
            '/(;\s*(rm|del|cat|curl|wget|exec|system|bash|sh|powershell|cmd|python|perl|ruby|php|nc|ncat|netcat)\b)/i',
            '/(\|\s*(rm|del|cat|curl|wget|exec|system|bash|sh)\b)/i',
            '/(`[^`]*`)/i',
            // Path traversal (strengthened)
            '/(\.\.\/|\.\.\\\\)/i',
            '/(\/etc\/|\/proc\/|\/dev\/|C:\\\\Windows|C:\\\\System)/i',
            // SQL comment/hex injection
            '/(--\s|\/\*|\*\/|0x[0-9a-f]{4,})/i',
            // Common NoSQL injection
            '/(\$(?:where|gt|gte|lt|lte|ne|in|nin|regex|exists)\b)/i',
            // LDAP injection
            '/([)(|*\\\\].*[)(|*\\\\])/i',
            // Template injection
            '/(\{\{|\}\}|\{%|%\})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a tool exists and is destructive (needs confirmation).
     */
    public function isDestructiveTool(string $toolName): bool
    {
        return $this->tools[$toolName]['is_destructive'] ?? false;
    }

    /**
     * Get tool definitions in native tool-use format (JSON Schema).
     * This enables native function-calling API instead of text-based tool calling.
     *
     * @param string $role User role for permission filtering
     * @return array Native-format tool definitions
     */
    public function getNativeToolDefinitions(string $role): array
    {
        $nativeTools = [];
        foreach ($this->tools as $name => $tool) {
            if (!$this->permissionService->canUseAgentTool($role, $name)) {
                continue;
            }

            $properties = [];
            $required = [];

            foreach ($tool['parameters'] ?? [] as $param) {
                $paramName = $param['name'];
                $paramType = $param['type'] === 'integer' ? 'integer' : 'string';
                $properties[$paramName] = [
                    'type' => $paramType,
                    'description' => $param['description'] ?? '',
                ];
                if ($param['required'] ?? false) {
                    $required[] = $paramName;
                }
            }

            $inputSchema = [
                'type' => 'object',
                'properties' => (object) $properties, // Force {} instead of [] when empty
            ];
            if (!empty($required)) {
                $inputSchema['required'] = $required;
            }

            $description = $tool['description'];
            if ($tool['is_destructive'] ?? false) {
                $description = '[DESTRUCTIVE - requires confirmation] ' . $description;
            }

            $nativeTools[] = [
                'name' => $name,
                'description' => $description,
                'input_schema' => $inputSchema,
            ];
        }

        return $nativeTools;
    }

    // ─── CORE TOOL REGISTRATIONS ──────────────────────────────────

    private function registerCoreTools(): void
    {
        // ── READ TOOLS (non-destructive) ──

        $this->tools['get_my_appointments'] = [
            'description' => 'Get the current user\'s appointments. Can filter by status and date range. Use date_from and date_to for specific periods like "this week" or "this month".',
            'parameters' => [
                ['name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'Filter: pending, approved, completed, cancelled'],
                ['name' => 'date_from', 'type' => 'string', 'required' => false, 'description' => 'Start date (YYYY-MM-DD)'],
                ['name' => 'date_to', 'type' => 'string', 'required' => false, 'description' => 'End date (YYYY-MM-DD)'],
                ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Max results (default 10)'],
            ],
            'required_role' => 'client',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetMyAppointments($args, $userId);
            },
        ];

        $this->tools['get_appointment_details'] = [
            'description' => 'Get detailed information about a specific appointment by ID.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'The appointment ID'],
            ],
            'required_role' => 'client',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetAppointmentDetails($args, $userId, $role);
            },
        ];

        $this->tools['get_available_services'] = [
            'description' => 'List all available services with descriptions and pricing.',
            'parameters' => [],
            'required_role' => 'guest',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetAvailableServices();
            },
        ];

        $this->tools['get_available_slots'] = [
            'description' => 'Get available appointment time slots for a specific date. Checks weekends, blackout dates, lunch breaks, and capacity limits. Always call this before booking.',
            'parameters' => [
                ['name' => 'date', 'type' => 'string', 'required' => true, 'description' => 'Date (YYYY-MM-DD)'],
                ['name' => 'service_id', 'type' => 'integer', 'required' => false, 'description' => 'Service ID to check slots for'],
            ],
            'required_role' => 'guest',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetAvailableSlots($args);
            },
        ];

        $this->tools['get_unavailable_dates'] = [
            'description' => 'Get all unavailable dates (weekends, blackout dates, holidays) within a date range. Useful when a user asks which days are available or unavailable.',
            'parameters' => [
                ['name' => 'start_date', 'type' => 'string', 'required' => true, 'description' => 'Start date (YYYY-MM-DD)'],
                ['name' => 'end_date', 'type' => 'string', 'required' => true, 'description' => 'End date (YYYY-MM-DD)'],
            ],
            'required_role' => 'guest',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetUnavailableDates($args);
            },
        ];

        $this->tools['get_alternative_slots'] = [
            'description' => 'Get alternative less-busy time slots when the user\'s preferred slot is unavailable or full.',
            'parameters' => [
                ['name' => 'date', 'type' => 'string', 'required' => true, 'description' => 'Date (YYYY-MM-DD)'],
                ['name' => 'time', 'type' => 'string', 'required' => false, 'description' => 'Original preferred time (HH:MM)'],
            ],
            'required_role' => 'guest',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetAlternativeSlots($args);
            },
        ];

        $this->tools['get_my_payments'] = [
            'description' => 'Get the current user\'s payment history.',
            'parameters' => [
                ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Max results (default 10)'],
            ],
            'required_role' => 'client',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetMyPayments($args, $userId);
            },
        ];

        $this->tools['check_payment_status'] = [
            'description' => 'Check payment status for a specific appointment.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'The appointment ID'],
            ],
            'required_role' => 'client',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolCheckPaymentStatus($args, $userId, $role);
            },
        ];

        $this->tools['get_notifications'] = [
            'description' => 'Get the user\'s recent notifications. Useful when users ask about updates or alerts.',
            'parameters' => [
                ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Max results (default 10)'],
                ['name' => 'unread_only', 'type' => 'string', 'required' => false, 'description' => 'Set to "true" to only get unread notifications'],
            ],
            'required_role' => 'client',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetNotifications($args, $userId);
            },
        ];

        // ── DESTRUCTIVE / WRITE TOOLS ──

        $this->tools['cancel_appointment'] = [
            'description' => 'Cancel an appointment. Can look up by appointment_id OR by date+time. If user says "cancel my appointment on March 10 at 10:00 AM", use date and time params. If no appointment_id and no date/time, the tool returns the user\'s pending/approved appointments so the user can choose which to cancel. ALWAYS confirm with user first.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => false, 'description' => 'The appointment ID to cancel (optional if date+time provided)'],
                ['name' => 'date', 'type' => 'string', 'required' => false, 'description' => 'Appointment date to cancel (YYYY-MM-DD) — used to look up appointment if no ID given'],
                ['name' => 'time', 'type' => 'string', 'required' => false, 'description' => 'Appointment time to cancel (HH:MM) — used with date to find exact appointment'],
                ['name' => 'reason', 'type' => 'string', 'required' => false, 'description' => 'Reason for cancellation'],
            ],
            'required_role' => 'client',
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolCancelAppointment($args, $userId, $role);
            },
        ];

        $this->tools['check_booking_limit'] = [
            'description' => 'Check the current user\'s booking limit status. Returns how many bookings they can still make, their daily limit, and if at limit, the exact date and time when they can book again. Call this before booking or when user asks about limits.',
            'parameters' => [],
            'required_role' => 'client',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolCheckBookingLimit($userId);
            },
        ];

        $this->tools['book_appointment'] = [
            'description' => 'Book a new appointment. Validates weekends, blackout dates, lunch breaks (12-1PM), daily booking limits, and slot capacity. ALWAYS use get_available_slots first. If the service has public_requirements, proactively inform the user about them before or while booking. Supports booking multiple services.',
            'parameters' => [
                ['name' => 'service_ids', 'type' => 'array', 'required' => false, 'description' => 'Array of Service IDs (numeric) or names. REQUIRED for multi-service bookings. Example: [1, 5] or ["Consultation", "Legal Advice"]'],
                ['name' => 'service_id', 'type' => 'integer', 'required' => false, 'description' => 'Single Service ID or name. Use service_ids for multiple services.'],
                ['name' => 'date', 'type' => 'string', 'required' => true, 'description' => 'Preferred date (YYYY-MM-DD)'],
                ['name' => 'time', 'type' => 'string', 'required' => true, 'description' => 'Preferred time (HH:MM)'],
                ['name' => 'notes', 'type' => 'string', 'required' => false, 'description' => 'Additional notes'],
            ],
            'required_role' => 'guest', // Allow guests to trigger so we can redirect them
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolBookAppointment($args, $userId, $role);
            },
        ];

        $this->tools['reschedule_appointment'] = [
            'description' => 'Reschedule an existing appointment to a new date/time. Same validation rules as booking apply.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'Appointment ID'],
                ['name' => 'new_date', 'type' => 'string', 'required' => true, 'description' => 'New date (YYYY-MM-DD)'],
                ['name' => 'new_time', 'type' => 'string', 'required' => true, 'description' => 'New time (HH:MM)'],
            ],
            'required_role' => 'client',
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolRescheduleAppointment($args, $userId, $role);
            },
        ];

        $this->tools['request_refund'] = [
            'description' => 'Request a refund for a specific appointment payment.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'Appointment ID'],
                ['name' => 'reason', 'type' => 'string', 'required' => true, 'description' => 'Reason for refund request'],
            ],
            'required_role' => 'client',
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolRequestRefund($args, $userId);
            },
        ];

        // ── ADMIN TOOLS ──

        $this->tools['admin_get_pending_appointments'] = [
            'description' => 'Get all pending appointments requiring approval.',
            'parameters' => [
                ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Max results (default 20)'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolAdminGetPendingAppointments($args);
            },
        ];

        $this->tools['admin_approve_appointment'] = [
            'description' => 'Approve a pending appointment. Requires confirmation.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'Appointment ID to approve'],
            ],
            'required_role' => 'admin',
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolAdminApproveAppointment($args, $userId);
            },
        ];

        $this->tools['admin_decline_appointment'] = [
            'description' => 'Decline a pending appointment. Requires confirmation.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'Appointment ID to decline'],
                ['name' => 'reason', 'type' => 'string', 'required' => false, 'description' => 'Reason for declining'],
            ],
            'required_role' => 'admin',
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolAdminDeclineAppointment($args, $userId);
            },
        ];

        $this->tools['admin_get_system_stats'] = [
            'description' => 'Get system-wide statistics and analytics summary.',
            'parameters' => [],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolAdminGetSystemStats();
            },
        ];

        $this->tools['admin_get_appointment_stats'] = [
            'description' => 'Get appointment statistics for a time period: total, pending, approved, completed, cancelled counts.',
            'parameters' => [
                ['name' => 'period', 'type' => 'string', 'required' => false, 'description' => 'Period: today, this_week, this_month, last_month (default: this_month)'],
                ['name' => 'date_from', 'type' => 'string', 'required' => false, 'description' => 'Custom start date (YYYY-MM-DD)'],
                ['name' => 'date_to', 'type' => 'string', 'required' => false, 'description' => 'Custom end date (YYYY-MM-DD)'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolAdminGetAppointmentStats($args);
            },
        ];

        $this->tools['admin_bulk_cancel_appointments'] = [
            'description' => 'Cancel all appointments on a specific date (for maintenance, emergencies, etc.). Shows affected count and requires confirmation.',
            'parameters' => [
                ['name' => 'date', 'type' => 'string', 'required' => true, 'description' => 'Date to cancel all appointments (YYYY-MM-DD)'],
                ['name' => 'reason', 'type' => 'string', 'required' => true, 'description' => 'Reason for mass cancellation'],
            ],
            'required_role' => 'admin',
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolAdminBulkCancelAppointments($args, $userId);
            },
        ];

        $this->tools['get_risk_assessment'] = [
            'description' => 'Get AI-powered risk assessment for an appointment (cancellation/no-show prediction with explanations).',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'Appointment to assess'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetRiskAssessment($args, $userId, $role);
            },
        ];

        $this->tools['get_scheduling_recommendation'] = [
            'description' => 'Get AI-powered optimal scheduling recommendations for a date. Returns slots ranked by success probability and workload balance.',
            'parameters' => [
                ['name' => 'service_id', 'type' => 'integer', 'required' => false, 'description' => 'Service ID'],
                ['name' => 'date', 'type' => 'string', 'required' => false, 'description' => 'Target date (YYYY-MM-DD)'],
            ],
            'required_role' => 'client',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetSchedulingRecommendation($args, $userId, $role);
            },
        ];
    }

    // ─── ANALYTICS TOOL REGISTRATIONS ──────────────────────────────

    private function registerAnalyticsTools(): void
    {
        $this->tools['get_demand_forecast'] = [
            'description' => 'Get demand forecast predicting busy and slow days. Shows expected appointment volume, trending services, and scheduling recommendations.',
            'parameters' => [
                ['name' => 'days_ahead', 'type' => 'integer', 'required' => false, 'description' => 'Number of days to forecast (default 14, max 90)'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetDemandForecast($args);
            },
        ];

        $this->tools['get_no_show_patterns'] = [
            'description' => 'Get no-show pattern analysis: high-risk users, risky time slots, risky days of week. Useful for operational decisions.',
            'parameters' => [
                ['name' => 'days', 'type' => 'integer', 'required' => false, 'description' => 'Analysis period in days (default 90)'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetNoShowPatterns($args);
            },
        ];

        $this->tools['get_auto_alerts'] = [
            'description' => 'Get automated operational alerts: capacity warnings, incomplete appointments, high no-show risks, pending refunds, underbooked days.',
            'parameters' => [],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetAutoAlerts();
            },
        ];

        $this->tools['get_quality_report'] = [
            'description' => 'Get quality and revenue report: completion rates, service performance, revenue analysis.',
            'parameters' => [
                ['name' => 'days', 'type' => 'integer', 'required' => false, 'description' => 'Report period in days (default 30)'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetQualityReport($args);
            },
        ];
    }

    // ─── DECISION SUPPORT TOOL REGISTRATIONS ──────────────────────

    private function registerDecisionSupportTools(): void
    {
        $this->tools['get_workload_optimization'] = [
            'description' => 'Get staff workload distribution and rebalancing insights for a specific date.',
            'parameters' => [
                ['name' => 'date', 'type' => 'string', 'required' => false, 'description' => 'Date to analyze (YYYY-MM-DD, default tomorrow)'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetWorkloadOptimization($args);
            },
        ];

        $this->tools['get_customer_insights'] = [
            'description' => 'Get AI-powered risk profile and history insights for a specific customer.',
            'parameters' => [
                ['name' => 'customer_id', 'type' => 'integer', 'required' => true, 'description' => 'Customer user ID'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetCustomerInsights($args);
            },
        ];

        $this->tools['get_client_engagement_scores'] = [
            'description' => 'Get client engagement scoring: identify churning, at-risk, and high-cancellation clients.',
            'parameters' => [
                ['name' => 'limit', 'type' => 'integer', 'required' => false, 'description' => 'Max clients to return (default 20)'],
            ],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetClientEngagementScores($args);
            },
        ];

        $this->tools['get_operational_recommendations'] = [
            'description' => 'Get data-driven operational recommendations for improving scheduling, reducing no-shows, and optimizing revenue.',
            'parameters' => [],
            'required_role' => 'admin',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolGetOperationalRecommendations();
            },
        ];

        $this->tools['predict_busy_days'] = [
            'description' => 'Predict high-demand days using ML model. Returns predicted busy days with confidence scores.',
            'parameters' => [
                ['name' => 'date_from', 'type' => 'string', 'required' => false, 'description' => 'Start date (YYYY-MM-DD, default tomorrow)'],
                ['name' => 'days_ahead', 'type' => 'integer', 'required' => false, 'description' => 'Number of days to forecast (default 14, max 30)'],
            ],
            'required_role' => 'staff',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolPredictBusyDays($args);
            },
        ];

        $this->tools['predict_no_show'] = [
            'description' => 'Predict the risk of no-show for a specific appointment using ML model. Returns risk score, confidence, and reasoning.',
            'parameters' => [
                ['name' => 'appointment_id', 'type' => 'integer', 'required' => true, 'description' => 'Appointment ID to assess'],
            ],
            'required_role' => 'staff',
            'is_destructive' => false,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolPredictNoShow($args);
            },
        ];

        $this->tools['send_notification'] = [
            'description' => 'Send a notification message to a user.',
            'parameters' => [
                ['name' => 'user_id', 'type' => 'integer', 'required' => true, 'description' => 'Target user ID'],
                ['name' => 'title', 'type' => 'string', 'required' => true, 'description' => 'Notification title'],
                ['name' => 'message', 'type' => 'string', 'required' => true, 'description' => 'Notification body'],
                ['name' => 'type', 'type' => 'string', 'required' => false, 'description' => 'Notification type: info, warning, reminder (default info)'],
            ],
            'required_role' => 'staff',
            'is_destructive' => true,
            'handler' => function (array $args, int $userId, string $role): array {
                return $this->toolSendNotification($args, $userId);
            },
        ];
    }

    // ─── TOOL IMPLEMENTATIONS ─────────────────────────────────────

    private function toolGetMyAppointments(array $args, int $userId): array
    {
        $query = Appointment::where('user_id', $userId)
            ->orderBy('appointment_date', 'desc')
            ->limit(min((int)($args['limit'] ?? 10), 50));

        if (!empty($args['status'])) {
            $allowed = ['pending', 'approved', 'completed', 'cancelled'];
            if (in_array($args['status'], $allowed)) {
                $query->where('status', $args['status']);
            }
        }

        if (!empty($args['date_from'])) {
            $query->where('appointment_date', '>=', $args['date_from']);
        }
        if (!empty($args['date_to'])) {
            $query->where('appointment_date', '<=', $args['date_to']);
        }

        $appointments = $query->with('service:id,name', 'services')->get()->map(fn($a) => [
            'id' => $a->id,
            'service' => $a->service_type ?? ($a->service?->name) ?? 'Service',
            'date' => $a->appointment_date?->format('Y-m-d'),
            'time' => $a->appointment_time,
            'status' => $a->status,
            'payment_status' => $a->payment_status ?? 'unknown',
        ])->toArray();

        return [
            'success' => true,
            'data' => $appointments,
            'count' => count($appointments),
            'message' => count($appointments) > 0
                ? 'Found ' . count($appointments) . ' appointment(s).'
                : 'No appointments found matching your criteria.',
        ];
    }

    private function toolGetAppointmentDetails(array $args, int $userId, string $role): array
    {
        $id = (int)($args['appointment_id'] ?? 0);
        $appointment = Appointment::with('service:id,name', 'user:id,first_name,last_name')->find($id);

        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found.'];
        }

        if ($role === 'client' && $appointment->user_id !== $userId) {
            return ['success' => false, 'error' => 'You can only view your own appointments.'];
        }

        $data = [
            'id' => $appointment->id,
            'service' => $appointment->service?->name ?? $appointment->service_type ?? 'Service',
            'date' => $appointment->appointment_date?->format('Y-m-d'),
            'time' => $appointment->appointment_time,
            'status' => $appointment->status,
            'payment_status' => $appointment->payment_status ?? 'unknown',
            'payment_amount' => $appointment->payment_amount,
            'notes' => $appointment->notes,
            'created_at' => $appointment->created_at?->format('Y-m-d H:i'),
        ];

        // Include client info for admin/staff
        if (in_array($role, ['admin', 'staff'])) {
            $data['client'] = trim(($appointment->user?->first_name ?? '') . ' ' . ($appointment->user?->last_name ?? ''));
        }

        return ['success' => true, 'data' => $data];
    }

    /**
     * Resolve a service_id that may be a numeric ID or a service name string.
     * LLMs often pass the service name (e.g., "Power of Attorney") instead of
     * the numeric ID. This method handles both cases.
     *
     * @return int|null The resolved service ID, or null if not found.
     */
    private function resolveServiceId($serviceIdInput): ?int
    {
        if (is_numeric($serviceIdInput) && (int)$serviceIdInput > 0) {
            return (int)$serviceIdInput;
        }

        if (!is_string($serviceIdInput) || empty(trim($serviceIdInput))) {
            return null;
        }

        $inputNormalized = mb_strtolower(trim($serviceIdInput));

        // 1. Exact match
        $service = Service::whereRaw('LOWER(name) = ?', [$inputNormalized])->first();
        if ($service) return $service->id;

        // 2. Exact match with symbols normalized (e.g. "Follow up" -> "Follow-up")
        $inputSafe = preg_replace('/[^a-z0-9]/', '', $inputNormalized);
        $services = Service::all(['id', 'name']);
        foreach ($services as $s) {
            $sNameSafe = preg_replace('/[^a-z0-9]/', '', mb_strtolower($s->name));
            if ($sNameSafe === $inputSafe) {
                return $s->id;
            }
        }

        // 3. Partial match (LIKE)
        $service = Service::whereRaw('LOWER(name) LIKE ?', ['%' . $inputNormalized . '%'])->first();
        if ($service) return $service->id;

        return null;
    }

    private function resolveServiceIds($serviceIdsInput): array
    {
        if (empty($serviceIdsInput)) {
            return [];
        }

        $inputs = [];
        if (is_array($serviceIdsInput)) {
            $inputs = $serviceIdsInput;
        } else if (is_string($serviceIdsInput)) {
            // Handle multiple delimiters: comma, semicolon, " and ", pipe
            $delimiters = [',', ';', '|', ' and ', ' & '];
            $temp = [$serviceIdsInput];
            foreach ($delimiters as $delim) {
                $newTemp = [];
                foreach ($temp as $item) {
                    $parts = explode($delim, $item);
                    foreach ($parts as $p) {
                        if (trim($p)) $newTemp[] = trim($p);
                    }
                }
                $temp = $newTemp;
            }
            $inputs = $temp;
        }

        $resolvedIds = [];
        foreach ($inputs as $input) {
            $id = $this->resolveServiceId($input);
            if ($id) {
                $resolvedIds[] = $id;
            }
        }

        return array_unique($resolvedIds);
    }

    private function toolGetAvailableServices(): array
    {
        $services = Cache::remember('agent_services_list', 300, function () {
            return Service::where('status', 'active')
                ->orWhere('is_active', true)
                ->select('id', 'name', 'description', 'price', 'duration', 'public_requirements')
                ->get()
                ->toArray();
        });

        return ['success' => true, 'data' => $services, 'count' => count($services)];
    }

    /**
     * FIXED: Now uses the same validation logic as CalendarController.
     * Checks weekends, blackout dates (specific + recurring), lunch breaks,
     * and capacity rules from TimeSlotCapacity.
     */
    private function toolGetAvailableSlots(array $args): array
    {
        $date = $args['date'] ?? now()->addDay()->format('Y-m-d');

        try {
            $parsedDate = Carbon::parse($date);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.'];
        }

        if ($parsedDate->startOfDay()->isPast() && !$parsedDate->isToday()) {
            return ['success' => false, 'error' => 'Cannot check slots for past dates.'];
        }

        // Rule 1: Block weekends
        $dayOfWeek = $parsedDate->dayOfWeek;
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return [
                'success' => true,
                'date' => $date,
                'available_slots' => [],
                'booked_count' => 0,
                'message' => 'The office is closed on weekends (Saturday and Sunday). Please choose a weekday.',
                'blocked_reason' => 'weekend',
            ];
        }

        // Rule 2: Check blackout dates (specific and recurring)
        $blackoutInfo = $this->checkBlackoutDate($date);
        if ($blackoutInfo && $blackoutInfo['blocks_entire_day']) {
            return [
                'success' => true,
                'date' => $date,
                'available_slots' => [],
                'booked_count' => 0,
                'message' => 'This date is not available: ' . ($blackoutInfo['reason'] ?? 'Blocked date'),
                'blocked_reason' => 'blackout_date',
            ];
        }

        // Rule 3: Generate working hour slots from config (excluding lunch break)
        $workStart = (int) config('chatbot_unified.booking.working_hour_start', 8);
        $workEnd = (int) config('chatbot_unified.booking.working_hour_end', 17);
        $lunchStart = (int) config('chatbot_unified.booking.lunch_break_start', 12);
        $lunchEnd = (int) config('chatbot_unified.booking.lunch_break_end', 13);
        $slotInterval = (int) config('chatbot_unified.booking.slot_interval_minutes', 30);

        $slots = [];
        $current = Carbon::parse($date . ' ' . str_pad($workStart, 2, '0', STR_PAD_LEFT) . ':00');
        $end = Carbon::parse($date . ' ' . str_pad($workEnd, 2, '0', STR_PAD_LEFT) . ':00');
        while ($current <= $end) {
            $timeStr = $current->format('H:i');
            // Skip lunch time
            if (!($current->hour >= $lunchStart && $current->hour < $lunchEnd)) {
                $slots[] = $timeStr;
            }
            $current->addMinutes($slotInterval);
        }

        // Rule 4: Filter by blackout time ranges
        if ($blackoutInfo && !$blackoutInfo['blocks_entire_day'] && $blackoutInfo['start_time'] && $blackoutInfo['end_time']) {
            $slots = array_values(array_filter($slots, function ($slot) use ($blackoutInfo) {
                $slotTime = strtotime($slot);
                $startTime = strtotime($blackoutInfo['start_time']);
                $endTime = strtotime($blackoutInfo['end_time']);
                return $slotTime < $startTime || $slotTime >= $endTime;
            }));
        }

        // Rule 5: Check capacity limits per slot (batched query)
        $slotCountsRaw = Appointment::where('appointment_date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->selectRaw('appointment_time, COUNT(*) as count')
            ->groupBy('appointment_time')
            ->get();

        $slotCounts = [];
        foreach ($slotCountsRaw as $countRow) {
            $time = $countRow->appointment_time;
            // Handle both H:i:s and H:i formats from DB
            $formattedTime = date('H:i', strtotime($time));
            $slotCounts[$formattedTime] = ($slotCounts[$formattedTime] ?? 0) + $countRow->count;
        }

        // Pre-load capacity rules
        $dayName = strtolower($parsedDate->englishDayOfWeek);
        $capacityRules = TimeSlotCapacity::where('is_active', true)
            ->where(function ($query) use ($dayName) {
                $query->whereNull('day_of_week')
                      ->orWhere('day_of_week', $dayName);
            })
            ->get();

        $availableSlots = [];
        $slotDetails = [];
        foreach ($slots as $slot) {
            $appointmentCount = $slotCounts[$slot] ?? 0;
            $maxCapacity = $this->getSlotCapacityFromRules($capacityRules, $slot);

            if ($appointmentCount < $maxCapacity) {
                $availableSlots[] = $slot;
                $slotDetails[] = [
                    'time' => $slot,
                    'booked' => $appointmentCount,
                    'capacity' => $maxCapacity,
                    'remaining' => $maxCapacity - $appointmentCount,
                ];
            }
        }

        return [
            'success' => true,
            'date' => $date,
            'day' => $parsedDate->englishDayOfWeek,
            'available_slots' => $availableSlots,
            'slot_details' => $slotDetails,
            'total_available' => count($availableSlots),
            'booked_count' => array_sum($slotCounts),
            'message' => count($availableSlots) > 0
                ? count($availableSlots) . ' slot(s) available on ' . $parsedDate->format('l, M j, Y') . '.'
                : 'No available slots on this date.',
        ];
    }

    /**
     * Get unavailable dates within a range (weekends + blackout dates).
     */
    private function toolGetUnavailableDates(array $args): array
    {
        $startDate = $args['start_date'] ?? now()->format('Y-m-d');
        $endDate = $args['end_date'] ?? now()->addMonth()->format('Y-m-d');

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Invalid date format.'];
        }

        if ($end->diffInDays($start) > 90) {
            return ['success' => false, 'error' => 'Date range too large. Maximum 90 days.'];
        }

        $unavailableDates = [];

        // Weekends
        $current = $start->copy();
        while ($current <= $end) {
            if ($current->dayOfWeek === 0 || $current->dayOfWeek === 6) {
                $unavailableDates[] = [
                    'date' => $current->toDateString(),
                    'reason' => $current->dayName . ' - Office Closed',
                    'type' => 'weekend',
                ];
            }
            $current->addDay();
        }

        // Blackout dates
        $blackouts = BlackoutDate::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('date', [$startDate, $endDate])
              ->orWhere('is_recurring', true);
        })->get();

        foreach ($blackouts as $blackout) {
            if ($blackout->is_recurring && $blackout->recurring_days) {
                $current = $start->copy();
                while ($current <= $end) {
                    $dayName = strtolower($current->englishDayOfWeek);
                    if (in_array($dayName, $blackout->recurring_days)) {
                        $unavailableDates[] = [
                            'date' => $current->toDateString(),
                            'reason' => $blackout->reason ?? 'Not available',
                            'type' => 'blackout',
                        ];
                    }
                    $current->addDay();
                }
            } else {
                $unavailableDates[] = [
                    'date' => $blackout->date ? Carbon::parse($blackout->date)->toDateString() : 'unknown',
                    'reason' => $blackout->reason ?? 'Not available',
                    'type' => 'blackout',
                    'time_range' => $blackout->start_time && $blackout->end_time
                        ? "{$blackout->start_time} - {$blackout->end_time}"
                        : 'All day',
                ];
            }
        }

        // Deduplicate
        $seen = [];
        $deduped = [];
        foreach ($unavailableDates as $entry) {
            $key = $entry['date'] . '|' . $entry['type'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $entry;
            }
        }

        usort($deduped, fn($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'success' => true,
            'data' => array_values($deduped),
            'count' => count($deduped),
            'date_range' => ['from' => $startDate, 'to' => $endDate],
        ];
    }

    /**
     * Get alternative less-busy slots for a given date.
     */
    private function toolGetAlternativeSlots(array $args): array
    {
        try {
            $analyticsService = app(AnalyticsService::class);
            $date = $args['date'] ?? now()->addDay()->format('Y-m-d');
            $time = $args['time'] ?? null;
            $alternatives = $analyticsService->getAlternativeSlotRecommendations($date, $time);
            return ['success' => true, 'data' => $alternatives];
        } catch (\Exception $e) {
            Log::warning('Alternative slots retrieval failed', ['error' => $e->getMessage()]);
            // Fallback: return available slots from the same date
            return $this->toolGetAvailableSlots(['date' => $args['date'] ?? now()->addDay()->format('Y-m-d')]);
        }
    }

    private function toolGetMyPayments(array $args, int $userId): array
    {
        $limit = min((int)($args['limit'] ?? 10), 50);

        $payments = Payment::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'appointment_id' => $p->appointment_id,
                'amount' => $p->amount,
                'status' => $p->status,
                'method' => $p->payment_method,
                'date' => $p->created_at?->format('Y-m-d'),
            ])->toArray();

        return ['success' => true, 'data' => $payments, 'count' => count($payments)];
    }

    private function toolCheckPaymentStatus(array $args, int $userId, string $role): array
    {
        $id = (int)($args['appointment_id'] ?? 0);
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found.'];
        }
        if ($role === 'client' && $appointment->user_id !== $userId) {
            return ['success' => false, 'error' => 'You can only check your own payment status.'];
        }

        $payment = Payment::where('appointment_id', $id)->latest()->first();

        return [
            'success' => true,
            'data' => [
                'appointment_id' => $id,
                'appointment_status' => $appointment->status,
                'payment_status' => $payment?->status ?? $appointment->payment_status ?? 'no_payment_found',
                'amount' => $payment?->amount ?? $appointment->payment_amount,
                'method' => $payment?->payment_method,
                'paid_at' => $payment?->created_at?->format('Y-m-d H:i'),
            ],
        ];
    }

    private function toolGetNotifications(array $args, int $userId): array
    {
        $limit = min((int)($args['limit'] ?? 10), 50);
        $unreadOnly = ($args['unread_only'] ?? '') === 'true';

        $query = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->get()->map(fn($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->format('Y-m-d H:i'),
        ])->toArray();

        $unreadCount = Notification::where('user_id', $userId)->whereNull('read_at')->count();

        return [
            'success' => true,
            'data' => $notifications,
            'count' => count($notifications),
            'unread_count' => $unreadCount,
        ];
    }

    private function toolCancelAppointment(array $args, int $userId, string $role): array
    {
        $id = (int)($args['appointment_id'] ?? 0);
        $date = $args['date'] ?? '';
        $time = $args['time'] ?? '';

        // If no appointment_id and no date/time, return the user's cancellable appointments
        if (!$id && !$date) {
            $pendingAppointments = Appointment::where('user_id', $userId)
                ->where('status', 'pending')
                ->where('appointment_date', '>=', now()->toDateString())
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->with('service:id,name')
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'service' => $a->service?->name ?? $a->service_type ?? 'Service',
                    'date' => $a->appointment_date?->format('Y-m-d'),
                    'date_formatted' => $a->appointment_date?->format('M d, Y'),
                    'time' => $a->appointment_time,
                    'time_formatted' => $a->appointment_time ? Carbon::parse($a->appointment_time)->format('g:i A') : null,
                    'status' => $a->status,
                ])->toArray();

            if (empty($pendingAppointments)) {
                return ['success' => true, 'data' => [], 'message' => 'You have no pending upcoming appointments to cancel. Only pending appointments can be cancelled through the chatbot. Approved, declined, or completed appointments cannot be cancelled.'];
            }

            return [
                'success' => true,
                'action' => 'list_for_cancel',
                'data' => $pendingAppointments,
                'count' => count($pendingAppointments),
                'message' => 'Here are your upcoming appointments that can be cancelled. Please tell me which one you want to cancel (by ID, date, or time).',
            ];
        }

        // Look up by date+time if no appointment_id
        if (!$id && $date) {
            $query = Appointment::where('user_id', $userId)
                ->where('appointment_date', $date)
                ->where('status', 'pending');

            if ($time) {
                $query->where('appointment_time', $time);
            }

            $matches = $query->with('service:id,name')->get();

            if ($matches->isEmpty()) {
                $dateFormatted = Carbon::parse($date)->format('M d, Y');
                $timeInfo = $time ? ' at ' . Carbon::parse($time)->format('g:i A') : '';
                return ['success' => false, 'error' => "No pending appointment found on {$dateFormatted}{$timeInfo}. Only pending appointments can be cancelled through the chatbot."];
            }

            if ($matches->count() > 1 && !$time) {
                // Multiple appointments on that date — ask user to specify time
                $list = $matches->map(fn($a) => [
                    'id' => $a->id,
                    'service' => $a->service?->name ?? $a->service_type ?? 'Service',
                    'date' => $a->appointment_date?->format('Y-m-d'),
                    'date_formatted' => $a->appointment_date?->format('M d, Y'),
                    'time' => $a->appointment_time,
                    'time_formatted' => $a->appointment_time ? Carbon::parse($a->appointment_time)->format('g:i A') : null,
                    'status' => $a->status,
                ])->toArray();

                return [
                    'success' => true,
                    'action' => 'list_for_cancel',
                    'data' => $list,
                    'count' => count($list),
                    'message' => 'You have multiple appointments on that date. Please specify which one to cancel.',
                ];
            }

            $appointment = $matches->first();
            $id = $appointment->id;
        } else {
            $appointment = Appointment::find($id);
        }

        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found.'];
        }

        if ($role === 'client' && $appointment->user_id !== $userId) {
            return ['success' => false, 'error' => 'You can only cancel your own appointments.'];
        }

        if ($appointment->status !== 'pending') {
            $reason = match($appointment->status) {
                'approved' => 'This appointment has already been approved by admin and cannot be cancelled through the chatbot. Please contact the office directly.',
                'declined' => 'This appointment has already been declined by admin.',
                'cancelled' => 'This appointment is already cancelled.',
                'completed' => 'This appointment has already been completed.',
                default => "This appointment cannot be cancelled (current status: {$appointment->status}).",
            };
            return ['success' => false, 'error' => $reason];
        }

        $appointment->load('service:id,name');
        $dateFormatted = $appointment->appointment_date ? Carbon::parse($appointment->appointment_date)->format('M d, Y') : 'N/A';
        $timeFormatted = $appointment->appointment_time ? Carbon::parse($appointment->appointment_time)->format('g:i A') : 'N/A';
        $serviceName = $appointment->service?->name ?? $appointment->service_type ?? 'Service';

        DB::beginTransaction();
        try {
            $appointment->status = 'cancelled';
            $appointment->notes = trim(($appointment->notes ? $appointment->notes . ' | ' : '') . 'Cancelled via chatbot: ' . ($args['reason'] ?? 'No reason provided'));
            $appointment->save();
            DB::commit();

            // Send notification to the appointment owner
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->appointmentCancelled($appointment);
            } catch (\Exception $e) {
                Log::warning('Failed to send cancellation notification', ['error' => $e->getMessage()]);
            }

            return [
                'success' => true,
                'message' => "Appointment #{$id} ({$serviceName} on {$dateFormatted} at {$timeFormatted}) has been cancelled successfully.",
                'data' => [
                    'appointment_id' => $id,
                    'service' => $serviceName,
                    'date' => $appointment->appointment_date?->format('Y-m-d'),
                    'date_formatted' => $dateFormatted,
                    'time' => $appointment->appointment_time,
                    'time_formatted' => $timeFormatted,
                    'new_status' => 'cancelled',
                    'cancelled_at' => now()->format('Y-m-d H:i'),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Failed to cancel appointment. Please try again.'];
        }
        
        // Clear appointment caches after cancellation for real-time updates
        try {
            Cache::forget("chatbot_appointments_user_{$userId}_all");
            Cache::forget("chatbot_appointments_user_{$userId}_pending");
            Cache::forget("chatbot_appointments_user_{$userId}_approved");
            Cache::forget("chatbot_appointments_user_{$userId}_completed");
            Cache::forget("chatbot_appointments_user_{$userId}_cancelled");
            Cache::forget("chatbot_booking_limit_{$userId}");
        } catch (\Exception $e) {
            Log::debug('Cache clearing failed after cancellation: ' . $e->getMessage());
        }
    }

    /**
     * Check the user's current booking limit status.
     */
    private function toolCheckBookingLimit(int $userId): array
    {
        $settings = AppointmentSettings::getCurrent();
        $limit = $settings->daily_booking_limit_per_user ?? 3;
        $remaining = AppointmentSettings::getRemainingBookingsForUser($userId);
        $hasReachedLimit = AppointmentSettings::userHasReachedDailyLimit($userId);
        $nextAvailable = $hasReachedLimit ? AppointmentSettings::getNextAvailableTime($userId) : null;
        $nextFormatted = $nextAvailable ? $nextAvailable->format('M d, Y \a\t g:i A') : null;

        $message = $hasReachedLimit
            ? "You have reached your daily booking limit of {$limit} appointments per 24 hours. You can book again on {$nextFormatted}."
            : "You can still book {$remaining} more appointment(s) today. Your daily limit is {$limit} per 24 hours.";

        return [
            'success' => true,
            'data' => [
                'daily_limit' => $limit,
                'remaining_bookings' => $remaining ?? 0,
                'has_reached_limit' => $hasReachedLimit,
                'next_available_time' => $nextAvailable?->toIso8601String(),
                'next_available_formatted' => $nextFormatted,
            ],
            'message' => $message,
        ];
    }

    /**
     * Book appointment via chatbot — follows the exact same flow as AppointmentController::store().
     * Validates weekends, blackout dates, unavailable dates, lunch breaks, daily booking limits,
     * uses pessimistic locking, dispatches events, sends email, and logs action.
     */
    /**
     * Validate a booking without actually creating it.
     * Used to check availability before asking for confirmation.
     */
    public function validateBookingSlot(array $args, int $userId): array
    {
        $serviceIds = $this->resolveServiceIds($args['service_ids'] ?? $args['service_id'] ?? []);
        $date = $args['date'] ?? '';
        $time = $args['time'] ?? '';

        if (empty($serviceIds) || !$date || !$time) {
            return ['valid' => false, 'error' => 'Service IDs, date, and time are required.'];
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        if ($services->isEmpty()) {
            return ['valid' => false, 'error' => 'No valid services found.'];
        }

        try {
            $parsedDate = Carbon::parse($date);
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.'];
        }

        // Check: not in the past
        if ($parsedDate->startOfDay()->isPast() && !$parsedDate->isToday()) {
            return ['valid' => false, 'error' => 'Cannot book appointments in the past.'];
        }

        // Check: no weekends
        if ($parsedDate->dayOfWeek === 0 || $parsedDate->dayOfWeek === 6) {
            return ['valid' => false, 'error' => 'Appointments cannot be booked on weekends.'];
        }

        // Check: working hours
        $workStart = (int) config('chatbot_unified.booking.working_hour_start', 8);
        $workEnd = (int) config('chatbot_unified.booking.working_hour_end', 17);
        $parsedTime = Carbon::parse($time);
        if ($parsedTime->hour < $workStart || $parsedTime->hour >= $workEnd) {
            $startFormatted = Carbon::createFromTime($workStart, 0)->format('g:i A');
            $endFormatted = Carbon::createFromTime($workEnd, 0)->format('g:i A');
            return ['valid' => false, 'error' => "Appointments are only available from {$startFormatted} to {$endFormatted}."];
        }

        // Check: lunch break
        $lunchStart = (int) config('chatbot_unified.booking.lunch_break_start', 12);
        $lunchEnd = (int) config('chatbot_unified.booking.lunch_break_end', 13);
        if ($parsedTime->hour >= $lunchStart && $parsedTime->hour < $lunchEnd) {
            $lunchStartFormatted = Carbon::createFromTime($lunchStart, 0)->format('g:i A');
            $lunchEndFormatted = Carbon::createFromTime($lunchEnd, 0)->format('g:i A');
            return ['valid' => false, 'error' => "This time falls during the lunch break ({$lunchStartFormatted} - {$lunchEndFormatted})."];
        }

        // Check: unavailable dates
        $isUnavailable = UnavailableDate::where('date', $date)
            ->where(function ($query) use ($time) {
                $query->where('all_day', true)
                    ->orWhere(function ($q) use ($time) {
                        $q->where('all_day', false)
                          ->where('start_time', '<=', $time)
                          ->where('end_time', '>=', $time);
                    });
            })
            ->exists();
        if ($isUnavailable) {
            return ['valid' => false, 'error' => 'Selected date and time is not available.'];
        }

        // Check: blackout dates
        $blackoutInfo = $this->checkBlackoutDate($date);
        if ($blackoutInfo && $blackoutInfo['blocks_entire_day']) {
            return ['valid' => false, 'error' => 'This date is not available: ' . ($blackoutInfo['reason'] ?? 'Blocked date')];
        }
        if ($blackoutInfo && !$blackoutInfo['blocks_entire_day'] && $blackoutInfo['start_time'] && $blackoutInfo['end_time']) {
            $slotTime = strtotime($time);
            $blockStart = strtotime($blackoutInfo['start_time']);
            $blockEnd = strtotime($blackoutInfo['end_time']);
            if ($slotTime >= $blockStart && $slotTime < $blockEnd) {
                return ['valid' => false, 'error' => "This time slot is blocked ({$blackoutInfo['start_time']} - {$blackoutInfo['end_time']}): " . ($blackoutInfo['reason'] ?? 'Not available')];
            }
        }

        // Check: daily booking limit
        if (AppointmentSettings::userHasReachedDailyLimit($userId)) {
            $nextAvailable = AppointmentSettings::getNextAvailableTime($userId);
            $nextFormatted = $nextAvailable ? $nextAvailable->format('M d, Y \a\t g:i A') : null;
            $message = "You have reached your daily booking limit.";
            if ($nextFormatted) $message .= " Next available: {$nextFormatted}";
            return ['valid' => false, 'error' => $message];
        }

        // Check: slot capacity
        $dayName = strtolower($parsedDate->englishDayOfWeek);
        $capacityRule = TimeSlotCapacity::where('is_active', true)
            ->where(function ($q) use ($dayName) {
                $q->where('day_of_week', $dayName)->orWhereNull('day_of_week');
            })
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->orderByRaw('CASE WHEN day_of_week IS NOT NULL THEN 0 ELSE 1 END')
            ->first();
        $defaultCapacity = (int) config('chatbot_unified.booking.default_slot_capacity', 3);
        $maxPerSlot = $capacityRule ? $capacityRule->max_appointments_per_slot : $defaultCapacity;

        $existing = Appointment::where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        if ($existing >= $maxPerSlot) {
            return ['valid' => false, 'error' => 'This time slot is fully booked.'];
        }

        // Check: user not already booked at this time
        $userAlreadyBooked = Appointment::where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($userAlreadyBooked) {
            return ['valid' => false, 'error' => 'You already have a booking at this date and time.'];
        }

        // All checks passed
        $serviceNames = $services->pluck('name')->join(', ');
        $dateFormatted = $parsedDate->format('M d, Y');
        $timeFormatted = Carbon::parse($time)->format('g:i A');

        return [
            'valid' => true,
            'message' => "✓ All checks passed. Ready to book {$serviceNames} on {$dateFormatted} at {$timeFormatted}.",
            'service_names' => $serviceNames,
            'date_formatted' => $dateFormatted,
            'time_formatted' => $timeFormatted,
        ];
    }

    private function toolBookAppointment(array $args, int $userId, string $role): array
    {
        // GUEST CHECK: Check role instead of just userId to be safe
        if ($role === 'guest' || $userId === 0) {
            return [
                'success' => false,
                'error' => 'GUESTS CANNOT BOOK DIRECTLY. Please register or log in to complete your booking.',
                'action_required' => 'auth',
                'message' => 'I have all your details ready! To finalize the booking, please log in or create an account. Your selected services, date, and time will be preserved.',
            ];
        }

        // SECURITY: Block booking if user's profile is incomplete
        $user = User::find($userId);
        if ($user && !$user->profile_completed) {
            return [
                'success' => false,
                'error' => 'Your profile is incomplete. Please complete your profile (first name, last name, phone number, and address) before booking an appointment.',
                'requires_profile_completion' => true,
            ];
        }

        // VALIDATION: Check all constraints first
        // This prevents asking for confirmation if validation will fail anyway
        $validationResult = $this->validateBookingSlot($args, $userId);
        if (!$validationResult['valid']) {
            return ['success' => false, 'error' => $validationResult['error']];
        }

        $serviceIds = $this->resolveServiceIds($args['service_ids'] ?? $args['service_id'] ?? []);
        $date = $args['date'] ?? '';
        $time = $args['time'] ?? '';

        $services = Service::whereIn('id', $serviceIds)->get();
        $primaryService = $services->first();
        $totalPrice = $services->sum('price');
        $serviceNames = $services->pluck('name')->join(', ');

        try {
            $parsedDate = Carbon::parse($date);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.'];
        }

        // Validate: not in the past
        if ($parsedDate->startOfDay()->isPast() && !$parsedDate->isToday()) {
            return ['success' => false, 'error' => 'Cannot book appointments in the past.'];
        }

        // Validate: no weekends (same as AppointmentController)
        if ($parsedDate->dayOfWeek === 0 || $parsedDate->dayOfWeek === 6) {
            return ['success' => false, 'error' => 'Appointments cannot be booked on weekends (Saturday and Sunday). Please choose a weekday.'];
        }

        // Validate: working hours from config
        $workStart = (int) config('chatbot_unified.booking.working_hour_start', 8);
        $workEnd = (int) config('chatbot_unified.booking.working_hour_end', 17);
        $parsedTime = Carbon::parse($time);
        if ($parsedTime->hour < $workStart || $parsedTime->hour >= $workEnd) {
            $startFormatted = Carbon::createFromTime($workStart, 0)->format('g:i A');
            $endFormatted = Carbon::createFromTime($workEnd, 0)->format('g:i A');
            return ['success' => false, 'error' => "Appointments are only available from {$startFormatted} to {$endFormatted}."];
        }

        // Validate: lunch break from config
        $lunchStart = (int) config('chatbot_unified.booking.lunch_break_start', 12);
        $lunchEnd = (int) config('chatbot_unified.booking.lunch_break_end', 13);
        if ($parsedTime->hour >= $lunchStart && $parsedTime->hour < $lunchEnd) {
            $lunchStartFormatted = Carbon::createFromTime($lunchStart, 0)->format('g:i A');
            $lunchEndFormatted = Carbon::createFromTime($lunchEnd, 0)->format('g:i A');
            return ['success' => false, 'error' => "This time falls during the lunch break ({$lunchStartFormatted} - {$lunchEndFormatted}). Please choose a different time."];
        }

        // Validate: unavailable dates (same as AppointmentController)
        $isUnavailable = UnavailableDate::where('date', $date)
            ->where(function ($query) use ($time) {
                $query->where('all_day', true)
                    ->orWhere(function ($q) use ($time) {
                        $q->where('all_day', false)
                          ->where('start_time', '<=', $time)
                          ->where('end_time', '>=', $time);
                    });
            })
            ->exists();
        if ($isUnavailable) {
            return ['success' => false, 'error' => 'Selected date and time is not available for booking.'];
        }

        // Validate: blackout dates including recurring (same as AppointmentController)
        $blackoutInfo = $this->checkBlackoutDate($date);
        if ($blackoutInfo && $blackoutInfo['blocks_entire_day']) {
            return ['success' => false, 'error' => 'This date is not available: ' . ($blackoutInfo['reason'] ?? 'Blocked date') . '. Please choose a different date.'];
        }
        if ($blackoutInfo && !$blackoutInfo['blocks_entire_day'] && $blackoutInfo['start_time'] && $blackoutInfo['end_time']) {
            $slotTime = strtotime($time);
            $blockStart = strtotime($blackoutInfo['start_time']);
            $blockEnd = strtotime($blackoutInfo['end_time']);
            if ($slotTime >= $blockStart && $slotTime < $blockEnd) {
                return ['success' => false, 'error' => "This time slot is blocked ({$blackoutInfo['start_time']} - {$blackoutInfo['end_time']}): " . ($blackoutInfo['reason'] ?? 'Not available')];
            }
        }

        // Validate: daily booking limit using AppointmentSettings (same as AppointmentController)
        if (AppointmentSettings::userHasReachedDailyLimit($userId)) {
            $settings = AppointmentSettings::getCurrent();
            $nextAvailable = AppointmentSettings::getNextAvailableTime($userId);
            $nextFormatted = $nextAvailable ? $nextAvailable->format('M d, Y \a\t g:i A') : null;
            $message = "You have reached your booking limit of {$settings->daily_booking_limit_per_user} appointments per 24 hours.";
            if ($nextFormatted) {
                $message .= " You can book again on {$nextFormatted}.";
            }
            
            // Try to find an alternative slot on the next available day
            $alternativeSlots = [];
            if ($nextAvailable) {
                $altDate = $nextAvailable->format('Y-m-d');
                $altSlotsResult = $this->toolGetAlternativeSlots(['date' => $altDate]);
                if ($altSlotsResult['success'] ?? false) {
                    $alternativeSlots = $altSlotsResult['data'] ?? [];
                }
            }

            return [
                'success' => false, 
                'error' => $message,
                'alternative_slots' => $alternativeSlots,
                'instruction_to_ai' => 'Inform the user they have reached their limit and offer them the alternative slots provided in the data.'
            ];
        }

        // Get slot capacity (same logic as AppointmentController::getSlotCapacity)
        $dayName = strtolower($parsedDate->englishDayOfWeek);
        $capacityRule = TimeSlotCapacity::where('is_active', true)
            ->where(function ($q) use ($dayName) {
                $q->where('day_of_week', $dayName)->orWhereNull('day_of_week');
            })
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->orderByRaw('CASE WHEN day_of_week IS NOT NULL THEN 0 ELSE 1 END')
            ->first();
        $defaultCapacity = (int) config('chatbot_unified.booking.default_slot_capacity', 3);
        $maxPerSlot = $capacityRule ? $capacityRule->max_appointments_per_slot : $defaultCapacity;

        // Atomic booking with pessimistic locking (same as AppointmentController)
        try {
            $appointment = DB::transaction(function () use ($userId, $serviceIds, $services, $totalPrice, $serviceNames, $date, $time, $maxPerSlot, $args) {
                $existing = Appointment::where('appointment_date', $date)
                    ->where('appointment_time', $time)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->count();

                if ($existing >= $maxPerSlot) {
                    throw new \Exception('SLOT_FULL');
                }

                $userAlreadyBooked = Appointment::where('appointment_date', $date)
                    ->where('appointment_time', $time)
                    ->where('user_id', $userId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->exists();

                if ($userAlreadyBooked) {
                    throw new \Exception('USER_DUPLICATE');
                }

                $primaryService = $services->first();
                $typeKeys = array_flip(Appointment::getTypes());
                $type = $typeKeys[$primaryService->name] ?? strtolower(str_replace(' ', '_', $primaryService->name));

                $appointment = Appointment::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'service_id' => $primaryService->id,
                    'service_type' => $serviceNames,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'notes' => $args['notes'] ?? null,
                    'original_price' => $totalPrice,
                ]);
                // Set protected field explicitly (same as AppointmentController)
                $appointment->payment_amount = $totalPrice;
                $appointment->status = 'pending';
                $appointment->save();

                // Sync multiple services
                $syncData = [];
                foreach ($services as $srv) {
                    $syncData[$srv->id] = ['price_at_booking' => $srv->price];
                }
                $appointment->services()->sync($syncData);

                return $appointment;
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_FULL') {
                $altSlotsResult = $this->toolGetAlternativeSlots(['date' => $date]);
                $alternativeSlots = $altSlotsResult['success'] ?? false ? $altSlotsResult['data'] : [];
                return [
                    'success' => false, 
                    'error' => 'This time slot is fully booked.',
                    'alternative_slots' => $alternativeSlots,
                    'instruction_to_ai' => 'Inform the user the time slot is fully booked and recommend the alternative slots provided in the data.'
                ];
            }
            if ($e->getMessage() === 'USER_DUPLICATE') {
                return ['success' => false, 'error' => 'You already have a pending or approved appointment at this date and time.'];
            }
            Log::error('Chatbot booking failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return ['success' => false, 'error' => 'Failed to book appointment. Please try again.'];
        }

        // Post-booking: ActionLog (same as AppointmentController)
        try {
            $dateFormatted = Carbon::parse($appointment->appointment_date)->format('M d, Y');
            $timeFormatted = Carbon::parse($appointment->appointment_time)->format('g:i A');
            ActionLog::log(
                'book_appointment',
                "Booked appointment for {$serviceNames} on {$dateFormatted} at {$timeFormatted} (via chatbot)",
                'Appointment',
                $appointment->id
            );
        } catch (\Exception $e) {
            Log::debug('Failed to record ActionLog for chatbot booking: ' . $e->getMessage());
        }

        // Post-booking: Broadcast AppointmentCreated event (same as AppointmentController)
        try {
            $appointment->load(['user', 'staff', 'service', 'services']);
            event(new AppointmentCreated($appointment));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast AppointmentCreated event from chatbot: ' . $e->getMessage());
        }

        // Post-booking: Notify all admins about new pending appointment
        try {
            $user = User::find($userId);
            $clientName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $notifyDate = Carbon::parse($appointment->appointment_date)->format('M d, Y');
            $notifyTime = Carbon::parse($appointment->appointment_time)->format('g:i A');
            NotificationService::notifyAdmins(
                'new_appointment',
                'New Appointment Pending Approval',
                "New appointment #{$appointment->id} from {$clientName} for {$serviceNames} on {$notifyDate} at {$notifyTime} is awaiting your approval.",
                [
                    'icon' => 'calendar',
                    'color' => 'blue',
                    'related_id' => $appointment->id,
                    'related_type' => 'Appointment',
                    'data' => [
                        'appointment_id' => $appointment->id,
                        'client_name' => $clientName,
                        'service' => $serviceNames,
                        'date' => $notifyDate,
                        'time' => $notifyTime,
                    ]
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to notify admins about new chatbot booking: ' . $e->getMessage());
        }

        // Post-booking: Send confirmation email (same as AppointmentController)
        try {
            $user = $user ?? User::find($userId);
            if ($user && $user->email) {
                Mail::to($user->email)->send(new AppointmentConfirmationMail($appointment));
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send confirmation email from chatbot booking: ' . $e->getMessage());
        }

        // Post-booking: Update user last_activity_at (same as AppointmentController)
        try {
            User::where('id', $userId)->update(['last_activity_at' => now()]);
        } catch (\Exception $e) {
            Log::warning('Failed to update last_activity_at from chatbot booking: ' . $e->getMessage());
        }

        // Invalidate appointment caches for this user so new appointment shows up immediately
        try {
            // Clear chatbot appointment caches (matching ChatbotRealTimeDataService keys)
            Cache::forget("chatbot_appointments_user_{$userId}_all");
            Cache::forget("chatbot_appointments_user_{$userId}_pending");
            Cache::forget("chatbot_appointments_user_{$userId}_approved");
            Cache::forget("chatbot_appointments_user_{$userId}_completed");
            Cache::forget("chatbot_appointments_user_{$userId}_cancelled");
            
            // Clear booking limit cache
            Cache::forget("chatbot_booking_limit_{$userId}");
            AppointmentSettings::clearRequestCache($userId);
        } catch (\Exception $e) {
            Log::debug('Cache clearing failed: ' . $e->getMessage());
        }

        // Get updated booking limit status after this booking
        $remainingBookings = AppointmentSettings::getRemainingBookingsForUser($userId);
        $settings = AppointmentSettings::getCurrent();
        $dateFormatted = $parsedDate->format('M d, Y');
        $timeFormatted = Carbon::parse($time)->format('g:i A');

        $message = "Appointment booked successfully! Your appointment ID is #{$appointment->id} for {$serviceNames} on {$dateFormatted} at {$timeFormatted}. Status: pending approval.";
        if ($remainingBookings !== null && $remainingBookings > 0) {
            $message .= " You can still book {$remainingBookings} more appointment(s) today.";
        } elseif ($remainingBookings === 0) {
            $nextAvailable = AppointmentSettings::getNextAvailableTime($userId);
            $nextFormatted = $nextAvailable ? $nextAvailable->format('M d, Y \a\t g:i A') : null;
            $message .= " You have now reached your daily booking limit of {$settings->daily_booking_limit_per_user}.";
            if ($nextFormatted) {
                $message .= " You can book again on {$nextFormatted}.";
            }
        }

        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'appointment_id' => $appointment->id,
                'service' => $serviceNames,
                'date' => $date,
                'date_formatted' => $dateFormatted,
                'time' => $time,
                'time_formatted' => $timeFormatted,
                'status' => 'pending',
                'day' => $parsedDate->englishDayOfWeek,
                'remaining_bookings_today' => $remainingBookings ?? 0,
                'daily_limit' => $settings->daily_booking_limit_per_user,
            ],
        ];
    }

    private function toolRescheduleAppointment(array $args, int $userId, string $role): array
    {
        $id = (int)($args['appointment_id'] ?? 0);
        $newDate = $args['new_date'] ?? '';
        $newTime = $args['new_time'] ?? '';

        $appointment = Appointment::find($id);
        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found.'];
        }
        if ($role === 'client' && $appointment->user_id !== $userId) {
            return ['success' => false, 'error' => 'You can only reschedule your own appointments.'];
        }
        if (in_array($appointment->status, ['cancelled', 'completed'])) {
            return ['success' => false, 'error' => "Cannot reschedule a {$appointment->status} appointment."];
        }

        try {
            $parsedDate = Carbon::parse($newDate);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.'];
        }

        if ($parsedDate->startOfDay()->isPast() && !$parsedDate->isToday()) {
            return ['success' => false, 'error' => 'New date must be in the future.'];
        }

        if ($parsedDate->dayOfWeek === 0 || $parsedDate->dayOfWeek === 6) {
            return ['success' => false, 'error' => 'Cannot reschedule to a weekend.'];
        }

        // Working hours from config
        $workStart = (int) config('chatbot_unified.booking.working_hour_start', 8);
        $workEnd = (int) config('chatbot_unified.booking.working_hour_end', 17);
        $parsedTime = Carbon::parse($newTime);
        if ($parsedTime->hour < $workStart || $parsedTime->hour >= $workEnd) {
            $startFormatted = Carbon::createFromTime($workStart, 0)->format('g:i A');
            $endFormatted = Carbon::createFromTime($workEnd, 0)->format('g:i A');
            return ['success' => false, 'error' => "Appointments are only available from {$startFormatted} to {$endFormatted}."];
        }

        // Lunch break from config
        $lunchStart = (int) config('chatbot_unified.booking.lunch_break_start', 12);
        $lunchEnd = (int) config('chatbot_unified.booking.lunch_break_end', 13);
        if ($parsedTime->hour >= $lunchStart && $parsedTime->hour < $lunchEnd) {
            $lunchStartFormatted = Carbon::createFromTime($lunchStart, 0)->format('g:i A');
            $lunchEndFormatted = Carbon::createFromTime($lunchEnd, 0)->format('g:i A');
            return ['success' => false, 'error' => "Cannot reschedule to the lunch break ({$lunchStartFormatted} - {$lunchEndFormatted})."];
        }

        // Unavailable dates check (same as AppointmentController)
        $isUnavailable = UnavailableDate::where('date', $newDate)
            ->where(function ($query) use ($newTime) {
                $query->where('all_day', true)
                    ->orWhere(function ($q) use ($newTime) {
                        $q->where('all_day', false)
                          ->where('start_time', '<=', $newTime)
                          ->where('end_time', '>=', $newTime);
                    });
            })
            ->exists();
        if ($isUnavailable) {
            return ['success' => false, 'error' => 'The new date and time is not available for booking.'];
        }

        // Blackout dates check
        $blackoutInfo = $this->checkBlackoutDate($newDate);
        if ($blackoutInfo && $blackoutInfo['blocks_entire_day']) {
            return ['success' => false, 'error' => 'The new date is not available: ' . ($blackoutInfo['reason'] ?? 'Blocked date')];
        }
        if ($blackoutInfo && !$blackoutInfo['blocks_entire_day'] && $blackoutInfo['start_time'] && $blackoutInfo['end_time']) {
            $slotTime = strtotime($newTime);
            $blockStart = strtotime($blackoutInfo['start_time']);
            $blockEnd = strtotime($blackoutInfo['end_time']);
            if ($slotTime >= $blockStart && $slotTime < $blockEnd) {
                return ['success' => false, 'error' => "This time slot is blocked ({$blackoutInfo['start_time']} - {$blackoutInfo['end_time']}): " . ($blackoutInfo['reason'] ?? 'Not available')];
            }
        }

        DB::beginTransaction();
        try {
            $appointment->appointment_date = $newDate;
            $appointment->appointment_time = $newTime;
            $appointment->status = 'pending';
            $appointment->save();
            DB::commit();

            return [
                'success' => true,
                'message' => "Appointment #{$id} has been rescheduled to {$parsedDate->format('l, M j, Y')} at {$newTime}. Status reset to pending approval.",
                'data' => [
                    'appointment_id' => $id,
                    'new_date' => $newDate,
                    'new_time' => $newTime,
                    'status' => 'pending',
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Failed to reschedule. Please try again.'];
        }
    }

    private function toolRequestRefund(array $args, int $userId): array
    {
        $appointmentId = (int)($args['appointment_id'] ?? 0);
        $reason = $args['reason'] ?? '';

        $appointment = Appointment::where('id', $appointmentId)->where('user_id', $userId)->first();
        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found or you don\'t have access.'];
        }

        $existingRefund = Refund::where('appointment_id', $appointmentId)->whereIn('status', ['pending', 'approved'])->first();
        if ($existingRefund) {
            return ['success' => false, 'error' => 'A refund request already exists for this appointment (status: ' . $existingRefund->status . ').'];
        }

        $payment = Payment::where('appointment_id', $appointmentId)->where('status', 'paid')->first();
        if (!$payment) {
            return ['success' => false, 'error' => 'No completed payment found for this appointment.'];
        }

        DB::beginTransaction();
        try {
            $refund = Refund::create([
                'user_id' => $userId,
                'appointment_id' => $appointmentId,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'reason' => $reason,
                'status' => 'pending',
            ]);
            DB::commit();

            return [
                'success' => true,
                'message' => "Refund request submitted (ID: #{$refund->id}). Amount: ₱" . number_format($payment->amount, 2) . ". Status: pending review.",
                'data' => ['refund_id' => $refund->id, 'amount' => $payment->amount, 'status' => 'pending'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Failed to submit refund request. Please try again.'];
        }
    }

    private function toolAdminGetPendingAppointments(array $args): array
    {
        $limit = min((int)($args['limit'] ?? 20), 100);
        $pending = Appointment::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->with('user:id,first_name,last_name', 'service:id,name')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'client' => trim(($a->user?->first_name ?? '') . ' ' . ($a->user?->last_name ?? '')),
                'service' => $a->service?->name ?? $a->service_type,
                'date' => $a->appointment_date?->format('Y-m-d'),
                'time' => $a->appointment_time,
                'days_waiting' => $a->created_at?->diffInDays(now()),
            ])->toArray();

        return ['success' => true, 'data' => $pending, 'count' => count($pending)];
    }

    private function toolAdminApproveAppointment(array $args, int $adminUserId): array
    {
        $id = (int)($args['appointment_id'] ?? 0);
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found.'];
        }
        if ($appointment->status !== 'pending') {
            return ['success' => false, 'error' => "Appointment is already {$appointment->status}."];
        }

        $oldStatus = $appointment->status;

        try {
            // Transaction with pessimistic lock (same as AppointmentController::approve)
            DB::transaction(function () use ($appointment, $oldStatus, $adminUserId) {
                $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

                if ($appointment->status !== 'pending') {
                    throw new \RuntimeException('Appointment status changed concurrently');
                }

                $appointment->status = 'approved';
                $appointment->processed_by = $adminUserId;
                $appointment->save();
                $appointment->refresh();

                // Log the action
                $serviceType = $appointment->service_type ?? $appointment->type;
                ActionLog::log(
                    'approve',
                    "Approved appointment (via chatbot) for {$appointment->user->first_name} {$appointment->user->last_name} - {$serviceType} on {$appointment->appointment_date} at {$appointment->appointment_time}",
                    'Appointment',
                    $appointment->id
                );

                // Send in-app message to client
                $appointmentDate = Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
                $appointmentTime = Carbon::parse($appointment->appointment_time)->format('g:i A');
                $serviceType = $appointment->service_type ?? $appointment->type;

                $messageText = "✓ Your appointment has been APPROVED!\n\n" .
                    "📅 Date: " . $appointmentDate . "\n" .
                    "⏰ Time: " . $appointmentTime . "\n" .
                    "📋 Service: " . $serviceType . "\n\n" .
                    "Please arrive on time for your appointment. If you need to reschedule, please contact us.";

                Message::create([
                    'sender_id' => $adminUserId,
                    'receiver_id' => $appointment->user_id,
                    'message' => $messageText,
                    'read' => false,
                ]);
            });

            // Re-fetch after transaction
            $appointment = Appointment::with(['user', 'staff', 'service'])->findOrFail($id);

            // Non-critical notifications OUTSIDE transaction (same as AppointmentController)
            try {
                NotificationService::appointmentApproved($appointment);
                NotificationService::notifyCashiersAppointmentApproved($appointment);
            } catch (\Exception $e) {
                Log::warning('Non-critical notification failed in chatbot approve: ' . $e->getMessage());
            }

            // Send approval email (same as AppointmentController)
            try {
                if ($appointment->user && $appointment->user->email) {
                    Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                }
                if ($appointment->staff_id && $appointment->staff && $appointment->staff->email) {
                    Mail::to($appointment->staff->email)->send(new AppointmentStatusMail($appointment));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send approval email from chatbot: ' . $e->getMessage());
            }

            // Broadcast update
            try {
                event(new AppointmentUpdated($appointment));
            } catch (\Exception $e) {
                Log::debug('Failed to broadcast AppointmentUpdated from chatbot approve: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'message' => "Appointment #{$id} has been approved. The client has been notified and the cashier has been alerted for payment processing.",
                'data' => ['appointment_id' => $id, 'status' => 'approved'],
            ];
        } catch (\Exception $e) {
            Log::error('Chatbot approve error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to approve appointment: ' . $e->getMessage()];
        }
    }

    private function toolAdminDeclineAppointment(array $args, int $adminUserId): array
    {
        $id = (int)($args['appointment_id'] ?? 0);
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return ['success' => false, 'error' => 'Appointment not found.'];
        }
        if (!in_array($appointment->status, ['pending', 'approved'])) {
            return ['success' => false, 'error' => "Appointment is already {$appointment->status} and cannot be declined."];
        }

        $reason = $args['reason'] ?? 'Declined via chatbot';
        $oldStatus = $appointment->status;

        try {
            // Transaction with pessimistic lock (same as AppointmentController::decline)
            DB::transaction(function () use ($appointment, $oldStatus, $adminUserId, $reason) {
                $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);

                if (!in_array($appointment->status, ['pending', 'approved'])) {
                    throw new \RuntimeException('Appointment status changed concurrently');
                }

                $appointment->status = 'declined';
                $appointment->processed_by = $adminUserId;
                $appointment->decline_reason = $reason;
                $appointment->save();
                $appointment->refresh();

                // Log the action
                $serviceType = $appointment->service_type ?? $appointment->type ?? 'Unknown';
                $reasonText = $reason ? " - Reason: {$reason}" : '';
                ActionLog::log(
                    'decline_appointment',
                    "Declined appointment (via chatbot) for {$appointment->user->first_name} {$appointment->user->last_name} ({$serviceType}){$reasonText}",
                    'Appointment',
                    $appointment->id
                );

                // Send in-app message to client
                $appointmentDate = Carbon::parse($appointment->appointment_date)->format('l, F d, Y');
                $appointmentTime = Carbon::parse($appointment->appointment_time)->format('g:i A');
                $serviceType = $appointment->service_type ?? $appointment->type;

                $messageText = "✕ Your appointment has been DECLINED.\n\n";
                $messageText .= "📅 Date: " . $appointmentDate . "\n";
                $messageText .= "⏰ Time: " . $appointmentTime . "\n";
                $messageText .= "📋 Service: " . $serviceType . "\n";

                if ($reason) {
                    $messageText .= "\n❌ Reason: " . $reason . "\n";
                }

                $messageText .= "\nPlease contact our support team if you have any questions or would like to discuss alternative options.";

                Message::create([
                    'sender_id' => $adminUserId,
                    'receiver_id' => $appointment->user_id,
                    'message' => $messageText,
                    'read' => false,
                ]);
            });

            // Re-fetch after transaction
            $appointment = Appointment::with(['user', 'staff', 'service'])->findOrFail($id);

            // Non-critical notification OUTSIDE transaction (same as AppointmentController)
            try {
                NotificationService::appointmentDeclined($appointment, $reason);
            } catch (\Exception $e) {
                Log::warning('Non-critical notification failed in chatbot decline: ' . $e->getMessage());
            }

            // Send decline email (same as AppointmentController)
            try {
                if ($appointment->user && $appointment->user->email) {
                    Mail::to($appointment->user->email)->send(new AppointmentStatusMail($appointment));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send decline email from chatbot: ' . $e->getMessage());
            }

            // Broadcast update
            try {
                event(new AppointmentUpdated($appointment));
            } catch (\Exception $e) {
                Log::debug('Failed to broadcast AppointmentUpdated from chatbot decline: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'message' => "Appointment #{$id} has been declined." . ($reason ? " Reason: {$reason}" : ''),
                'data' => ['appointment_id' => $id, 'status' => 'declined'],
            ];
        } catch (\Exception $e) {
            Log::error('Chatbot decline error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to decline appointment: ' . $e->getMessage()];
        }
    }

    private function toolAdminGetSystemStats(): array
    {
        return [
            'success' => true,
            'data' => $this->dataService->getSystemStats(),
        ];
    }

    /**
     * Get appointment statistics by time period.
     */
    private function toolAdminGetAppointmentStats(array $args): array
    {
        $period = $args['period'] ?? 'this_month';

        switch ($period) {
            case 'today':
                $dateFrom = now()->startOfDay();
                $dateTo = now()->endOfDay();
                break;
            case 'this_week':
                $dateFrom = now()->startOfWeek();
                $dateTo = now()->endOfWeek();
                break;
            case 'last_month':
                $dateFrom = now()->subMonth()->startOfMonth();
                $dateTo = now()->subMonth()->endOfMonth();
                break;
            case 'this_month':
            default:
                $dateFrom = now()->startOfMonth();
                $dateTo = now()->endOfMonth();
                break;
        }

        // Allow custom date override
        if (!empty($args['date_from'])) {
            $dateFrom = Carbon::parse($args['date_from'])->startOfDay();
        }
        if (!empty($args['date_to'])) {
            $dateTo = Carbon::parse($args['date_to'])->endOfDay();
        }

        $stats = Appointment::whereBetween('appointment_date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
            ")
            ->first();

        $revenue = Payment::whereHas('appointment', function ($q) use ($dateFrom, $dateTo) {
            $q->whereBetween('appointment_date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]);
        })->where('status', 'paid')->sum('amount');

        return [
            'success' => true,
            'data' => [
                'period' => $period,
                'date_range' => [
                    'from' => $dateFrom->format('Y-m-d'),
                    'to' => $dateTo->format('Y-m-d'),
                ],
                'total_appointments' => (int) $stats->total,
                'pending' => (int) $stats->pending,
                'approved' => (int) $stats->approved,
                'completed' => (int) $stats->completed,
                'cancelled' => (int) $stats->cancelled,
                'declined' => (int) $stats->declined,
                'completion_rate' => $stats->total > 0
                    ? round(($stats->completed / $stats->total) * 100, 1) . '%'
                    : '0%',
                'revenue' => number_format($revenue, 2),
            ],
        ];
    }

    /**
     * Bulk cancel appointments on a specific date.
     */
    private function toolAdminBulkCancelAppointments(array $args, int $adminUserId): array
    {
        $date = $args['date'] ?? '';
        $reason = $args['reason'] ?? 'Bulk cancellation via chatbot';

        if (!$date) {
            return ['success' => false, 'error' => 'Date is required.'];
        }

        $affected = Appointment::whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->get();

        if ($affected->isEmpty()) {
            return ['success' => false, 'error' => "No active appointments found on {$date}."];
        }

        DB::beginTransaction();
        try {
            $cancelledCount = 0;
            $notificationService = app(NotificationService::class);

            foreach ($affected as $appointment) {
                $appointment->status = 'cancelled';
                $appointment->cancelled_at = now();
                $appointment->cancellation_reason = $reason;
                $appointment->save();
                $cancelledCount++;

                try {
                    $notificationService->appointmentCancelled($appointment);
                } catch (\Exception $e) {
                    Log::warning('Bulk cancel notification failed for appointment #' . $appointment->id);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "Successfully cancelled {$cancelledCount} appointment(s) on {$date}. Affected users have been notified.",
                'data' => [
                    'date' => $date,
                    'cancelled_count' => $cancelledCount,
                    'reason' => $reason,
                    'affected_clients' => $affected->map(fn($a) => [
                        'appointment_id' => $a->id,
                        'client' => $a->user?->full_name ?? 'Unknown',
                    ])->toArray(),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Bulk cancellation failed. Please try again.'];
        }
    }

    private function toolGetRiskAssessment(array $args, int $userId, string $role): array
    {
        $id = (int)($args['appointment_id'] ?? 0);
        try {
            $decisionService = app(MLDecisionSupportService::class);
            $assessment = $decisionService->getAppointmentRiskAssessment($id);
            return ['success' => true, 'data' => $assessment];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Risk assessment unavailable.'];
        }
    }

    private function toolGetSchedulingRecommendation(array $args, int $userId, string $role): array
    {
        try {
            $decisionService = app(MLDecisionSupportService::class);
            $date = $args['date'] ?? now()->addDay()->format('Y-m-d');
            $slots = $decisionService->getTimeSlotRecommendations($date);
            return ['success' => true, 'data' => $slots];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Scheduling recommendation unavailable.'];
        }
    }

    // ─── ANALYTICS TOOL IMPLEMENTATIONS ──────────────────────────

    private function toolGetDemandForecast(array $args): array
    {
        try {
            $analyticsService = app(AnalyticsService::class);
            $daysAhead = min((int)($args['days_ahead'] ?? 14), 90);
            $forecast = $analyticsService->getDemandForecast($daysAhead);
            return ['success' => true, 'data' => $forecast];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Demand forecast unavailable: ' . $e->getMessage()];
        }
    }

    private function toolGetNoShowPatterns(array $args): array
    {
        try {
            $analyticsService = app(AnalyticsService::class);
            $days = min((int)($args['days'] ?? 90), 365);
            $patterns = $analyticsService->getNoShowPatterns($days);
            return ['success' => true, 'data' => $patterns];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'No-show pattern analysis unavailable: ' . $e->getMessage()];
        }
    }

    private function toolGetAutoAlerts(): array
    {
        try {
            $analyticsService = app(AnalyticsService::class);
            $alerts = $analyticsService->getAutoAlerts();
            return ['success' => true, 'data' => $alerts];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Auto alerts unavailable: ' . $e->getMessage()];
        }
    }

    private function toolGetQualityReport(array $args): array
    {
        try {
            $analyticsService = app(AnalyticsService::class);
            $days = min((int)($args['days'] ?? 30), 365);
            $report = $analyticsService->getQualityReport($days);
            return ['success' => true, 'data' => $report];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Quality report unavailable: ' . $e->getMessage()];
        }
    }

    // ─── DECISION SUPPORT TOOL IMPLEMENTATIONS ──────────────────

    private function toolGetWorkloadOptimization(array $args): array
    {
        try {
            $decisionService = app(MLDecisionSupportService::class);
            $date = $args['date'] ?? now()->addDay()->format('Y-m-d');
            $optimization = $decisionService->getWorkloadOptimization($date);
            return ['success' => true, 'data' => $optimization];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Workload optimization unavailable: ' . $e->getMessage()];
        }
    }

    private function toolGetCustomerInsights(array $args): array
    {
        $customerId = (int)($args['customer_id'] ?? 0);
        if (!$customerId) {
            return ['success' => false, 'error' => 'Customer ID is required.'];
        }

        try {
            $decisionService = app(MLDecisionSupportService::class);
            $insights = $decisionService->getCustomerInsights($customerId);
            return ['success' => true, 'data' => $insights];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Customer insights unavailable.'];
        }
    }

    private function toolGetClientEngagementScores(array $args): array
    {
        try {
            $decisionService = app(MLDecisionSupportService::class);
            $limit = min((int)($args['limit'] ?? 20), 100);
            // Client engagement scores derived from appointment history
            $users = \App\Models\User::where('role', 'client')
                ->withCount(['appointments', 'appointments as completed_count' => function ($q) {
                    $q->where('status', 'completed');
                }, 'appointments as cancelled_count' => function ($q) {
                    $q->where('status', 'cancelled');
                }])
                ->having('appointments_count', '>', 0)
                ->orderByDesc('appointments_count')
                ->limit($limit)
                ->get();

            $scores = $users->map(function ($user) {
                $total = $user->appointments_count;
                $completionRate = $total > 0 ? round($user->completed_count / $total * 100, 1) : 0;
                return [
                    'user_id' => $user->id,
                    'name' => "{$user->first_name} {$user->last_name}",
                    'total_appointments' => $total,
                    'completed' => $user->completed_count,
                    'cancelled' => $user->cancelled_count,
                    'engagement_score' => $completionRate,
                ];
            });

            return ['success' => true, 'data' => $scores->toArray()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Client engagement scores unavailable: ' . $e->getMessage()];
        }
    }

    private function toolGetOperationalRecommendations(): array
    {
        try {
            $decisionService = app(MLDecisionSupportService::class);
            $date = now()->format('Y-m-d');
            $workload = $decisionService->getWorkloadOptimization($date);
            return ['success' => true, 'data' => [
                'workload' => $workload,
                'note' => 'Operational recommendations based on current workload analysis.',
            ]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Operational recommendations unavailable: ' . $e->getMessage()];
        }
    }

    private function toolPredictBusyDays(array $args): array
    {
        try {
            $mlClient = app(MLServiceClient::class);
            if (!$mlClient->isAvailable()) {
                return ['success' => false, 'error' => 'ML service unavailable. Cannot predict busy days.'];
            }

            $startDate = $args['date_from'] ?? now()->addDay()->format('Y-m-d');
            $daysAhead = min((int)($args['days_ahead'] ?? 14), 30);
            $predictions = [];

            for ($i = 0; $i < $daysAhead; $i++) {
                $date = Carbon::parse($startDate)->addDays($i)->format('Y-m-d');
                $result = $mlClient->predictSlotRank($date);
                $slots = $result['data'] ?? [];

                $avgBookings = collect($slots)->avg('current_bookings') ?? 0;
                $avgScore = collect($slots)->avg('predicted_score') ?? 0;
                $fullSlots = collect($slots)->where('status', 'full')->count();

                $demandLevel = 'low';
                if ($avgBookings >= 5 || $fullSlots >= 3) $demandLevel = 'high';
                elseif ($avgBookings >= 3 || $fullSlots >= 1) $demandLevel = 'medium';

                $predictions[] = [
                    'date' => $date,
                    'day' => Carbon::parse($date)->format('l'),
                    'demand_level' => $demandLevel,
                    'avg_bookings' => round($avgBookings, 1),
                    'full_slots' => $fullSlots,
                    'avg_success_score' => round($avgScore, 3),
                ];
            }

            return ['success' => true, 'data' => [
                'predictions' => $predictions,
                'high_demand_days' => collect($predictions)->where('demand_level', 'high')->values()->toArray(),
            ]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Busy day prediction unavailable: ' . $e->getMessage()];
        }
    }

    private function toolPredictNoShow(array $args): array
    {
        $appointmentId = (int)($args['appointment_id'] ?? 0);
        if (!$appointmentId) {
            return ['success' => false, 'error' => 'Appointment ID is required.'];
        }

        try {
            $mlClient = app(MLServiceClient::class);
            if (!$mlClient->isAvailable()) {
                return ['success' => false, 'error' => 'ML service unavailable. Cannot predict no-show risk.'];
            }

            $result = $mlClient->predictRisk($appointmentId);
            if (isset($result['error'])) {
                return ['success' => false, 'error' => $result['error']];
            }

            if (($result['status'] ?? '') === 'no_model') {
                return ['success' => false, 'error' => 'No trained ML model. Train a model first via Decision Support > Data Quality.'];
            }

            $data = $result['data'] ?? [];
            return ['success' => true, 'data' => [
                'appointment_id' => $appointmentId,
                'risk_score' => $data['risk_score'] ?? null,
                'risk_level' => $data['risk_level'] ?? 'unknown',
                'confidence' => $data['confidence'] ?? 0,
                'confidence_label' => $data['confidence_label'] ?? 'low',
                'reasoning' => $data['reasoning'] ?? [],
                'feature_importances' => array_slice($data['feature_importances'] ?? [], 0, 5),
            ]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'No-show prediction unavailable: ' . $e->getMessage()];
        }
    }

    private function toolSendNotification(array $args, int $senderId): array
    {
        $targetId = (int)($args['user_id'] ?? 0);
        $title = trim($args['title'] ?? '');
        $message = trim($args['message'] ?? '');
        $type = $args['type'] ?? 'info';

        if (!$targetId || !$title || !$message) {
            return ['success' => false, 'error' => 'user_id, title, and message are required.'];
        }

        $user = User::find($targetId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        try {
            Notification::create([
                'user_id' => $targetId,
                'title' => substr($title, 0, 255),
                'message' => substr($message, 0, 1000),
                'type' => in_array($type, ['info', 'warning', 'reminder', 'success']) ? $type : 'info',
                'is_read' => false,
            ]);

            return ['success' => true, 'data' => [
                'sent_to' => "{$user->first_name} {$user->last_name}",
                'title' => $title,
                'type' => $type,
            ]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to send notification: ' . $e->getMessage()];
        }
    }

    // ─── SHARED HELPERS ──────────────────────────────────────────

    /**
     * Check blackout dates (specific and recurring) for a given date.
     * Replicates CalendarController's getBlackoutDate() logic.
     */
    private function checkBlackoutDate(string $date): ?array
    {
        $parsedDate = Carbon::parse($date);
        $dayName = strtolower($parsedDate->englishDayOfWeek);

        // Check specific date blackout
        $blackout = BlackoutDate::where('date', $date)
            ->where('is_recurring', false)
            ->first();

        if ($blackout) {
            return [
                'date' => $date,
                'reason' => $blackout->reason,
                'blocks_entire_day' => !$blackout->start_time || !$blackout->end_time,
                'start_time' => $blackout->start_time,
                'end_time' => $blackout->end_time,
            ];
        }

        // Check recurring blackout
        $recurringBlackout = BlackoutDate::where('is_recurring', true)
            ->whereJsonContains('recurring_days', $dayName)
            ->first();

        if ($recurringBlackout) {
            return [
                'date' => $date,
                'reason' => $recurringBlackout->reason,
                'blocks_entire_day' => !$recurringBlackout->start_time || !$recurringBlackout->end_time,
                'start_time' => $recurringBlackout->start_time,
                'end_time' => $recurringBlackout->end_time,
            ];
        }

        return null;
    }

    /**
     * Get slot capacity from pre-loaded capacity rules.
     */
    private function getSlotCapacityFromRules($capacityRules, string $time): int
    {
        $matching = $capacityRules->first(function ($rule) use ($time) {
            return $rule->start_time <= $time && $rule->end_time > $time;
        });

        return $matching ? $matching->max_appointments_per_slot : (int) config('chatbot_unified.booking.default_slot_capacity', 3);
    }

    /**
     * Sanitize sensitive data before logging.
     */
    private function sanitizeForLog(array $args): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key'];
        foreach ($args as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $args[$key] = '[REDACTED]';
            }
        }
        return $args;
    }
}
