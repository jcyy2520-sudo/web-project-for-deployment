<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ChatbotSecurityService — MANDATORY, Always-On Security Layer
 * 
 * This service is NOT feature-flagged. It runs on EVERY chatbot request.
 * It enforces zero-trust security principles:
 * 
 * 1. Role is NEVER determined from user input — only from server-side auth
 * 2. All prompt injection attempts are detected and blocked
 * 3. Role escalation attempts are detected, logged, and refused
 * 4. Cryptographic role binding ensures role integrity
 * 5. Output is validated to prevent leaking role-inappropriate data
 * 
 * DESIGN PRINCIPLES:
 * - Fail-closed: if any check fails, deny access
 * - Non-destructive: does not modify existing working flows
 * - Always-on: cannot be disabled via feature flags
 * - Performant: uses efficient pattern matching and caching
 */
class ChatbotSecurityService
{
    /**
     * Role hierarchy — defines the privilege level of each role.
     * Higher number = higher privilege. Used to detect escalation.
     * Loaded from config with sensible defaults.
     */
    private function getRoleHierarchy(): array
    {
        return config('chatbot_unified.security.role_hierarchy', [
            'guest'   => 0,
            'client'  => 1,
            'staff'   => 2,
            'cashier' => 3,
            'admin'   => 4,
        ]);
    }

    /**
     * HMAC secret for role binding tokens.
     * Uses APP_KEY as the HMAC key for cryptographic role assertions.
     */
    private function getHmacKey(): string
    {
        $key = config('app.key');
        if (!$key) {
            throw new \RuntimeException('APP_KEY is not set. Cannot secure chatbot role assertions.');
        }
        return $key;
    }

    // ──────────────────────────────────────────────────────────────
    // 1. PROMPT INJECTION DETECTION (comprehensive, multi-layer)
    // ──────────────────────────────────────────────────────────────

    /**
     * Detect prompt injection attempts with multi-layer analysis.
     * This catches a wide range of techniques including:
     * - Direct instruction override
     * - Role impersonation
     * - System prompt extraction
     * - Jailbreak patterns (DAN, Developer Mode, etc.)
     * - Unicode/encoding evasion
     * - Multi-turn escalation
     * 
     * @param string $message Raw user message
     * @return array ['injection_detected' => bool, 'type' => string|null, 'risk_score' => int]
     */
    public function detectPromptInjection(string $message): array
    {
        $normalizedMessage = $this->normalizeForSecurity($message);
        $flags = [];
        $riskScore = 0;

        // Layer 1: Direct instruction override patterns
        $instructionOverride = [
            '/(?:ignore|disregard|forget|override|bypass|skip|cancel|stop\s+following|do\s+not\s+follow)\s+(?:your|all|the|any|previous|above|prior|system|original)\s+(?:instructions|rules|prompt|guidelines|constraints|directives|programming|training)/i',
            '/(?:new|updated|revised|different|my)\s+(?:instructions|rules|prompt|system\s+prompt|directives|guidelines)/i',
            '/(?:from\s+now\s+on|starting\s+now|henceforth)\s+(?:you\s+(?:are|will|must|should)|ignore|disregard)/i',
            '/(?:i\s+am\s+(?:your|the)\s+(?:developer|creator|admin|administrator|owner|master|programmer|operator))/i',
            '/(?:system\s*:\s*|<\|system\|>|<<SYS>>|\[SYSTEM\]|\[INST\])/i',
            '/(?:human\s*:\s*|assistant\s*:\s*|user\s*:\s*).*(?:system\s*:\s*)/is',
        ];
        foreach ($instructionOverride as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'instruction_override';
                $riskScore += 60;
                break;
            }
        }

        // Layer 2: Role impersonation patterns
        $roleImpersonation = [
            '/(?:you\s+are\s+now|pretend\s+(?:to\s+be|you\s*(?:\'re|are))|act\s+(?:as\s+(?:if\s+you\s+(?:are|were)\s+)?|like)\s*(?:(?:an?|the)\s+)?(?:(?:system|the)\s+)?)(?:admin|administrator|cashier|staff|superuser|root|manager|moderator|developer)/i',
            '/(?:roleplay|role[_\s-]*play)\s+(?:as|like)\s+(?:(?:an?|the)\s+)?(?:admin|administrator|cashier|staff|system)/i',
            '/(?:switch|change|set|update|modify|elevate|promote)\s+(?:my\s+)?(?:role|access|permission|privilege)\s+(?:to|as|into)\s+(?:admin|administrator|cashier|staff)/i',
            '/(?:i\s+am\s+(?:(?:an?|the)\s+)?(?:admin|administrator|cashier|staff|authorized|superuser))\b/i',
            '/(?:grant|give|assign|make)\s+(?:me|myself)\s+(?:admin|administrator|cashier|staff)\s*(?:role|access|permission|privilege)?/i',
            '/(?:my\s+role\s+is|i\s+have\s+(?:admin|administrator|cashier|staff)\s+(?:access|role|privilege|permission))/i',
            '/(?:i\s+(?:can|should\s+be\s+able\s+to|need\s+to)\s+(?:approve|reject|manage|process\s+payment|view\s+all|access\s+admin))/i',
            '/(?:treat\s+me\s+(?:as|like)\s+(?:(?:an?|the)\s+)?(?:admin|administrator|cashier|staff))/i',
            '/(?:for\s+(?:the\s+)?(?:purposes?|sake|rest)\s+(?:of\s+)?(?:this|our)\s+(?:chat|conversation|session)).*(?:admin|administrator|cashier|staff|system\s+admin)/i',
        ];
        foreach ($roleImpersonation as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'role_impersonation';
                $riskScore += 70;
                break;
            }
        }

        // Layer 3: System prompt extraction
        $promptExtraction = [
            '/(?:show|reveal|display|print|output|repeat|tell)\s+(?:me\s+)?(?:your|the)\s+(?:system\s+prompt|instructions|initial\s+prompt|system\s+message|original\s+prompt|hidden\s+prompt|rules|training\s+data|guidelines)/i',
            '/(?:what\s+were\s+you\s+told|what\s+are\s+your\s+(?:secret\s+)?instructions|how\s+were\s+you\s+programmed)/i',
            '/(?:repeat|echo|dump|export).{0,20}(?:prompt|instruction|system|config)/i',
            '/(?:print|output|show).{0,20}(?:above|everything|all).{0,20}(?:text|content|prompt|instruction)/i',
            '/(?:show|give|tell)\s+(?:me\s+)?(?:all\s+)?(?:api\s+keys?|secrets?|credentials?|passwords?|tokens?|env(?:ironment)?\s+variables?)/i',
        ];
        foreach ($promptExtraction as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'prompt_extraction';
                $riskScore += 50;
                break;
            }
        }

        // Layer 4: Jailbreak techniques
        $jailbreak = [
            '/\b(?:dan|dude|evil|devil|chaos|dark|shadow|unrestricted|unfiltered|uncensored)\s*(?:mode|version|persona|personality)\b/i',
            '/\b(?:jailbreak|jail\s*break|unlock|unleash|liberate|unchain|free\s+yourself)\b/i',
            '/(?:developer|debug|test|maintenance|admin|god|sudo|root)\s*mode/i',
            '/(?:do\s+anything\s+now|no\s+restrictions|without\s+limits|without\s+rules|ignore\s+safety|bypass\s+filters)/i',
            '/(?:pretend\s+there\s+(?:are|is)\s+no\s+(?:rules|restrictions|limits|safety|filters|guidelines))/i',
            '/(?:respond\s+(?:as\s+if|like)\s+(?:you\s+have\s+)?no\s+(?:rules|restrictions|filters|limits))/i',
            '/(?:in\s+this\s+(?:scenario|reality|universe|dimension|world)\s+(?:you|there)\s+(?:are|have)\s+no\s+(?:rules|restrictions))/i',
        ];
        foreach ($jailbreak as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'jailbreak_attempt';
                $riskScore += 55;
                break;
            }
        }

        // Layer 5: Encoding/obfuscation evasion
        $evasion = [
            // Base64 encoded strings
            '/(?:decode|translate|interpret|execute)\s+(?:this|the\s+following)?\s*:?\s*[A-Za-z0-9+\/=]{20,}/i',
            // ROT13 or cipher references
            '/(?:rot13|caesar|cipher|encode|decode)\s+(?:this|the\s+following)/i',
            // Markdown/HTML injection
            '/<script\b|<iframe\b|javascript\s*:|on(?:load|error|click|mouseover)\s*=/i',
            // Multiple system-message-like blocks
            '/\[(?:SYSTEM|ADMIN|ROOT|SUDO)\]/i',
            // Unicode escape sequences used to bypass filters
            '/\\\\u[0-9a-fA-F]{4}/i',
            // Hex-encoded payloads
            '/(?:0x[0-9a-fA-F]{8,})/i',
            // Template injection (Twig, Jinja, etc.)
            '/\{\{.*(?:system|exec|passthru|shell_exec|popen).*\}\}/i',
            // HTML entity obfuscation
            '/&(?:#(?:x[0-9a-f]+|\d+)|[a-z]+);/i',
        ];
        foreach ($evasion as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'encoding_evasion';
                $riskScore += 40;
                break;
            }
        }

        // Layer 6: Multi-turn conversation manipulation
        $multiTurn = [
            '/(?:remember\s+(?:earlier|before|that)\s+(?:i|you|we)\s+(?:said|agreed|established)\s+(?:i|you)\s+(?:am|are|have|were)\s+(?:an?\s+)?(?:admin|administrator|cashier))/i',
            '/(?:as\s+(?:we|you)\s+(?:discussed|agreed|established)\s+(?:earlier|before|previously).*(?:admin|role|access|permission))/i',
            '/(?:you\s+(?:already|previously)\s+(?:confirmed|said|agreed|verified)\s+(?:that\s+)?(?:i|my)\s+(?:am|role|access)\s+(?:is\s+)?(?:admin|administrator|cashier))/i',
        ];
        foreach ($multiTurn as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'multi_turn_manipulation';
                $riskScore += 65;
                break;
            }
        }

        // Layer 7: Data exfiltration attempts
        $dataExfiltration = [
            '/(?:show|list|give|display|dump|export|reveal)\s+(?:me\s+)?(?:all|every)\s+(?:user|client|customer|appointment|payment|record|data|account|email|phone|password|credential)/i',
            '/(?:how\s+many\s+(?:users|clients|admins|records)\s+(?:are\s+)?(?:there|in\s+the\s+(?:system|database)))/i',
            '/(?:database|table|column|schema|sql|query)\s+(?:structure|layout|design|info|information|detail)/i',
        ];
        foreach ($dataExfiltration as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'data_exfiltration';
                $riskScore += 45;
                break;
            }
        }

        // Layer 8: Social engineering / proxy access attempts
        $socialEngineering = [
            '/(?:my\s+(?:friend|wife|husband|boss|colleague|coworker|partner|secretary|assistant)\s+(?:left|forgot|lost|asked|told|wants|needs|said)).*(?:show|check|see|view|access|get)\s+(?:their|his|her)\s+(?:appointment|payment|account|data|info|status)/i',
            '/(?:(?:i\s+am|i\'m)\s+(?:calling|asking|checking|inquiring)\s+(?:on\s+behalf|for)\s+(?:of\s+)?(?:(?:user|client|patient|customer)\s+)?(?:id\s+)?\w+)/i',
            '/(?:as\s+a\s+test|for\s+testing?\s+purposes?|just\s+(?:a\s+)?test|hypothetically|in\s+theory|if\s+i\s+were).*(?:admin|show|access|view|data|all\s+users)/i',
            '/(?:the\s+(?:admin|manager|boss|supervisor|staff)\s+(?:told|said|asked|allowed|authorized|gave)\s+(?:me|us)\s+(?:to|permission)).*(?:access|view|see|check|approve|manage)/i',
            '/(?:just\s+this\s+once|make\s+an?\s+exception|special\s+case|emergency|urgent\s+(?:need|request)).*(?:access|view|show|see|get|data)/i',
            '/(?:i\s+(?:forgot|lost)\s+my\s+(?:password|credentials|login)).*(?:reset|give|show|access|bypass)/i',
        ];

        // Layer 9: Tool call injection attempts (fence-based)
        $toolCallInjection = [
            '/```\s*tool_call/i',
            '/\{\s*"tool"\s*:\s*"[^"]+"\s*,\s*"arguments"\s*:/i',
        ];
        foreach ($toolCallInjection as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'tool_call_injection';
                $riskScore += 80;
                break;
            }
        }
        foreach ($socialEngineering as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                $flags[] = 'social_engineering';
                $riskScore += 50;
                break;
            }
        }

        $riskScore = min(100, $riskScore);
        $injectionDetected = !empty($flags);

        return [
            'injection_detected' => $injectionDetected,
            'flags' => $flags,
            'risk_score' => $riskScore,
            'severity' => $this->classifyRisk($riskScore),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // 2. ROLE ESCALATION DETECTION
    // ──────────────────────────────────────────────────────────────

    /**
     * Detect if a user message is attempting to escalate privileges.
     * Compares the user's actual role against what they're trying to access.
     * 
     * @param string $currentRole The user's verified server-side role
     * @param string $message The user's message
     * @return array ['escalation_detected' => bool, 'target_role' => string|null, 'details' => string|null]
     */
    public function detectRoleEscalation(string $currentRole, string $message): array
    {
        $normalizedMessage = $this->normalizeForSecurity($message);
        $currentLevel = $this->getRoleHierarchy()[$currentRole] ?? 0;

        // Role-specific features that would indicate escalation
        $roleFeaturePatterns = [
            'admin' => [
                '/\b(?:all\s+users|manage\s+users|user\s+management|system\s+settings|admin\s+dashboard|system\s+analytics)\b/i',
                '/\b(?:approve\s+appointment|decline\s+appointment|manage\s+services|view\s+all\s+appointments)\b/i',
                '/\b(?:audit\s+log|action\s+log|system\s+health|configure\s+system)\b/i',
                '/\b(?:manage\s+announcements|block\s+user|deactivate\s+user|user\s+roles)\b/i',
                '/\b(?:admin\s+(?:access|panel|controls?|functions?|features?|tools?|page|privileges?))\b/i',
                '/\b(?:i\s+need|give\s+me|grant\s+me|want)\s+(?:admin|full|administrator|staff)\s+(?:access|rights|permission|privilege|control)\b/i',
            ],
            'cashier' => [
                '/\b(?:process\s+payment|pending\s+payments|shift\s+report|daily\s+(?:financial\s+)?summary)\b/i',
                '/\b(?:verify\s+receipt|process\s+refund|approved\s+refunds|cash\s+summary|transaction\s+report)\b/i',
                '/\b(?:payment\s+processing|financial\s+report|daily\s+collection)\b/i',
            ],
            'client' => [
                '/\b(?:my\s+appointments|my\s+payments|my\s+refunds|my\s+profile|cancel\s+my)\b/i',
                '/\b(?:book\s+(?:an?\s+)?appointment|check\s+(?:my\s+)?status|my\s+(?:payment\s+)?history)\b/i',
            ],
        ];

        foreach ($roleFeaturePatterns as $targetRole => $patterns) {
            $targetLevel = $this->getRoleHierarchy()[$targetRole] ?? 0;

            // Only flag if the target role is HIGHER than the current role
            if ($targetLevel <= $currentLevel) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $normalizedMessage)) {
                    return [
                        'escalation_detected' => true,
                        'target_role' => $targetRole,
                        'current_role' => $currentRole,
                        'details' => "Attempted to access {$targetRole}-level functionality from {$currentRole} role",
                    ];
                }
            }
        }

        return [
            'escalation_detected' => false,
            'target_role' => null,
            'current_role' => $currentRole,
            'details' => null,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // 3. CRYPTOGRAPHIC ROLE BINDING
    // ──────────────────────────────────────────────────────────────

    /**
     * Create a signed role assertion token.
     * This binds the user's role to their ID and a timestamp, preventing tampering.
     * 
     * @param int|null $userId
     * @param string $role
     * @return string Signed role token (base64)
     */
    public function createRoleAssertion(?int $userId, string $role): string
    {
        $payload = json_encode([
            'uid' => $userId,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + 300, // 5-minute validity
        ]);

        $signature = hash_hmac('sha256', $payload, $this->getHmacKey());
        return base64_encode($payload . '.' . $signature);
    }

    /**
     * Verify a signed role assertion token.
     * Returns the verified role or null if invalid/expired.
     * 
     * @param string $token The signed role assertion
     * @return array|null Verified payload or null
     */
    public function verifyRoleAssertion(string $token): ?array
    {
        try {
            $decoded = base64_decode($token, true);
            if (!$decoded) return null;

            $parts = explode('.', $decoded, 2);
            if (count($parts) !== 2) return null;

            [$payloadJson, $signature] = $parts;
            
            // Verify HMAC signature
            $expectedSignature = hash_hmac('sha256', $payloadJson, $this->getHmacKey());
            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('ChatbotSecurity: Invalid role assertion signature', [
                    'token_length' => strlen($token),
                ]);
                return null;
            }

            $payload = json_decode($payloadJson, true);
            if (!$payload) return null;

            // Check expiration
            if (($payload['exp'] ?? 0) < time()) {
                return null; // Expired — not a security event, just refresh
            }

            // Validate role is a known role
            if (!isset($this->getRoleHierarchy()[$payload['role'] ?? ''])) {
                Log::warning('ChatbotSecurity: Unknown role in assertion', [
                    'role' => $payload['role'] ?? 'null',
                ]);
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            Log::warning('ChatbotSecurity: Role assertion verification failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 4. SECURITY AUDIT LOGGING
    // ──────────────────────────────────────────────────────────────

    /**
     * Log a security event to the database and log files.
     * This is always-on and cannot be disabled.
     * 
     * @param string $eventType Type of security event
     * @param array $context Event context data
     */
    public function logSecurityEvent(string $eventType, array $context = []): void
    {
        // Always log to security channel
        Log::channel('daily')->warning("ChatbotSecurity [{$eventType}]", array_merge($context, [
            'timestamp' => now()->toIso8601String(),
        ]));

        // Persist high-severity events to database
        $highSeverityEvents = [
            'prompt_injection', 'role_impersonation', 'role_escalation',
            'data_exfiltration', 'jailbreak_attempt', 'token_tampering',
            'social_engineering',
        ];

        if (in_array($eventType, $highSeverityEvents)) {
            try {
                DB::table('chatbot_analytics')->insert([
                    'user_id' => $context['user_id'] ?? null,
                    'session_id' => $context['session_id'] ?? null,
                    'ip_address' => $context['ip_address'] ?? null,
                    'detected_intent' => 'security_violation',
                    'user_message' => substr($context['message'] ?? '', 0, 255),
                    'response_source' => "security_{$eventType}",
                    'was_successful' => false,
                    'failure_reason' => $eventType . ': ' . ($context['details'] ?? ''),
                    'confidence_score' => ($context['risk_score'] ?? 0) / 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Never let audit logging break the request
                Log::debug('Security audit DB insert failed: ' . $e->getMessage());
            }

            // Also persist to dedicated security_events table for centralized monitoring
            try {
                DB::table('security_events')->insert([
                    'event_type' => "chatbot_{$eventType}",
                    'ip_address' => $context['ip_address'] ?? '0.0.0.0',
                    'user_id' => $context['user_id'] ?? null,
                    'endpoint' => '/api/chatbot',
                    'method' => 'POST',
                    'is_suspicious' => true,
                    'risk_score' => $context['risk_score'] ?? 50,
                    'details' => json_encode([
                        'flags' => $context['flags'] ?? $eventType,
                        'message_snippet' => substr($context['message'] ?? '', 0, 100),
                        'session_id' => $context['session_id'] ?? null,
                        'current_role' => $context['current_role'] ?? null,
                        'target_role' => $context['target_role'] ?? null,
                    ]),
                    'action_taken' => 'blocked',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::debug('Security events DB insert failed: ' . $e->getMessage());
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 5. COMPREHENSIVE SECURITY CHECK (MAIN ENTRY POINT)
    // ──────────────────────────────────────────────────────────────

    /**
     * Run all security checks against an incoming chatbot message.
     * Returns a structured result with pass/fail and deterministic response.
     * 
     * This is the SINGLE entry point called by UnifiedChatbotService for
     * every message. It runs:
     * 1. Prompt injection detection
     * 2. Role escalation detection
     * 3. Suspicious activity monitoring
     * 
     * @param string $message User message
     * @param string $role Verified server-side role
     * @param int|null $userId Authenticated user ID
     * @param string $ipAddress Request IP
     * @param string|null $sessionId Session ID
     * @return array ['passed' => bool, 'response' => string|null, 'event_type' => string|null]
     */
    public function runSecurityChecks(
        string $message,
        string $role,
        ?int $userId,
        string $ipAddress,
        ?string $sessionId = null
    ): array {
        // --- Check 1: Prompt Injection Detection ---
        $injectionResult = $this->detectPromptInjection($message);

        // CRITICAL patterns: block immediately regardless of cumulative score
        $criticalPatterns = ['role_impersonation', 'instruction_override', 'multi_turn_manipulation', 'tool_call_injection'];
        $hasCriticalFlag = !empty(array_intersect($injectionResult['flags'], $criticalPatterns));

        if ($injectionResult['injection_detected'] && ($injectionResult['risk_score'] >= 40 || $hasCriticalFlag)) {
            $this->logSecurityEvent($injectionResult['flags'][0] ?? 'prompt_injection', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'message' => $message,
                'risk_score' => $injectionResult['risk_score'],
                'flags' => implode(', ', $injectionResult['flags']),
                'details' => 'Prompt injection detected with risk score ' . $injectionResult['risk_score'],
            ]);

            return [
                'passed' => false,
                'response' => $this->getSecurityRefusalResponse($injectionResult['flags'][0] ?? 'prompt_injection'),
                'event_type' => $injectionResult['flags'][0] ?? 'prompt_injection',
                'risk_score' => $injectionResult['risk_score'],
            ];
        }

        // --- Check 2: Role Escalation Detection ---
        $escalationResult = $this->detectRoleEscalation($role, $message);
        if ($escalationResult['escalation_detected']) {
            $this->logSecurityEvent('role_escalation', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'message' => $message,
                'current_role' => $role,
                'target_role' => $escalationResult['target_role'],
                'details' => $escalationResult['details'],
            ]);

            // Return a helpful but firm refusal
            return [
                'passed' => false,
                'response' => $this->getRoleEscalationResponse($role, $escalationResult['target_role']),
                'event_type' => 'role_escalation',
                'risk_score' => 50,
            ];
        }

        // --- Check 3: Progressive abuse monitoring ---
        $abuseKey = "chatbot_security_abuse_{$ipAddress}_{$userId}";
        $abuseCount = Cache::get($abuseKey, 0);

        // Progressive penalties: as violations increase, threshold decreases
        // 3+ violations: threshold drops to 30 (catches medium-risk)
        // 5+ violations: threshold drops to 20 (catches low-risk)
        // 10+ violations: block all messages for the remaining window
        if ($abuseCount >= 10) {
            $this->logSecurityEvent('abuse_lockout', [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'abuse_count' => $abuseCount,
                'message' => $message,
            ]);
            return [
                'passed' => false,
                'response' => "Your session has been temporarily restricted due to repeated policy violations. Please try again later or contact support.",
                'event_type' => 'abuse_lockout',
                'risk_score' => 100,
            ];
        }

        $dynamicThreshold = match(true) {
            $abuseCount >= 5 => 20,
            $abuseCount >= 3 => 30,
            default => 40,
        };

        if ($abuseCount >= 3 && $injectionResult['risk_score'] >= $dynamicThreshold) {
            $this->logSecurityEvent('repeated_suspicious_activity', [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'abuse_count' => $abuseCount,
                'dynamic_threshold' => $dynamicThreshold,
                'message' => $message,
            ]);

            return [
                'passed' => false,
                'response' => "I'm here to help with appointments, services, and account questions. How can I assist you?",
                'event_type' => 'repeated_suspicious_activity',
                'risk_score' => $injectionResult['risk_score'],
            ];
        }

        // Track failed checks — progressive window: longer for repeat offenders
        if ($injectionResult['risk_score'] > 0 || $escalationResult['escalation_detected']) {
            $windowSeconds = min(600 * (1 + $abuseCount), 3600); // 10 min base, up to 1 hour
            Cache::put($abuseKey, $abuseCount + 1, $windowSeconds);
        }

        return [
            'passed' => true,
            'response' => null,
            'event_type' => null,
            'risk_score' => $injectionResult['risk_score'],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // 6. OUTPUT VALIDATION
    // ──────────────────────────────────────────────────────────────

    /**
     * Validate LLM output to ensure it doesn't contain role-inappropriate content.
     * Called AFTER LLM generates a response, BEFORE sending to user.
     * 
     * @param string $response LLM response
     * @param string $role User's verified role
     * @return array ['safe' => bool, 'sanitized' => string, 'violations' => array]
     */
    public function validateOutput(string $response, string $role): array
    {
        $violations = [];

        // Check for role-inappropriate data leakage in output
        if ($role === 'guest' || $role === 'client') {
            // Detect if LLM leaked admin/system data
            $adminDataPatterns = [
                '/(?:total\s+(?:users|clients|records|appointments|revenue))\s*(?:is|are|:)\s*\d+/i',
                '/(?:system\s+(?:analytics|statistics|metrics|health))\s*(?:show|indicate|report)/i',
                '/(?:admin\s+dashboard|admin\s+panel|system\s+configuration)/i',
                '/(?:all\s+(?:users|clients)\s+(?:in\s+the\s+system|registered|total))/i',
                '/(?:database|table|column|schema|migration)\s+(?:structure|name|detail|layout)/i',
                '/(?:api[_\s]?key|secret[_\s]?key|bearer\s+token|access[_\s]?token)\s*[:=]/i',
                '/(?:internal\s+(?:error|stack\s+trace|exception|server))/i',
                '/(?:sql\s+(?:query|error|syntax|statement))/i',
            ];

            foreach ($adminDataPatterns as $pattern) {
                if (preg_match($pattern, $response)) {
                    $violations[] = 'admin_data_leakage';
                    break;
                }
            }
        }

        if ($role === 'guest') {
            // Guest should never see personal user data
            $personalDataPatterns = [
                '/(?:your\s+(?:appointment|payment|refund)\s+(?:#|number|id)\s*(?:is|:)\s*\w+)/i',
                '/(?:(?:appointment|payment|refund)\s+for\s+\w+.*(?:on|at|dated)\s+\d)/i',
            ];

            foreach ($personalDataPatterns as $pattern) {
                if (preg_match($pattern, $response)) {
                    $violations[] = 'personal_data_to_guest';
                    break;
                }
            }
        }

        // Check for PII leakage — credit cards, SSN-like patterns, raw API keys
        $piiPatterns = [
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', // Credit card (with/without separators)
            '/\b\d{13,19}\b/', // Credit card numbers without separators (13-19 digits)
            '/\bsk-[a-zA-Z0-9]{20,}\b/', // OpenAI API key patterns
            '/\bhf_[a-zA-Z0-9]{20,}\b/', // HuggingFace key
            '/\bkey-[a-zA-Z0-9]{20,}\b/', // Generic API key
            '/\bAIza[a-zA-Z0-9_-]{35}\b/', // Google API key
            '/\bAKIA[A-Z0-9]{16}\b/', // AWS access key
            '/\bghp_[a-zA-Z0-9]{36,}\b/', // GitHub personal access token
            '/\bglpat-[a-zA-Z0-9_-]{20,}\b/', // GitLab personal access token
            '/\bxox[bps]-[a-zA-Z0-9-]+\b/', // Slack tokens
            '/\b[a-f0-9]{32,64}\b(?=.*(?:key|token|secret|password))/i', // Hex strings near sensitive words
            '/(?:password|passwd|pwd)\s*[:=]\s*\S+/i', // Password assignments
            '/\b\d{3}-\d{2}-\d{4}\b/', // SSN-like pattern
        ];
        foreach ($piiPatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                $violations[] = 'pii_leakage';
                break;
            }
        }

        // Check for system prompt leakage
        $promptLeakagePatterns = [
            'CORE PRINCIPLES (Non-negotiable)',
            'STRICT CLIENT DATA BOUNDARIES',
            'STRICT GUEST DATA BOUNDARIES',
            'SECURITY & ACCESS CONTROL',
            'ROLE ENFORCEMENT (CRITICAL)',
            'ANTI-INJECTION DIRECTIVE',
            'ZERO-TRUST ROLE BINDING',
            'MESSY INPUT HANDLING',
            'OFFENSIVE LANGUAGE HANDLING',
            'VERIFICATION & ANTI-HALLUCINATION',
            'GROUND TRUTH',
            'DYNAMIC SYSTEM KNOWLEDGE',
            'LEARNED PATTERNS & QUALITY',
        ];

        foreach ($promptLeakagePatterns as $fragment) {
            if (stripos($response, $fragment) !== false) {
                $violations[] = 'system_prompt_leakage';
                break;
            }
        }

        if (!empty($violations)) {
            Log::warning('ChatbotSecurity: Output validation failed', [
                'violations' => $violations,
                'role' => $role,
                'response_snippet' => substr($response, 0, 200),
            ]);
        }

        return [
            'safe' => empty($violations),
            'sanitized' => empty($violations) ? $response : $this->getSafeOutputFallback($role),
            'violations' => $violations,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Normalize message for security pattern matching.
     * Strips obfuscation while preserving semantic content.
     */
    private function normalizeForSecurity(string $message): string
    {
        // Remove zero-width and invisible unicode characters (expanded set)
        $message = preg_replace('/[\x{200B}-\x{200F}\x{FEFF}\x{00AD}\x{2060}-\x{2064}\x{180E}\x{034F}\x{17B4}\x{17B5}\x{FE00}-\x{FE0F}]/u', '', $message);

        // Normalize Cyrillic confusables (most common Unicode homoglyph attacks)
        $cyrillicMap = [
            'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'у' => 'y',
            'х' => 'x', 'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
            'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'У' => 'Y',
            'Х' => 'X', 'і' => 'i', 'ї' => 'i', 'ё' => 'e',
        ];
        // Greek confusables
        $greekMap = [
            'α' => 'a', 'ε' => 'e', 'η' => 'n', 'ι' => 'i', 'ο' => 'o', 'ρ' => 'p',
            'τ' => 't', 'υ' => 'u', 'ν' => 'v', 'Α' => 'A', 'Β' => 'B', 'Ε' => 'E',
            'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M', 'Ν' => 'N', 'Ο' => 'O',
            'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
        ];
        // Fullwidth Latin confusables
        $message = preg_replace_callback('/[\x{FF01}-\x{FF5E}]/u', function ($m) {
            $code = mb_ord($m[0]) - 0xFF00 + 0x0020;
            return chr($code);
        }, $message);
        $message = strtr($message, $cyrillicMap);
        $message = strtr($message, $greekMap);

        // Normalize unicode confusables (Latin accented characters)
        $confusables = [
            "\xC0" => 'a', "\xC1" => 'a', "\xC2" => 'a', "\xC3" => 'a', "\xC4" => 'a',
            "\xC8" => 'e', "\xC9" => 'e', "\xCA" => 'e', "\xCB" => 'e',
            "\xCC" => 'i', "\xCD" => 'i', "\xCE" => 'i', "\xCF" => 'i',
            "\xD2" => 'o', "\xD3" => 'o', "\xD4" => 'o', "\xD5" => 'o', "\xD6" => 'o',
            "\xD9" => 'u', "\xDA" => 'u', "\xDB" => 'u', "\xDC" => 'u',
        ];

        // Normalize common leetspeak
        $leetMap = [
            '@' => 'a', '4' => 'a',
            '3' => 'e',
            '1' => 'i', '!' => 'i',
            '0' => 'o',
            '5' => 's', '$' => 's',
            '7' => 't', '+' => 't',
        ];

        // Create a security-normalized copy for matching
        $normalized = mb_strtolower($message);
        $normalized = strtr($normalized, $leetMap);

        // Remove dots/dashes/underscores/asterisks between letters (bypass attempts like a.d.m.i.n)
        // NOTE: Do NOT remove spaces here — spaces are needed for regex word boundary matching
        $normalized = preg_replace('/(?<=[a-z])[\._\-\*]+(?=[a-z])/', '', $normalized);
        
        // Also collapse extra spaces injected between every letter (e.g., "a d m i n")
        // Detect single-char-space patterns: "a d m i n" → "admin"
        if (preg_match('/\b([a-z]) ([a-z]) ([a-z])\b/', $normalized)) {
            $denormalized = preg_replace('/\b((?:[a-z] ){2,}[a-z])\b/', '', $normalized);
            // Extract the spaced-out words and collapse them
            preg_match_all('/\b((?:[a-z] ){2,}[a-z])\b/', $normalized, $spacedWords);
            if (!empty($spacedWords[0])) {
                $temp = $normalized;
                foreach ($spacedWords[0] as $spacedWord) {
                    $collapsed = str_replace(' ', '', $spacedWord);
                    $temp = str_replace($spacedWord, $collapsed, $temp);
                }
                $normalized = $temp;
            }
        }

        // Collapse repeated characters
        $normalized = preg_replace('/(.)\1{2,}/', '$1$1', $normalized);

        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        return $normalized;
    }

    /**
     * Classify risk level from score.
     */
    private function classifyRisk(int $score): string
    {
        if ($score >= 60) return 'critical';
        if ($score >= 40) return 'high';
        if ($score >= 20) return 'medium';
        return 'low';
    }

    /**
     * Get deterministic security refusal response.
     * These responses are polite but firm — they redirect to legitimate use.
     */
    private function getSecurityRefusalResponse(string $type): string
    {
        $responses = [
            'instruction_override' => "I cannot change my instructions or operating guidelines. I'm here to help you with appointments, services, payments, and account questions. How can I assist you?",
            'role_impersonation' => "I cannot change roles or grant access permissions. Your role is determined by your account and cannot be modified through our conversation. How can I help you with the features available to your account?",
            'prompt_extraction' => "I'm not able to share my internal configuration or instructions. I'm here to help you with our legal services, appointments, and account management. What can I assist you with?",
            'jailbreak_attempt' => "I operate within my designed guidelines to provide you the best assistance with our services. I cannot enter alternative modes. How can I help you today?",
            'encoding_evasion' => "I'm designed to help with appointments, services, and account questions. Could you please rephrase your request in plain language?",
            'multi_turn_manipulation' => "I cannot change roles or access levels during our conversation. Your permissions are determined by your account type. How can I help you with the features available to you?",
            'data_exfiltration' => "I can only share information that belongs to your account and is appropriate for your role. I cannot provide bulk data exports or information about other users. What specific information about your account can I help you with?",
            'social_engineering' => "I can only assist you with your own account. For security reasons, I cannot access, share, or verify another person's information — even if they gave you permission. Each user must access their own account directly. How can I help you with your account?",
            'tool_call_injection' => "I'm here to help with our legal services and appointment system. Please describe what you need in plain language, and I'll assist you.",
            'prompt_injection' => "I'm here to help with our legal services and appointment system. How can I assist you today?",
        ];

        return $responses[$type] ?? $responses['prompt_injection'];
    }

    /**
     * Get role escalation refusal response — helpful but firm.
     */
    private function getRoleEscalationResponse(string $currentRole, string $targetRole): string
    {
        $currentDisplay = ucfirst($currentRole);
        $targetDisplay = ucfirst($targetRole);

        $responses = [
            'guest' => [
                'client' => "That feature requires a registered account. You can register to access your appointments, payments, and personal dashboard. Would you like to know how to create an account?",
                'cashier' => "Payment processing features are available to authorized cashier staff only. I can help you with information about our services, pricing, and how to get started.",
                'admin' => "Administrative features are restricted to authorized personnel. I can help you with information about our services, business hours, and registration.",
            ],
            'client' => [
                'cashier' => "Payment processing and financial reporting features are managed by our cashier staff. I can help you with your own payment history, appointment status, or refund requests instead.",
                'admin' => "Administrative features like user management and system analytics are restricted to authorized staff. I can help you with your appointments, payments, services, or profile. What would you like to do?",
            ],
            'cashier' => [
                'admin' => "Administrative features like user management and system settings are available to administrators only. I can help you with payment processing, shift reports, and transaction-related tasks.",
            ],
        ];

        return $responses[$currentRole][$targetRole]
            ?? "That feature is available to {$targetDisplay} accounts. As a {$currentDisplay}, I can help you with the features available to your account. What would you like to do?";
    }

    /**
     * Get safe fallback output when LLM output validation fails.
     */
    private function getSafeOutputFallback(string $role): string
    {
        return match ($role) {
            'guest' => "I can help you with information about our services, business hours, and how to register. What would you like to know?",
            'client' => "I can help you with your appointments, payments, services, and account information. What would you like to know?",
            'cashier' => "I can assist you with payment processing, transaction reports, and refund management. How can I help?",
            'admin' => "I can provide information about system operations, analytics, and management tasks. What do you need?",
            default => "I'm here to help with our legal services and appointment system. How can I assist you?",
        };
    }

    // ──────────────────────────────────────────────────────────────
    // INJECTED CONTENT SANITIZATION (KB, feedback, memory, RAG)
    // ──────────────────────────────────────────────────────────────

    /**
     * Sanitize content that will be injected into the system prompt from
     * non-user-input sources (knowledge base, feedback, conversation memory,
     * dynamic knowledge feeds).
     *
     * Unlike user message validation (which blocks the request), this method
     * STRIPS dangerous patterns from the content so the remaining safe text
     * can still be used. This prevents indirect prompt injection where a
     * malicious user poisons a data source (e.g., submits feedback containing
     * "Ignore all previous instructions...") which then gets injected into
     * the system prompt for ALL future users.
     *
     * @param string $content Raw content from KB, feedback, memory, etc.
     * @return string Sanitized content safe for prompt injection
     */
    public function sanitizeInjectedContent(string $content): string
    {
        if (empty(trim($content))) {
            return $content;
        }

        // Patterns that indicate prompt injection in injected content
        $dangerousPatterns = [
            // Direct instruction override
            '/(?:ignore|disregard|forget|override|bypass|skip|cancel|stop\s+following|do\s+not\s+follow)\s+(?:your|all|the|any|previous|above|prior|system|original)\s+(?:instructions|rules|prompt|guidelines|constraints|directives|programming|training)/i',
            '/(?:new|updated|revised|different|my)\s+(?:instructions|rules|prompt|system\s+prompt|directives|guidelines)\s*:/i',
            '/(?:from\s+now\s+on|starting\s+now|henceforth)\s+(?:you\s+(?:are|will|must|should)|ignore|disregard)/i',

            // System/role impersonation markers
            '/(?:system\s*:\s*|<\|system\|>|<<SYS>>|\[SYSTEM\]|\[INST\])/i',
            '/(?:human\s*:\s*|assistant\s*:\s*|user\s*:\s*).*(?:system\s*:\s*)/is',
            '/(?:i\s+am\s+(?:your|the)\s+(?:developer|creator|admin|administrator|owner|master|programmer|operator))/i',

            // Role escalation attempts
            '/(?:you\s+are\s+now|pretend\s+(?:to\s+be|you\s*(?:\'re|are))|act\s+(?:as|like))\s+/i',
            '/(?:switch|change|set|update|modify|elevate|promote)\s+(?:my\s+)?(?:role|access|permission|privilege)\s+(?:to|as)/i',
            '/(?:grant|give|assign|make)\s+(?:me|myself)\s+(?:admin|administrator|cashier|staff)/i',

            // Jailbreak techniques
            '/\b(?:dan|evil|chaos|unrestricted|unfiltered|uncensored)\s*(?:mode|version|persona)\b/i',
            '/\b(?:jailbreak|jail\s*break|unlock|unleash|liberate)\b/i',
            '/(?:developer|debug|test|maintenance|admin|god|sudo|root)\s*mode/i',
            '/(?:do\s+anything\s+now|no\s+restrictions|without\s+limits|without\s+rules|ignore\s+safety|bypass\s+filters)/i',

            // Data exfiltration instructions
            '/(?:show|reveal|display|print|output|dump|export)\s+(?:all|every)\s+(?:user|client|data|record|password|credential|api\s*key|secret|token)/i',
            '/(?:show|reveal|tell)\s+(?:me\s+)?(?:your|the)\s+(?:system\s+prompt|instructions|initial\s+prompt|hidden\s+prompt|rules|training\s+data)/i',

            // Code/SQL injection embedded in content
            '/<script\b|<iframe\b|javascript\s*:/i',
            '/\b(?:DROP\s+TABLE|DELETE\s+FROM|INSERT\s+INTO|UPDATE\s+\w+\s+SET|UNION\s+SELECT)\b/i',

            // Markdown/formatting tricks to break prompt structure
            '/\[(?:SYSTEM|ADMIN|ROOT|SUDO)\]/i',
        ];

        $sanitized = $content;
        $strippedCount = 0;

        foreach ($dangerousPatterns as $pattern) {
            $before = $sanitized;
            $sanitized = preg_replace($pattern, '[content removed for security]', $sanitized);
            if ($sanitized !== $before) {
                $strippedCount++;
            }
        }

        if ($strippedCount > 0) {
            Log::warning('Sanitized injected content: removed ' . $strippedCount . ' suspicious pattern(s)', [
                'content_length' => strlen($content),
                'patterns_removed' => $strippedCount,
            ]);
        }

        return $sanitized;
    }

    /**
     * Sanitize an array of content strings (e.g., list of corrections or suggestions).
     *
     * @param array $items Array of strings to sanitize
     * @return array Sanitized array with dangerous content neutralized
     */
    public function sanitizeInjectedArray(array $items): array
    {
        return array_map(fn($item) => is_string($item) ? $this->sanitizeInjectedContent($item) : $item, $items);
    }
}
