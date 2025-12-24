<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\Payment;

/**
 * AdvancedIntelligenceService - Core Intelligence Engine
 * 
 * DESIGN PRINCIPLES:
 * - NO HARDCODING: All responses are dynamically generated based on context
 * - CLARIFICATION-FIRST: Always ask clarifying questions for ambiguous requests
 * - STEP-BY-STEP: Break down complex tasks into clear, actionable steps
 * - MULTILINGUAL: Native support for English, Tagalog, and Taglish
 * - TYPO-TOLERANT: Gracefully handle messy input, typos, and informal text
 * - CONTEXT-AWARE: Leverage conversation history and user data
 * - REAL-TIME DATA: Always fetch fresh data, never assume
 * 
 * Features:
 * - Intelligent ambiguity detection
 * - Dynamic clarifying question generation
 * - Step-by-step response structuring
 * - Multi-option response building
 * - Consequence/warning analysis
 * - Context persistence across conversations
 */
class AdvancedIntelligenceService
{
    private const CONTEXT_CACHE_PREFIX = 'adv_intel_context_';
    private const CONTEXT_TTL = 3600; // 1 hour

    /**
     * Ambiguity indicators - patterns that suggest unclear intent
     */
    private array $ambiguityIndicators = [
        // Vague requests
        'general_vague' => [
            'patterns' => [
                '/^(help|tulong|pano|paano|how)$/i',
                '/^(i want|gusto ko|kailangan ko)$/i',
                '/^(tell me|sabihin mo|ano)$/i',
                '/^(what|ano|which|alin)$/i',
                '/^(can you|pwede ba|pede ba)$/i',
            ],
            'requires_clarification' => true,
            'clarification_type' => 'specify_action',
        ],
        // Pronoun-heavy without context
        'pronoun_heavy' => [
            'patterns' => [
                '/\b(it|this|that|these|those|ito|iyan|iyon)\b/i',
                '/\b(them|they|sila|siya)\b/i',
            ],
            'requires_context_check' => true,
            'clarification_type' => 'specify_subject',
        ],
        // Multiple possible intents
        'multi_intent' => [
            'patterns' => [
                '/\b(and|or|also|at|o|din|rin)\b.*\b(and|or|also|at|o|din|rin)\b/i',
            ],
            'requires_clarification' => true,
            'clarification_type' => 'prioritize_request',
        ],
        // Time ambiguity
        'time_ambiguous' => [
            'patterns' => [
                '/\b(soon|later|sometime|mamaya|bukas|later|next)\b/i',
            ],
            'requires_clarification' => true,
            'clarification_type' => 'specify_time',
        ],
    ];

    /**
     * Question type indicators for dynamic clarification generation
     */
    private array $questionTypes = [
        'appointment' => [
            'what' => ['service', 'date', 'time', 'purpose'],
            'clarifying_questions' => [
                'en' => [
                    'service' => 'Which service are you interested in?',
                    'date' => 'What date would you prefer for your appointment?',
                    'time' => 'What time works best for you?',
                    'which_appointment' => 'Which appointment are you referring to? Please provide the appointment ID or date.',
                    'status_specific' => 'Are you asking about a specific appointment or all your appointments?',
                ],
                'tl' => [
                    'service' => 'Anong serbisyo ang gusto mo?',
                    'date' => 'Anong petsa ang gusto mo para sa appointment mo?',
                    'time' => 'Anong oras ang pinaka-convenient para sa iyo?',
                    'which_appointment' => 'Aling appointment ang tinutukoy mo? Pakibigay ang appointment ID o petsa.',
                    'status_specific' => 'Tungkol ba ito sa isang partikular na appointment o lahat ng appointments mo?',
                ],
            ],
        ],
        'payment' => [
            'what' => ['amount', 'method', 'appointment', 'status'],
            'clarifying_questions' => [
                'en' => [
                    'which_payment' => 'Which payment are you inquiring about?',
                    'method' => 'What payment method would you prefer?',
                    'amount' => 'Are you asking about a specific amount or total balance?',
                ],
                'tl' => [
                    'which_payment' => 'Aling bayad ang tinatanong mo?',
                    'method' => 'Anong paraan ng pagbabayad ang gusto mo?',
                    'amount' => 'Tungkol ba ito sa isang partikular na halaga o kabuuang balanse?',
                ],
            ],
        ],
        'refund' => [
            'what' => ['reason', 'appointment', 'amount', 'status'],
            'clarifying_questions' => [
                'en' => [
                    'reason' => 'Could you tell me the reason for the refund request?',
                    'which_appointment' => 'Which appointment would you like to request a refund for?',
                    'status_check' => 'Are you checking the status of an existing refund request?',
                ],
                'tl' => [
                    'reason' => 'Pwede mo bang sabihin ang dahilan ng refund request?',
                    'which_appointment' => 'Aling appointment ang gusto mong i-refund?',
                    'status_check' => 'Tine-check mo ba ang status ng existing refund request?',
                ],
            ],
        ],
        'general' => [
            'clarifying_questions' => [
                'en' => [
                    'specify' => 'Could you please provide more details about what you need help with?',
                    'options' => 'I can help you with appointments, payments, refunds, services, and general inquiries. Which would you like to know more about?',
                    'context' => 'To assist you better, could you tell me more about your situation?',
                ],
                'tl' => [
                    'specify' => 'Pwede mo bang ibigay ang mas detalyadong impormasyon tungkol sa kailangan mong tulong?',
                    'options' => 'Pwede kitang tulungan sa appointments, payments, refunds, services, at general inquiries. Alin ang gusto mong malaman?',
                    'context' => 'Para mas matulungan kita, pwede mo bang sabihin pa ang tungkol sa sitwasyon mo?',
                ],
            ],
        ],
    ];

    /**
     * Analyze user input for ambiguity and determine if clarification is needed
     * 
     * @param string $message User message
     * @param array $context Conversation context
     * @return array Analysis result with clarification needs
     */
    public function analyzeForAmbiguity(string $message, array $context = []): array
    {
        $result = [
            'is_ambiguous' => false,
            'ambiguity_type' => null,
            'confidence' => 1.0,
            'needs_clarification' => false,
            'clarification_type' => null,
            'suggested_questions' => [],
            'detected_topics' => [],
            'missing_information' => [],
        ];

        $messageLower = strtolower(trim($message));
        $wordCount = str_word_count($messageLower);

        // Very short messages are often ambiguous
        if ($wordCount <= 2) {
            $result['is_ambiguous'] = true;
            $result['ambiguity_type'] = 'too_brief';
            $result['confidence'] = 0.4;
            $result['needs_clarification'] = true;
            $result['clarification_type'] = 'specify_action';
        }

        // Check against ambiguity patterns
        foreach ($this->ambiguityIndicators as $type => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $messageLower)) {
                    $result['is_ambiguous'] = true;
                    $result['ambiguity_type'] = $type;
                    
                    if ($config['requires_clarification'] ?? false) {
                        $result['needs_clarification'] = true;
                        $result['clarification_type'] = $config['clarification_type'];
                    }
                    
                    if ($config['requires_context_check'] ?? false) {
                        // Check if we have context to resolve pronouns
                        if (empty($context['last_topic']) && empty($context['last_entities'])) {
                            $result['needs_clarification'] = true;
                            $result['clarification_type'] = $config['clarification_type'];
                        }
                    }
                }
            }
        }

        // Detect topics mentioned
        $result['detected_topics'] = $this->detectTopics($messageLower);

        // Determine missing information based on detected intent
        $result['missing_information'] = $this->determineMissingInfo($messageLower, $context);

        // Generate suggested clarifying questions
        if ($result['needs_clarification']) {
            $language = $context['language'] ?? 'en';
            $result['suggested_questions'] = $this->generateClarifyingQuestions(
                $result['clarification_type'],
                $result['detected_topics'],
                $language
            );
        }

        return $result;
    }

    /**
     * Detect topics mentioned in the message
     */
    private function detectTopics(string $message): array
    {
        $topics = [];

        $topicPatterns = [
            'appointment' => '/\b(appointment|appt|apt|book|schedule|sched|resched|cancel|booking|reserve)\b/i',
            'payment' => '/\b(pay|payment|bayad|singil|fee|price|cost|amount|balance|receipt)\b/i',
            'refund' => '/\b(refund|return|ibalik|money back|reimburse)\b/i',
            'service' => '/\b(service|serbisyo|notary|legal|document|consultation)\b/i',
            'status' => '/\b(status|update|pending|approved|completed|cancelled|track)\b/i',
            'time' => '/\b(time|date|schedule|when|kelan|oras|petsa|bukas|today|tomorrow)\b/i',
            'help' => '/\b(help|tulong|assist|how|paano|pano|guide)\b/i',
            'account' => '/\b(account|profile|info|details|password|email)\b/i',
        ];

        foreach ($topicPatterns as $topic => $pattern) {
            if (preg_match($pattern, $message)) {
                $topics[] = $topic;
            }
        }

        return $topics;
    }

    /**
     * Determine what information is missing for a complete request
     */
    private function determineMissingInfo(string $message, array $context): array
    {
        $missing = [];
        $topics = $this->detectTopics($message);

        // For appointment-related queries
        if (in_array('appointment', $topics)) {
            // Check if booking new appointment
            if (preg_match('/\b(book|schedule|reserve|new|create)\b/i', $message)) {
                if (!preg_match('/\b(notary|legal|document|consultation|service)\b/i', $message)) {
                    $missing[] = 'service_type';
                }
                if (!preg_match('/\d{1,2}[-\/]\d{1,2}|\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday|lunes|martes|miyerkules|huwebes|biyernes|sabado|linggo)\b/i', $message)) {
                    $missing[] = 'preferred_date';
                }
            }
            
            // Check if referencing specific appointment
            if (preg_match('/\b(my|this|the|that|aking|itong)\s*(appointment|booking)/i', $message)) {
                if (!preg_match('/\b(id|#|number|\d{3,})\b/i', $message) && empty($context['last_appointment_id'])) {
                    $missing[] = 'appointment_identifier';
                }
            }
        }

        // For payment-related queries
        if (in_array('payment', $topics)) {
            if (!preg_match('/\b(id|#|appointment|\d{3,})\b/i', $message) && empty($context['last_payment_context'])) {
                $missing[] = 'payment_reference';
            }
        }

        // For refund-related queries
        if (in_array('refund', $topics)) {
            if (preg_match('/\b(request|want|need|gusto)\b/i', $message)) {
                if (!preg_match('/\b(id|#|appointment|\d{3,})\b/i', $message)) {
                    $missing[] = 'appointment_for_refund';
                }
                if (!preg_match('/\b(reason|because|kasi|dahil)\b/i', $message)) {
                    $missing[] = 'refund_reason';
                }
            }
        }

        return $missing;
    }

    /**
     * Generate appropriate clarifying questions based on context
     */
    private function generateClarifyingQuestions(string $clarificationType, array $topics, string $language = 'en'): array
    {
        $questions = [];
        $lang = $language === 'tl' || $language === 'filipino' ? 'tl' : 'en';

        // Generate dynamic clarifying questions using live data where possible
        foreach ($topics as $topic) {
            if ($topic === 'service' || $topic === 'appointment') {
                // Pull top services dynamically
                try {
                    $services = Service::where('is_active', true)->limit(5)->pluck('name')->toArray();
                } catch (\Exception $e) {
                    Log::debug('Failed to fetch services for clarifying questions: ' . $e->getMessage());
                    $services = [];
                }

                if (!empty($services)) {
                    $serviceList = implode(', ', $services);
                    if ($lang === 'tl') {
                        $questions[] = "Alin sa mga serbisyong ito ang kailangan mo: $serviceList?";
                        $questions[] = 'Anong petsa ang gusto mong i-schedule?';
                    } else {
                        $questions[] = "Which of these services do you need: $serviceList?";
                        $questions[] = 'What date would you like to schedule?';
                    }
                } else {
                    if ($lang === 'tl') {
                        $questions[] = 'Alin ang gusto mong serbisyo?';
                        $questions[] = 'Anong petsa ang inuuna mo?';
                    } else {
                        $questions[] = 'Which service would you like?';
                        $questions[] = 'What date would you prefer?';
                    }
                }
            }

            if ($topic === 'payment') {
                if ($lang === 'tl') {
                    $questions[] = 'Aling bayad ang tinutukoy mo? Mayroon ka bang payment ID o appointment number?';
                    $questions[] = 'Nais mo bang malaman ang balanse o detalye ng isang partikular na bayad?';
                } else {
                    $questions[] = 'Which payment are you asking about? Do you have a payment ID or appointment number?';
                    $questions[] = 'Are you asking about a balance or a specific payment detail?';
                }
            }

            if ($topic === 'refund') {
                if ($lang === 'tl') {
                    $questions[] = 'Anong appointment ang gusto mong i-refund?';
                    $questions[] = 'Ano ang dahilan ng refund request?';
                } else {
                    $questions[] = 'Which appointment do you want a refund for?';
                    $questions[] = 'What is the reason for the refund request?';
                }
            }
        }

        // If nothing generated, fall back to a generic dynamic prompt
        if (empty($questions)) {
            if ($lang === 'tl') {
                $questions[] = 'Pwede mo bang ilarawan nang mas detalyado ang kailangan mo?';
                $questions[] = 'Gusto mo bang tingnan ang mga appointment o serbisyo mo?';
            } else {
                $questions[] = 'Could you provide more details about what you need?';
                $questions[] = 'Would you like to view your appointments or available services?';
            }
        }

        // Return up to 3 questions
        return array_slice(array_unique($questions), 0, 3);
    }

    /**
     * Build a structured, step-by-step response
     * 
     * @param string $topic Main topic/intent
     * @param array $data Dynamic data from system
     * @param array $options Available options/paths
     * @param string $language Response language
     * @return array Structured response
     */
    public function buildStructuredResponse(
        string $topic,
        array $data,
        array $options = [],
        string $language = 'en'
    ): array {
        $response = [
            'type' => 'structured',
            'topic' => $topic,
            'language' => $language,
            'sections' => [],
        ];

        // Build introduction based on topic
        $response['sections'][] = [
            'type' => 'introduction',
            'content' => $this->buildIntroduction($topic, $data, $language),
        ];

        // Build options/alternatives if available
        if (!empty($options)) {
            $response['sections'][] = [
                'type' => 'options',
                'title' => $language === 'tl' ? 'Mga Pagpipilian' : 'Available Options',
                'items' => $this->formatOptions($options, $language),
            ];
        }

        // Build step-by-step instructions if applicable
        if ($this->requiresSteps($topic)) {
            $response['sections'][] = [
                'type' => 'steps',
                'title' => $language === 'tl' ? 'Mga Hakbang' : 'Steps to Follow',
                'items' => $this->buildSteps($topic, $data, $language),
            ];
        }

        // Add warnings/recommendations if applicable
        $warnings = $this->buildWarnings($topic, $data, $language);
        if (!empty($warnings)) {
            $response['sections'][] = [
                'type' => 'warnings',
                'title' => $language === 'tl' ? 'Mga Paalala' : 'Important Notes',
                'items' => $warnings,
            ];
        }

        // Add follow-up prompt
        $response['sections'][] = [
            'type' => 'follow_up',
            'content' => $this->buildFollowUpPrompt($topic, $language),
        ];

        return $response;
    }

    /**
     * Build dynamic introduction based on topic and data
     */
    private function buildIntroduction(string $topic, array $data, string $language): string
    {
        $lang = $language === 'tl' || $language === 'filipino' ? 'tl' : 'en';

        // Build introduction dynamically from available data
        try {
            if ($topic === 'appointment' || $topic === 'appointments') {
                if (!empty($data['appointment_id'])) {
                    $appt = Appointment::find($data['appointment_id']);
                    if ($appt) {
                        $serviceName = $appt->service->name ?? ($appt->service_name ?? null);
                        $when = $appt->scheduled_at ?? $appt->date ?? null;
                        if ($lang === 'tl') {
                            return 'Nakita ko ang appointment: ' . ($serviceName ? $serviceName . ' - ' : '') . ($when ? $when : '') . ' (Status: ' . ($appt->status ?? 'unknown') . ')';
                        }
                        return 'Found appointment: ' . ($serviceName ? $serviceName . ' - ' : '') . ($when ? $when : '') . ' (Status: ' . ($appt->status ?? 'unknown') . ')';
                    }
                    // Not found
                    if ($lang === 'tl') {
                        return 'Hindi ko makita ang appointment na iyon. Pakibigay ang tamang appointment ID o karagdagang detalye.';
                    }
                    return "I couldn't find that appointment. Please provide a valid appointment ID or more details.";
                }

                // Summary of recent appointments if provided
                if (!empty($data['appointments_count'])) {
                    $count = (int) $data['appointments_count'];
                    if ($lang === 'tl') {
                        return "May nakitang $count appointment na tumutugma sa iyong query.";
                    }
                    return "I found $count appointments matching your query.";
                }

                // Default appointments intro
                if ($lang === 'tl') {
                    return 'Narito ang summary ng iyong mga appointment.';
                }
                return "Here's an overview of your appointments.";
            }

            if ($topic === 'services') {
                $services = Service::where('is_active', true)->get();
                if ($services->count() > 0) {
                    $names = $services->pluck('name')->toArray();
                    $list = implode(', ', array_slice($names, 0, 8));
                    if ($lang === 'tl') {
                        return 'Narito ang mga serbisyong available: ' . $list;
                    }
                    return 'Available services: ' . $list;
                }
                if ($lang === 'tl') {
                    return 'Walang nakitang aktibong serbisyo sa system ngayon.';
                }
                return 'No active services were found in the system right now.';
            }

            if ($topic === 'payment' || $topic === 'payments') {
                if (!empty($data['user_id'])) {
                    $userId = $data['user_id'];
                    $pending = Payment::where('user_id', $userId)->where('status', 'pending')->sum('amount');
                    if ($lang === 'tl') {
                        return 'Ang iyong nakabinbing balanse ay: ' . number_format($pending, 2);
                    }
                    return 'Your outstanding balance is: ' . number_format($pending, 2);
                }
                if ($lang === 'tl') {
                    return 'Narito ang impormasyon tungkol sa iyong mga bayad.';
                }
                return "Here's information about payments related to your account.";
            }
        } catch (\Exception $e) {
            Log::debug('buildIntroduction dynamic fetch error: ' . $e->getMessage());
        }

        // Generic fallback dynamic intro
        if ($lang === 'tl') {
            return 'Nandito ako para tulungan ka. Sabihin mo lang kung anong kailangan mo.';
        }
        return "I'm here to help — tell me what you need and I'll fetch the latest information.";
    }

    /**
     * Format options for display
     */
    private function formatOptions(array $options, string $language): array
    {
        $formatted = [];
        $lang = $language === 'tl' || $language === 'filipino' ? 'tl' : 'en';

        foreach ($options as $index => $option) {
            $formatted[] = [
                'number' => $index + 1,
                'label' => is_array($option) ? ($option['label'] ?? $option['name'] ?? "Option " . ($index + 1)) : $option,
                'description' => is_array($option) ? ($option['description'] ?? '') : '',
                'action' => is_array($option) ? ($option['action'] ?? null) : null,
            ];
        }

        return $formatted;
    }

    /**
     * Check if topic requires step-by-step instructions
     */
    private function requiresSteps(string $topic): bool
    {
        $stepTopics = ['booking', 'book_appointment', 'cancel_appointment', 
                       'reschedule', 'request_refund', 'process_payment'];
        return in_array($topic, $stepTopics);
    }

    /**
     * Build step-by-step instructions dynamically
     */
    private function buildSteps(string $topic, array $data, string $language): array
    {
        $steps = [];
        $lang = $language === 'tl' || $language === 'filipino' ? 'tl' : 'en';

        try {
            if ($topic === 'booking' || $topic === 'book_appointment') {
                // Steps reference live endpoints and required fields
                $instructions = [];
                if ($lang === 'tl') {
                    $instructions[] = 'Buksan ang Appointments sa iyong dashboard o gamitin ang /api/appointments endpoint.';
                    $instructions[] = 'Mag-submit ng POST request na may `service_id`, `date`, at `time`.';
                    $instructions[] = 'Suriin ang available slots na ibibigay ng system bago mag-confirm.';
                    $instructions[] = 'I-confirm ang booking at i-check ang status sa iyong appointments list.';
                } else {
                    $instructions[] = 'Open the Appointments section in your dashboard or call the /api/appointments endpoint.';
                    $instructions[] = 'Submit a POST with `service_id`, `date`, and `time` to create a booking.';
                    $instructions[] = 'Check available slots returned by the system before confirming.';
                    $instructions[] = 'Confirm the booking and review its status in your appointments list.';
                }

                foreach ($instructions as $i => $ins) {
                    $steps[] = [
                        'number' => $i + 1,
                        'instruction' => $ins,
                        'note' => $data['step_notes'][$i] ?? null,
                    ];
                }
            }

            if ($topic === 'cancel_appointment') {
                $settings = AppointmentSettings::first();
                $cancelWindow = $settings->cancellation_window_hours ?? 24;
                if ($lang === 'tl') {
                    $instructions = [
                        'Buksan ang iyong Appointments list.',
                        'Hanapin ang appointment na gustong i-cancel.',
                        'Pumindot ng "Cancel" at magbigay ng dahilan (opsyonal).',
                        "Siguraduhing gawin ito hindi bababa sa $cancelWindow oras bago ang schedule.",
                    ];
                } else {
                    $instructions = [
                        'Open your Appointments list.',
                        'Find the appointment you want to cancel.',
                        'Tap "Cancel" and provide a reason (optional).',
                        "Make sure to cancel at least $cancelWindow hours before the scheduled time.",
                    ];
                }

                foreach ($instructions as $i => $ins) {
                    $steps[] = ['number' => $i + 1, 'instruction' => $ins, 'note' => $data['step_notes'][$i] ?? null];
                }
            }

            if ($topic === 'request_refund') {
                $settings = AppointmentSettings::first();
                $refundDays = $settings->refund_processing_days ?? 5;
                if ($lang === 'tl') {
                    $instructions = [
                        'Pumunta sa Payment History sa iyong account.',
                        'Hanapin ang payment na gusto mong i-refund at i-click ang "Request Refund".',
                        'Pumili ng dahilan at magbigay ng karagdagang detalye kung kinakailangan.',
                        'I-submit ang request at maghintay ng admin review.',
                        "Karaniwang tumatagal ng $refundDays business days ang processing.",
                    ];
                } else {
                    $instructions = [
                        'Go to Payment History in your account.',
                        'Find the payment you want refunded and click "Request Refund".',
                        'Select a reason and provide details if needed.',
                        'Submit the request and wait for admin review.',
                        "Processing typically takes $refundDays business days.",
                    ];
                }

                foreach ($instructions as $i => $ins) {
                    $steps[] = ['number' => $i + 1, 'instruction' => $ins, 'note' => $data['step_notes'][$i] ?? null];
                }
            }
        } catch (\Exception $e) {
            Log::debug('buildSteps dynamic generation error: ' . $e->getMessage());
        }

        return $steps;
    }

    /**
     * Build warnings and recommendations based on context
     */
    private function buildWarnings(string $topic, array $data, string $language): array
    {
        $warnings = [];
        $lang = $language === 'tl' || $language === 'filipino' ? 'tl' : 'en';

        try {
            $settings = AppointmentSettings::first();
            $cancelWindow = $settings->cancellation_window_hours ?? 24;
            $refundDays = $settings->refund_processing_days ?? 5;

            // Cancellation warnings
            if ($topic === 'cancel_appointment') {
                if ($lang === 'en') {
                    $warnings[] = "Cancellations should be made at least $cancelWindow hours before the appointment.";
                    if (!empty($data['has_payment'])) {
                        $warnings[] = 'If you have already paid, you can request a refund after cancellation. Refund processing times vary.';
                    }
                } else {
                    $warnings[] = "Dapat i-cancel ang appointment hindi bababa sa $cancelWindow oras bago ang schedule.";
                    if (!empty($data['has_payment'])) {
                        $warnings[] = 'Kung nakabayad ka na, pwede kang mag-request ng refund pagkatapos ng cancellation. Iba-iba ang processing time.';
                    }
                }
            }

            // Refund warnings
            if ($topic === 'request_refund') {
                if ($lang === 'en') {
                    $warnings[] = 'Refund requests are subject to admin review and approval.';
                    $warnings[] = "Processing typically takes around $refundDays business days.";
                } else {
                    $warnings[] = 'Ang refund requests ay nangangailangan ng admin review at approval.';
                    $warnings[] = "Karaniwang tumatagal ng humigit-kumulang $refundDays business days ang processing.";
                }
            }

            // Booking warnings
            if ($topic === 'booking' || $topic === 'book_appointment') {
                if (!empty($data['limited_slots'])) {
                    $warnings[] = $lang === 'en'
                        ? 'Limited slots are available for your preferred date. Consider alternative dates to increase availability.'
                        : 'Limitado ang available slots para sa pinili mong petsa. Subukan ang ibang petsa para sa mas maraming slots.';
                }
            }
        } catch (\Exception $e) {
            Log::debug('buildWarnings dynamic fetch error: ' . $e->getMessage());
        }

        return $warnings;
    }

    /**
     * Build follow-up prompt
     */
    private function buildFollowUpPrompt(string $topic, string $language): string
    {
        $lang = $language === 'tl' || $language === 'filipino' ? 'tl' : 'en';

        // Build dynamic follow-up using available actions and counts
        try {
            $serviceCount = Service::where('is_active', true)->count();

            if ($lang === 'tl') {
                $options = [
                    'Tingnan ang aking mga appointment',
                    'Mag-book ng appointment',
                ];
                if ($serviceCount > 0) {
                    $options[] = "Tingnan ang aming mga serbisyo ($serviceCount)";
                }
                return implode(' · ', $options) . ' — ano ang gusto mong gawin?';
            }

            $options = [
                'View my appointments',
                'Book an appointment',
            ];
            if ($serviceCount > 0) {
                $options[] = "View our services ($serviceCount)";
            }
            return implode(' · ', $options) . ' — what would you like to do next?';
        } catch (\Exception $e) {
            Log::debug('buildFollowUpPrompt dynamic error: ' . $e->getMessage());
        }

        return $lang === 'tl' ? 'May iba pa bang maitutulong ko sa iyo?' : 'Is there anything else you need help with?';
    }

    /**
     * Convert structured response to natural language
     */
    public function structuredToNaturalLanguage(array $structuredResponse): string
    {
        $parts = [];

        foreach ($structuredResponse['sections'] as $section) {
            switch ($section['type']) {
                case 'introduction':
                    $parts[] = $section['content'];
                    break;
                    
                case 'options':
                    $parts[] = "\n\n**" . $section['title'] . ":**";
                    foreach ($section['items'] as $item) {
                        $parts[] = $item['number'] . ". **" . $item['label'] . "**" . 
                                  ($item['description'] ? " - " . $item['description'] : "");
                    }
                    break;
                    
                case 'steps':
                    $parts[] = "\n\n**" . $section['title'] . ":**";
                    foreach ($section['items'] as $item) {
                        $parts[] = $item['number'] . ". " . $item['instruction'];
                        if ($item['note']) {
                            $parts[] = "   _Note: " . $item['note'] . "_";
                        }
                    }
                    break;
                    
                case 'warnings':
                    $parts[] = "\n\n**" . $section['title'] . ":**";
                    foreach ($section['items'] as $warning) {
                        $parts[] = "• " . $warning;
                    }
                    break;
                    
                case 'follow_up':
                    $parts[] = "\n\n" . $section['content'];
                    break;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Enhance message with typo correction and normalization
     * Returns both original and corrected versions
     */
    public function enhanceMessage(string $message): array
    {
        return [
            'original' => $message,
            'normalized' => $this->normalizeMessage($message),
            'corrections_made' => $this->getCorrections($message),
            'detected_language' => $this->detectMessageLanguage($message),
        ];
    }

    /**
     * Normalize message - fix typos, expand abbreviations
     */
    private function normalizeMessage(string $message): string
    {
        $normalized = $message;

        // Common typo corrections (comprehensive list)
        $corrections = [
            // Appointment variations
            'appintment' => 'appointment', 'apointment' => 'appointment', 
            'appointmnt' => 'appointment', 'appoitment' => 'appointment',
            'apptment' => 'appointment', 'appt' => 'appointment',
            'apts' => 'appointments', 'sked' => 'schedule',
            'resked' => 'reschedule', 'resched' => 'reschedule',
            
            // Payment variations
            'bayad' => 'payment', 'pament' => 'payment',
            'paymnt' => 'payment', 'pymnt' => 'payment',
            
            // Common English typos
            'pls' => 'please', 'plz' => 'please', 'thx' => 'thanks',
            'tnx' => 'thanks', 'ty' => 'thank you', 'thnks' => 'thanks',
            'hw' => 'how', 'wht' => 'what', 'wer' => 'where',
            'wen' => 'when', 'bcoz' => 'because', 'coz' => 'because',
            'tmrw' => 'tomorrow', '2day' => 'today', '2mrw' => 'tomorrow',
            
            // Taglish/Tagalog shortcuts
            'pano' => 'paano', 'pwd' => 'pwede', 'pde' => 'pwede',
            'mgkano' => 'magkano', 'gsto' => 'gusto', 'klangan' => 'kailangan',
            'nman' => 'naman', 'lng' => 'lang', 'dn' => 'din',
            'kc' => 'kasi', 'kse' => 'kasi', 'nmn' => 'naman',
            'po' => 'po', 'opo' => 'opo', 'sge' => 'sige',
            
            // Common misspellings
            'servise' => 'service', 'servis' => 'service',
            'serbisyo' => 'service', 'cancle' => 'cancel',
            'cancell' => 'cancel', 'cansel' => 'cancel',
            'refnd' => 'refund', 'refun' => 'refund',
            'approv' => 'approve', 'aprove' => 'approve',
        ];

        foreach ($corrections as $wrong => $right) {
            $normalized = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/i', $right, $normalized);
        }

        return $normalized;
    }

    /**
     * Get list of corrections made
     */
    private function getCorrections(string $message): array
    {
        $corrections = [];
        $normalized = $this->normalizeMessage($message);
        
        if ($normalized !== $message) {
            $corrections['modified'] = true;
            $corrections['original'] = $message;
            $corrections['corrected'] = $normalized;
        }
        
        return $corrections;
    }

    /**
     * Detect message language
     */
    private function detectMessageLanguage(string $message): string
    {
        $tagalogIndicators = [
            'ang', 'ng', 'sa', 'ko', 'mo', 'ka', 'ako', 'ikaw', 'siya',
            'kami', 'kayo', 'sila', 'na', 'pa', 'ba', 'po', 'opo',
            'hindi', 'oo', 'ano', 'sino', 'saan', 'kailan', 'bakit',
            'paano', 'gusto', 'kailangan', 'pwede', 'meron', 'wala',
            'mga', 'yung', 'yun', 'ito', 'iyan', 'iyon', 'dito', 'doon',
            'salamat', 'sige', 'naman', 'lang', 'din', 'rin', 'kasi',
        ];

        $words = preg_split('/\s+/', strtolower($message));
        $tagalogCount = 0;
        $totalWords = count($words);

        foreach ($words as $word) {
            if (in_array($word, $tagalogIndicators)) {
                $tagalogCount++;
            }
        }

        $tagalogRatio = $totalWords > 0 ? $tagalogCount / $totalWords : 0;

        if ($tagalogRatio > 0.4) {
            return 'tagalog';
        } elseif ($tagalogRatio > 0.15) {
            return 'taglish';
        }
        
        return 'english';
    }

    /**
     * Store conversation context for intelligent follow-ups
     */
    public function storeContext(int $userId, string $conversationId, array $context): void
    {
        $key = self::CONTEXT_CACHE_PREFIX . $userId . '_' . $conversationId;
        
        $existingContext = Cache::get($key, []);
        $mergedContext = array_merge($existingContext, $context);
        $mergedContext['updated_at'] = now()->toDateTimeString();
        
        Cache::put($key, $mergedContext, self::CONTEXT_TTL);
    }

    /**
     * Retrieve conversation context
     */
    public function getContext(int $userId, string $conversationId): array
    {
        $key = self::CONTEXT_CACHE_PREFIX . $userId . '_' . $conversationId;
        return Cache::get($key, []);
    }

    /**
     * Clear conversation context
     */
    public function clearContext(int $userId, string $conversationId): void
    {
        $key = self::CONTEXT_CACHE_PREFIX . $userId . '_' . $conversationId;
        Cache::forget($key);
    }
}
