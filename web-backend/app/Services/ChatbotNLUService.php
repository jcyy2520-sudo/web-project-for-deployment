<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ChatbotNLUService - Enhanced Natural Language Understanding
 * 
 * @deprecated This service uses pattern matching which is less accurate than 
 *             semantic embeddings. Use VectorEmbeddingService + UnifiedChatbotService
 *             for the LLM-first architecture. This service remains for backward 
 *             compatibility with legacy endpoints.
 * 
 * MIGRATION PATH:
 * - Old: ChatbotNLUService->detectIntent() → pattern match → confidence threshold
 * - New: VectorEmbeddingService->semanticSearch() → LLM with context
 * 
 * See UNIFIED_CHATBOT_ARCHITECTURE.md for details.
 * 
 * Advanced Natural Language Understanding with:
 * - Fuzzy matching for typos and variations
 * - Intent recognition from user input
 * - Entity extraction (IDs, dates, amounts, names)
 * - Contextual understanding with conversation memory
 * - Support for multiple languages (English, Filipino/Taglish)
 * - Smart spell correction and slang normalization
 * - Confidence scoring and clarification prompts
 * - Content filtering and safety checks
 * - Out-of-scope detection
 * 
 * Handles incomplete messages, spelling mistakes, slang, and informal text
 */
class ChatbotNLUService
{
    /**
     * Conversation context storage key prefix
     */
    private const CONTEXT_CACHE_PREFIX = 'chatbot_context_';
    private const CONTEXT_TTL = 1800; // 30 minutes

    /**
     * Profanity and offensive language patterns (English + Filipino)
     */
    private array $profanityPatterns = [
        // English profanity
        '/\bf+u+c+k+\w*/i', '/\bs+h+i+t+\w*/i', '/\ba+s+s+h+o+l+e+/i', '/\bb+i+t+c+h+\w*/i',
        '/\bc+u+n+t+/i', '/\bd+i+c+k+\w*/i', '/\bp+u+s+s+y+/i', '/\bf+a+g+\w*/i',
        '/\br+e+t+a+r+d+\w*/i', '/\bw+h+o+r+e+/i', '/\bs+l+u+t+/i', '/\bstfu\b/i', '/\bwtf\b/i',
        // Filipino profanity
        '/\bp+u+t+a+n*g*\s*i+n+a+/i', '/\bg+a+g+o+/i', '/\bt+a+n+g+i+n+a+/i', '/\bu+l+o+l+/i',
        '/\bb+o+b+o+/i', '/\bt+a+r+a+n+t+a+d+o+/i', '/\bl+i+n+t+i+k+/i', '/\bp+u+n+y+e+t+a+/i',
        '/\bl+e+c+h+e+/i', '/\bp+a+k+y+u+/i', '/\bp+a+k+s+h+e+t+/i',
        // Directed hostility
        '/\b(stupid|idiot|dumb|useless)\s*(bot|ai|assistant|chatbot)/i',
    ];

    /**
     * Harmful content patterns
     */
    private array $harmfulPatterns = [
        '/\b(how|can|help)\s*(to)?\s*(kill|murder|hurt|harm|attack)/i',
        '/\b(suicide|kill\s*myself|end\s*(my)?\s*life)/i',
        '/\b(weapon|gun|bomb|explosive)\s*(make|create|build)/i',
        '/\b(hack|steal|scam|fraud|illegal)/i',
    ];

    /**
     * Intent patterns with comprehensive fuzzy matching support
     * Includes Taglish (Tagalog-English mix) variations
     */
    private array $intentPatterns = [
        // ==================== APPOINTMENT INTENTS ====================
        'view_appointments' => [
            'patterns' => [
                'show my appointments', 'list appointments', 'my bookings', 'show bookings',
                'view my appointments', 'see my appointments', 'what are my appointments',
                'do i have appointments', 'any appointments', 'upcoming appointments',
                'mga appointment ko', 'anong appointment ko', 'appointments ko'
            ],
            'keywords' => ['appointment', 'booking', 'schedule', 'booked', 'my appointments', 'reservation'],
            'shortcuts' => ['apts', 'apts?', 'appts', 'bookings', 'sched'],
            'taglish' => ['mga booking', 'ano appointments', 'appointments ba', 'may sched ba'],
            'role_hint' => ['client', 'admin', 'cashier'],
        ],
        'check_appointment_status' => [
            'patterns' => [
                'what is my appointment status', 'check status', 'appointment pending',
                'is my appointment approved', 'check my booking status', 'appointment status',
                'where is my appointment', 'track appointment', 'appointment update',
                'anong status ng appointment', 'approved na ba', 'status ng booking'
            ],
            'keywords' => ['status', 'pending', 'approved', 'declined', 'completed', 'check', 'track', 'update'],
            'queries' => ['when is', 'what time', 'which day', 'is it approved', 'what happened'],
            'taglish' => ['status ba', 'kelan na', 'approved na', 'pending pa ba'],
            'role_hint' => ['client'],
        ],
        'book_appointment' => [
            'patterns' => [
                'book appointment', 'make appointment', 'schedule appointment', 'reserve appointment',
                'create booking', 'set appointment', 'new appointment', 'i want to book',
                'how to book', 'booking process', 'make a reservation',
                'magbook ako', 'gusto ko magbook', 'paano mag book'
            ],
            'keywords' => ['book', 'schedule', 'reserve', 'new appointment', 'create', 'set up'],
            'questions' => ['how do i book', 'can i book', 'where to book', 'book now', 'schedule now'],
            'taglish' => ['pano magbook', 'magpareserve', 'gusto ko magschedule', 'booking ba'],
            'role_hint' => ['client', 'guest'],
        ],
        'cancel_appointment' => [
            'patterns' => [
                'cancel appointment', 'cancel booking', 'cancel my appointment', 'delete appointment',
                'remove booking', 'i want to cancel', 'cancel this', 'stop appointment',
                'icancel ko', 'cancel na lang', 'ayaw ko na ituloy'
            ],
            'keywords' => ['cancel', 'remove', 'delete', 'stop', 'terminate', 'dont want'],
            'taglish' => ['cancel ko', 'icancel', 'wag na', 'ayaw na'],
            'role_hint' => ['client', 'admin'],
            'requires_entity' => 'appointment_id',
        ],
        'reschedule_appointment' => [
            'patterns' => [
                'reschedule appointment', 'move appointment', 'change appointment date',
                'change appointment time', 'postpone appointment', 'move my booking',
                'change schedule', 'different date', 'another time',
                'palitan ang date', 'ilipat ang appointment', 'ibang araw'
            ],
            'keywords' => ['reschedule', 'move', 'change', 'postpone', 'another', 'different', 'transfer'],
            'taglish' => ['resched', 'lipat sched', 'ibang araw', 'palitan'],
            'role_hint' => ['client', 'admin'],
            'requires_entity' => 'appointment_id',
        ],

        // ==================== PAYMENT INTENTS ====================
        'view_payments' => [
            'patterns' => [
                'show my payments', 'payment history', 'my transactions', 'view payments',
                'see payments', 'payment records', 'what have i paid', 'my payment list',
                'mga bayad ko', 'payment history ko', 'ano binayad ko'
            ],
            'keywords' => ['payment', 'paid', 'transaction', 'history', 'receipt', 'records'],
            'taglish' => ['mga bayad', 'binayaran ko', 'history ng bayad'],
            'role_hint' => ['client'],
        ],
        'check_payment_status' => [
            'patterns' => [
                'payment pending', 'is payment due', 'did i pay', 'payment status',
                'check payment', 'how much do i owe', 'outstanding balance', 'amount due',
                'bayad na ba ako', 'magkano babayaran', 'may utang ba ako'
            ],
            'keywords' => ['payment', 'paid', 'pending', 'due', 'owe', 'balance', 'amount'],
            'taglish' => ['bayad na ba', 'magkano', 'may babayaran pa', 'kulang pa'],
            'role_hint' => ['client'],
        ],
        'process_payment' => [
            'patterns' => [
                'process payment', 'collect payment', 'mark as paid', 'payment received',
                'record payment', 'accept payment', 'client paid', 'customer paid',
                'nagbayad na', 'receive payment', 'payment complete'
            ],
            'keywords' => ['process', 'collect', 'record', 'accept', 'receive', 'mark paid'],
            'cashier_actions' => ['process payment', 'collect', 'mark paid', 'received'],
            'taglish' => ['bayad na siya', 'nakuha na bayad', 'paid na'],
            'role_hint' => ['cashier', 'admin'],
            'requires_entity' => 'appointment_id',
        ],

        // ==================== REFUND INTENTS ====================
        'request_refund' => [
            'patterns' => [
                'request refund', 'want refund', 'refund my money', 'get refund',
                'i need a refund', 'refund please', 'return my payment', 'money back',
                'ibalik ang bayad', 'refund po', 'gusto ko irefund'
            ],
            'keywords' => ['refund', 'money back', 'return', 'reimburse'],
            'taglish' => ['ibalik pera', 'refund ko', 'ibalik bayad'],
            'role_hint' => ['client'],
            'requires_entity' => 'appointment_id',
        ],
        'check_refund_status' => [
            'patterns' => [
                'check refund status', 'refund pending', 'is my refund approved',
                'where is my refund', 'refund update', 'when will i get refund',
                'status ng refund ko', 'approved na ba refund', 'kelan refund'
            ],
            'keywords' => ['refund', 'status', 'approved', 'pending', 'where', 'when'],
            'taglish' => ['refund status', 'kelan na refund', 'approved na ba'],
            'role_hint' => ['client'],
        ],
        'view_refunds' => [
            'patterns' => [
                'show refunds', 'refund history', 'my refunds', 'list refunds',
                'refund records', 'mga refund ko'
            ],
            'keywords' => ['refund', 'history', 'records', 'list'],
            'taglish' => ['mga refund', 'refund history ko'],
            'role_hint' => ['client'],
        ],
        'approve_refund' => [
            'patterns' => [
                'approve refund', 'accept refund', 'confirm refund', 'grant refund',
                'allow refund', 'approve this refund', 'i approve refund'
            ],
            'keywords' => ['approve', 'accept', 'confirm', 'grant', 'allow', 'refund'],
            'role_hint' => ['admin', 'cashier'],
            'requires_entity' => 'refund_id',
        ],
        'reject_refund' => [
            'patterns' => [
                'reject refund', 'deny refund', 'decline refund', 'refuse refund'
            ],
            'keywords' => ['reject', 'deny', 'decline', 'refuse', 'refund'],
            'role_hint' => ['admin', 'cashier'],
            'requires_entity' => 'refund_id',
        ],
        'process_refund' => [
            'patterns' => [
                'process refund', 'complete refund', 'release refund', 'give refund',
                'pay refund', 'issue refund', 'refund now'
            ],
            'keywords' => ['process', 'complete', 'release', 'issue', 'give', 'refund'],
            'role_hint' => ['cashier', 'admin'],
            'requires_entity' => 'refund_id',
        ],

        // ==================== SERVICE INTENTS ====================
        'view_services' => [
            'patterns' => [
                'what services', 'show services', 'list services', 'available services',
                'service menu', 'what do you offer', 'services offered', 'all services',
                'ano services', 'mga services', 'anong pwede i-avail'
            ],
            'keywords' => ['service', 'services', 'offer', 'available', 'menu', 'options'],
            'taglish' => ['ano services', 'mga pwede', 'anong meron'],
            'role_hint' => ['client', 'guest', 'admin', 'cashier'],
        ],
        'service_details' => [
            'patterns' => [
                'service details', 'about service', 'what is notary', 'what is legal',
                'service information', 'explain service', 'tell me about', 'service description',
                'ano yung service', 'explain', 'paano yung process'
            ],
            'keywords' => ['details', 'about', 'description', 'info', 'information', 'explain', 'what is'],
            'taglish' => ['ano yun', 'explain mo', 'paano yan'],
            'role_hint' => ['client', 'guest'],
        ],
        'service_pricing' => [
            'patterns' => [
                'service pricing', 'how much service', 'service cost', 'service fee',
                'price list', 'rates', 'how much is', 'what is the cost', 'fees',
                'magkano', 'presyo', 'singil', 'bayad'
            ],
            'keywords' => ['price', 'cost', 'fee', 'rate', 'amount', 'charge', 'how much'],
            'taglish' => ['magkano ba', 'presyo ng', 'bayad sa'],
            'role_hint' => ['client', 'guest'],
        ],

        // ==================== SCHEDULE INTENTS ====================
        'view_availability' => [
            'patterns' => [
                'show availability', 'available times', 'available dates', 'when available',
                'free slots', 'open slots', 'schedule availability', 'booking availability',
                'kelan pwede', 'anong available', 'may slot ba'
            ],
            'keywords' => ['available', 'slot', 'free', 'open', 'vacancy', 'schedule'],
            'taglish' => ['pwede ba', 'may slot', 'kelan available'],
            'role_hint' => ['client', 'guest', 'admin'],
        ],
        'business_hours' => [
            'patterns' => [
                'business hours', 'office hours', 'when open', 'operating hours',
                'what time open', 'what time close', 'schedule of operation', 'working hours',
                'anong oras bukas', 'kelan sarado', 'oras ng office'
            ],
            'keywords' => ['hours', 'open', 'close', 'operating', 'working', 'schedule'],
            'taglish' => ['oras ba', 'bukas ba', 'sarado na ba'],
            'role_hint' => ['client', 'guest'],
        ],
        'contact_info' => [
            'patterns' => [
                'contact information', 'contact details', 'how to contact', 'phone number',
                'email address', 'contact you', 'reach you', 'get in touch', 'contact number',
                'your number', 'your email', 'office number', 'call you', 'contact na',
                'pano kayo kontakin', 'ano number', 'ano email', 'paano tumawag'
            ],
            'keywords' => ['contact', 'phone', 'email', 'call', 'reach', 'number', 'telephone'],
            'taglish' => ['kontakin', 'tawag', 'number niyo', 'email niyo'],
            'role_hint' => ['client', 'guest'],
        ],
        'location_info' => [
            'patterns' => [
                'where are you located', 'where is your office', 'office location', 'address',
                'where to find you', 'your location', 'office address', 'where is the office',
                'saan kayo', 'saan ang office', 'anong address', 'location niyo', 'nasaan kayo',
                'how to get there', 'directions', 'where can i find you'
            ],
            'keywords' => ['location', 'address', 'where', 'located', 'office', 'find', 'directions'],
            'taglish' => ['saan', 'nasaan', 'location', 'address niyo'],
            'role_hint' => ['client', 'guest'],
        ],
        'about_business' => [
            'patterns' => [
                'about the lawyer', 'about the attorney', 'who is the lawyer', 'who is the attorney',
                'about your company', 'about your office', 'about your firm', 'who runs this',
                'who is peejay', 'who is pj', 'atty de guzman', 'attorney de guzman',
                'sino ang abogado', 'sino ang attorney', 'sino lawyer', 'about peejayy',
                'what services do you offer', 'what do you do', 'what kind of lawyer',
                'ano services', 'ano ginagawa', 'anong serbisyo'
            ],
            'keywords' => ['lawyer', 'attorney', 'atty', 'legal', 'firm', 'company', 'about', 'who'],
            'taglish' => ['abogado', 'sino', 'tungkol sa', 'anong serbisyo'],
            'role_hint' => ['client', 'guest'],
        ],

        // ==================== USER PROFILE INTENTS ====================
        'view_profile' => [
            'patterns' => [
                'my profile', 'my account', 'account details', 'personal info',
                'user details', 'my information', 'view account', 'show profile',
                'profile ko', 'account ko', 'info ko'
            ],
            'keywords' => ['profile', 'account', 'info', 'details', 'personal', 'information'],
            'taglish' => ['profile ko', 'info ko', 'account ko'],
            'role_hint' => ['client'],
        ],
        'edit_profile' => [
            'patterns' => [
                'edit profile', 'update account', 'change details', 'update info',
                'modify profile', 'change password', 'update phone', 'change email',
                'baguhin profile', 'update info ko', 'palitan password'
            ],
            'keywords' => ['edit', 'update', 'change', 'modify', 'profile', 'account'],
            'taglish' => ['palitan', 'baguhin', 'update ko'],
            'role_hint' => ['client'],
        ],

        // ==================== ADMIN INTENTS ====================
        'view_pending_appointments' => [
            'patterns' => [
                'pending appointments', 'appointments to approve', 'waiting appointments',
                'pending bookings', 'unapproved appointments', 'needs approval',
                'show pending', 'list pending', 'what needs approval',
                'mga pending', 'kailangan i-approve'
            ],
            'keywords' => ['pending', 'approve', 'waiting', 'unapproved', 'needs action'],
            'taglish' => ['pending pa', 'kailangan action', 'waiting approval'],
            'role_hint' => ['admin', 'cashier'],
        ],
        'approve_appointment' => [
            'patterns' => [
                'approve appointment', 'accept appointment', 'confirm appointment',
                'approve booking', 'confirm booking', 'accept booking',
                'i-approve appointment', 'approve na'
            ],
            'keywords' => ['approve', 'accept', 'confirm', 'okay', 'grant'],
            'taglish' => ['approve na', 'i-approve', 'okay na'],
            'role_hint' => ['admin'],
            'requires_entity' => 'appointment_id',
        ],
        'decline_appointment' => [
            'patterns' => [
                'decline appointment', 'reject appointment', 'deny appointment',
                'decline booking', 'reject booking', 'refuse appointment',
                'i-decline appointment', 'reject na'
            ],
            'keywords' => ['decline', 'reject', 'deny', 'refuse', 'disapprove'],
            'taglish' => ['reject na', 'tanggihan', 'wag i-approve'],
            'role_hint' => ['admin'],
            'requires_entity' => 'appointment_id',
        ],
        'complete_appointment' => [
            'patterns' => [
                'complete appointment', 'mark complete', 'finish appointment', 'mark done',
                'appointment done', 'completed', 'mark as complete', 'finish this',
                'tapos na appointment', 'done na'
            ],
            'keywords' => ['complete', 'finish', 'done', 'mark', 'completed'],
            'taglish' => ['tapos na', 'done na', 'completed na'],
            'role_hint' => ['admin', 'cashier'],
            'requires_entity' => 'appointment_id',
        ],
        'view_all_appointments' => [
            'patterns' => [
                'all appointments', 'show all appointments', 'list all bookings',
                'every appointment', 'appointment list', 'all bookings today',
                'lahat ng appointments', 'buong list'
            ],
            'keywords' => ['all', 'every', 'list', 'appointments', 'bookings'],
            'taglish' => ['lahat ng appointments', 'buong list', 'all na'],
            'role_hint' => ['admin', 'cashier'],
        ],
        'view_analytics' => [
            'patterns' => [
                'show analytics', 'view statistics', 'dashboard stats', 'reports',
                'performance metrics', 'system stats', 'analytics dashboard',
                'ipakita analytics', 'stats ng system'
            ],
            'keywords' => ['analytics', 'statistics', 'stats', 'metrics', 'reports', 'dashboard'],
            'taglish' => ['stats na', 'analytics mo', 'reports'],
            'role_hint' => ['admin'],
        ],
        'manage_users' => [
            'patterns' => [
                'manage users', 'user list', 'show users', 'all users',
                'user management', 'list clients', 'registered users',
                'mga users', 'client list'
            ],
            'keywords' => ['users', 'clients', 'customers', 'manage', 'list'],
            'taglish' => ['mga users', 'lahat ng clients'],
            'role_hint' => ['admin'],
        ],

        // ==================== CASHIER INTENTS ====================
        'view_pending_payments' => [
            'patterns' => [
                'pending payments', 'unpaid', 'who needs to pay', 'payment due',
                'outstanding payments', 'collect payments', 'awaiting payment',
                'sino di pa bayad', 'pending bayad', 'kailangan maningil'
            ],
            'keywords' => ['pending payment', 'unpaid', 'due', 'collect', 'outstanding'],
            'taglish' => ['di pa bayad', 'pending bayad', 'singil pa'],
            'role_hint' => ['cashier', 'admin'],
        ],
        'view_pending_refunds' => [
            'patterns' => [
                'pending refunds', 'refund requests', 'refunds to process',
                'awaiting refund', 'refund queue', 'process refunds',
                'mga pending refund', 'refund requests'
            ],
            'keywords' => ['pending refund', 'refund request', 'process refund', 'queue'],
            'taglish' => ['pending refunds', 'refund requests na'],
            'role_hint' => ['cashier', 'admin'],
        ],
        'shift_report' => [
            'patterns' => [
                'shift report', 'daily report', 'transaction summary', 'my transactions',
                'today transactions', 'sales report', 'daily summary', 'cash report',
                'report ng shift', 'transactions ngayon', 'summary ng benta'
            ],
            'keywords' => ['shift', 'report', 'daily', 'transaction', 'summary', 'sales'],
            'taglish' => ['report ngayon', 'shift ko', 'transactions today'],
            'role_hint' => ['cashier'],
        ],
        'verify_receipt' => [
            'patterns' => [
                'verify receipt', 'check receipt', 'receipt validation', 'confirm receipt',
                'validate payment proof', 'check payment proof'
            ],
            'keywords' => ['verify', 'receipt', 'proof', 'validate', 'confirm'],
            'role_hint' => ['cashier'],
        ],

        // ==================== SYSTEM & HELP INTENTS ====================
        'system_status' => [
            'patterns' => [
                'system status', 'is system working', 'system health', 'check system',
                'any issues', 'server status', 'is everything working',
                'okay ba system', 'may problema ba'
            ],
            'keywords' => ['system', 'status', 'working', 'health', 'server', 'issue'],
            'taglish' => ['okay ba', 'may issue ba', 'working ba'],
            'role_hint' => ['admin'],
        ],
        'help_register' => [
            'patterns' => [
                'how to register', 'how do i register', 'sign up', 'create account',
                'register account', 'new account', 'register now', 'make account',
                'paano mag register', 'paano mag signup', 'gumawa ng account',
                'bagong account', 'register ako', 'sign up ko'
            ],
            'keywords' => ['register', 'signup', 'sign up', 'account', 'create', 'new', 'join'],
            'taglish' => ['pano mag register', 'mag signup', 'gumawa ng account', 'bagong account'],
            'role_hint' => ['guest'],
        ],
        'help_login' => [
            'patterns' => [
                'how to login', 'how do i login', 'how to log in', 'sign in',
                'login help', 'cant login', 'forgot password', 'reset password',
                'paano mag login', 'login ako', 'sign in naman', 'password ko'
            ],
            'keywords' => ['login', 'log in', 'sign in', 'password', 'account', 'access', 'signin'],
            'taglish' => ['pano mag login', 'mag sign in', 'password ko', 'login help'],
            'role_hint' => ['guest'],
        ],
        'help' => [
            'patterns' => [
                'help', 'what can you do', 'how to use', 'help me', 'i need help',
                'commands', 'options', 'features', 'what do you do',
                'tulong', 'paano', 'help naman'
            ],
            'keywords' => ['help', 'how', 'what', 'support', 'assist', 'guide', 'tutorial'],
            'taglish' => ['tulong', 'paano ba', 'tulungan mo ko'],
            'role_hint' => ['client', 'admin', 'cashier', 'guest'],
        ],
        'greeting' => [
            'patterns' => [
                'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening',
                'kamusta', 'kumusta', 'musta', 'oy', 'hoy'
            ],
            'keywords' => ['hello', 'hi', 'hey', 'good', 'morning', 'afternoon', 'evening'],
            'taglish' => ['kamusta', 'musta', 'ano news'],
            'role_hint' => ['client', 'admin', 'cashier', 'guest'],
        ],
        'farewell' => [
            'patterns' => [
                'bye', 'goodbye', 'thanks', 'thank you', 'see you', 'take care',
                'salamat', 'paalam', 'sige', 'okay bye'
            ],
            'keywords' => ['bye', 'goodbye', 'thanks', 'thank', 'see you', 'later'],
            'taglish' => ['salamat', 'paalam', 'sige na'],
            'role_hint' => ['client', 'admin', 'cashier', 'guest'],
        ],
        'general_question' => [
            'patterns' => [],
            'keywords' => [],
            'role_hint' => ['client', 'admin', 'cashier', 'guest'],
        ],
    ];

    /**
     * Entity extraction patterns - Enhanced with more variations
     */
    private array $entityPatterns = [
        'appointment_id' => '/(?:appointment|apt|booking|id|number|ref|reference|#)[\s:]*(?:#)?(\d{1,10})/i',
        'user_id' => '/(?:user|client|patient|customer|id|user_id)[\s:]*(?:#)?(\d{1,10})/i',
        'refund_id' => '/(?:refund|refund_id|refund id|ref)[\s:]*(?:#)?(\d{1,10})/i',
        'payment_id' => '/(?:payment|payment_id|payment id|pay)[\s:]*(?:#)?(\d{1,10})/i',
        'date' => '/(\d{1,2})[-\/](\d{1,2})[-\/](\d{2,4})|(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/i',
        'date_relative' => '/(today|tomorrow|yesterday|next week|this week|next month)/i',
        'time' => '/(\d{1,2}):(\d{2})\s*(?:am|pm|AM|PM)?|(\d{1,2})\s*(?:am|pm|AM|PM)/i',
        'amount' => '/(?:php|₱|\$)?[\s]*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.?\d*)/i',
        'status' => '/(pending|approved|declined|completed|cancelled|refunded|paid|unpaid|partial|overdue|confirmed)/i',
        'service_name' => '/(notary|legal|document|affidavit|authentication|apostille|oath|acknowledgment|deed|contract)/i',
        'email' => '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
        'phone' => '/((?:\+63|0)?9\d{9})/i',
        'name' => '/(?:name|client|customer|user)[\s:]+([A-Za-z\s]{2,50})/i',
    ];

    /**
     * Common misspellings and their corrections - Extended
     * Includes informal typing, SMS speak, and common typos
     * 
     * COMPREHENSIVE LIST: Covers English, Tagalog, Taglish, SMS speak, 
     * keyboard proximity errors, and phonetic misspellings
     */
    private array $commonMisspellings = [
        // ==================== APPOINTMENT VARIATIONS ====================
        'appintment' => 'appointment', 'apointment' => 'appointment',
        'appointmnt' => 'appointment', 'appoitment' => 'appointment',
        'appointmen' => 'appointment', 'appoinment' => 'appointment',
        'apptment' => 'appointment', 'apptmnt' => 'appointment',
        'apt' => 'appointment', 'apts' => 'appointments',
        'appt' => 'appointment', 'appts' => 'appointments',
        'appointmwnt' => 'appointment', 'appoinrment' => 'appointment',
        'appoitnment' => 'appointment', 'appointmnet' => 'appointment',
        'appountment' => 'appointment', 'appointement' => 'appointment',
        
        // ==================== BOOKING VARIATIONS ====================
        'beuking' => 'booking', 'bokking' => 'booking',
        'bookng' => 'booking', 'bookin' => 'booking',
        'bok' => 'book', 'buking' => 'booking',
        'bookinh' => 'booking', 'bokng' => 'booking',
        'bookign' => 'booking', 'boking' => 'booking',
        
        // ==================== SCHEDULE VARIATIONS ====================
        'reshcedule' => 'reschedule', 'reschdule' => 'reschedule',
        'reshedule' => 'reschedule', 'schdule' => 'schedule',
        'sched' => 'schedule', 'resched' => 'reschedule',
        'skejul' => 'schedule', 'skedul' => 'schedule',
        'scheduel' => 'schedule', 'shcedule' => 'schedule',
        'schedulle' => 'schedule', 'scedule' => 'schedule',
        'reschd' => 'reschedule', 'risched' => 'reschedule',
        
        // ==================== PAYMENT VARIATIONS ====================
        'pament' => 'payment', 'paymnet' => 'payment',
        'paymet' => 'payment', 'paymnt' => 'payment',
        'pymnt' => 'payment', 'paymment' => 'payment',
        'payemnt' => 'payment', 'paiment' => 'payment',
        'paymente' => 'payment', 'paymenta' => 'payment',
        
        // ==================== REFUND VARIATIONS ====================
        'refnd' => 'refund', 'refun' => 'refund',
        'refunf' => 'refund', 'refunde' => 'refund',
        'refundd' => 'refund', 'reefund' => 'refund',
        'rerfund' => 'refund', 'refudn' => 'refund',
        
        // ==================== SERVICE VARIATIONS ====================
        'servise' => 'service', 'servis' => 'service',
        'servic' => 'service', 'servce' => 'service',
        'serbice' => 'service', 'servicee' => 'service',
        'serivce' => 'service', 'srvc' => 'service',
        
        // ==================== STATUS VARIATIONS ====================
        'satuts' => 'status', 'staus' => 'status',
        'sttaus' => 'status', 'stautus' => 'status',
        'statsu' => 'status', 'stauts' => 'status',
        
        // ==================== GENERAL ENGLISH CORRECTIONS ====================
        'recieve' => 'receive', 'verificaion' => 'verification',
        'complte' => 'complete', 'complet' => 'complete',
        'approv' => 'approve', 'aprove' => 'approve',
        'cancl' => 'cancel', 'cansel' => 'cancel',
        'cancle' => 'cancel', 'canecl' => 'cancel',
        'availble' => 'available', 'avalable' => 'available',
        'avaiable' => 'available', 'availabe' => 'available',
        'pendin' => 'pending', 'pendng' => 'pending',
        'pendign' => 'pending', 'pnding' => 'pending',
        'comfirm' => 'confirm', 'confrim' => 'confirm',
        'confirme' => 'confirm', 'conferm' => 'confirm',
        'checl' => 'check', 'chck' => 'check',
        'requst' => 'request', 'rquest' => 'request',
        'reqest' => 'request', 'requet' => 'request',
        'porcess' => 'process', 'proccess' => 'process',
        'proces' => 'process', 'proess' => 'process',
        
        // ==================== SMS/TEXT SPEAK ====================
        'pls' => 'please', 'plz' => 'please', 'plss' => 'please',
        'plez' => 'please', 'plzzz' => 'please', 'plsss' => 'please',
        'thx' => 'thanks', 'tnx' => 'thanks', 'ty' => 'thank you',
        'thnks' => 'thanks', 'thankss' => 'thanks', 'thnx' => 'thanks',
        'tysm' => 'thank you so much', 'tyvm' => 'thank you very much',
        'hlp' => 'help', 'hw' => 'how', 'wht' => 'what',
        'wer' => 'where', 'wen' => 'when', 'wat' => 'what',
        'wut' => 'what', 'hwo' => 'how', 'howw' => 'how',
        'tmrw' => 'tomorrow', 'tday' => 'today', 'ystrdy' => 'yesterday',
        '2day' => 'today', '2mrw' => 'tomorrow', '2morrow' => 'tomorrow',
        'bcoz' => 'because', 'bcuz' => 'because', 'coz' => 'because',
        'cuz' => 'because', 'bcos' => 'because', 'cos' => 'because',
        'asap' => 'as soon as possible', 'btw' => 'by the way',
        'imo' => 'in my opinion', 'fyi' => 'for your information',
        'idk' => 'i do not know', 'nvm' => 'never mind',
        'msg' => 'message', 'ppl' => 'people', 'rn' => 'right now',
        'ur' => 'your', 'u' => 'you', 'r' => 'are',
        'b4' => 'before', 'l8r' => 'later', 'gr8' => 'great',
        
        // ==================== TAGALOG/TAGLISH CORRECTIONS ====================
        // Common Tagalog words kept as-is or normalized
        'po' => 'po', 'opo' => 'opo', 'naman' => 'naman',
        'lang' => 'lang', 'din' => 'din', 'rin' => 'rin',
        'kasi' => 'kasi', 'daw' => 'daw', 'raw' => 'raw',
        'nga' => 'nga', 'sana' => 'sana', 'talaga' => 'talaga',
        'muna' => 'muna', 'palagi' => 'palagi', 'pala' => 'pala',
        
        // Tagalog typo corrections
        'pano' => 'paano', 'paanoo' => 'paano', 'paanu' => 'paano',
        'panu' => 'paano', 'pno' => 'paano', 'paaano' => 'paano',
        'pde' => 'pwede', 'pwd' => 'pwede', 'pwde' => 'pwede',
        'pwedee' => 'pwede', 'pwdi' => 'pwede', 'puwede' => 'pwede',
        'mgkano' => 'magkano', 'magknu' => 'magkano', 'mkano' => 'magkano',
        'magkaano' => 'magkano', 'magkno' => 'magkano', 'mgkno' => 'magkano',
        'gsto' => 'gusto', 'guato' => 'gusto', 'gusot' => 'gusto',
        'gustoo' => 'gusto', 'gst' => 'gusto', 'guato' => 'gusto',
        'klangan' => 'kailangan', 'kailngan' => 'kailangan', 'kelangan' => 'kailangan',
        'kelngan' => 'kailangan', 'kailanagn' => 'kailangan', 'kaylangan' => 'kailangan',
        'kaylngan' => 'kailangan', 'kylanagan' => 'kailangan', 'kailangn' => 'kailangan',
        'salamaat' => 'salamat', 'salamta' => 'salamat', 'slmat' => 'salamat',
        'salamt' => 'salamat', 'slamat' => 'salamat', 'salamet' => 'salamat',
        'kelan' => 'kailan', 'klan' => 'kailan', 'kaylan' => 'kailan',
        'kailan' => 'kailan', 'kilan' => 'kailan', 'kailn' => 'kailan',
        'san' => 'saan', 'saan' => 'saan', 'ssan' => 'saan', 'sna' => 'saan',
        'snoo' => 'sino', 'snio' => 'sino', 'sno' => 'sino', 'sinoo' => 'sino',
        'anoo' => 'ano', 'anio' => 'ano', 'annoo' => 'ano',
        'baket' => 'bakit', 'bkit' => 'bakit', 'bakt' => 'bakit',
        'sge' => 'sige', 'sgee' => 'sige', 'sigee' => 'sige', 'sigue' => 'sige',
        'ayaw' => 'ayaw', 'ayws' => 'ayaw', 'ayew' => 'ayaw',
        'mron' => 'meron', 'mrn' => 'meron', 'meorn' => 'meron',
        'wla' => 'wala', 'walaa' => 'wala', 'walang' => 'walang',
        'nandto' => 'nandito', 'nndito' => 'nandito', 'ndito' => 'nandito',
        'andyan' => 'andiyan', 'ndyan' => 'andiyan', 'nandyan' => 'andiyan',
        'yng' => 'yung', 'yun' => 'yun', 'ung' => 'yung',
        'ito' => 'ito', 'etoo' => 'ito', 'itoo' => 'ito',
        'yan' => 'iyan', 'iyn' => 'iyan', 'eyan' => 'iyan',
        'yon' => 'iyon', 'iyn' => 'iyon', 'eyun' => 'iyon',
        
        // Common Taglish expressions
        'nman' => 'naman', 'nmn' => 'naman', 'nmaan' => 'naman',
        'kc' => 'kasi', 'kse' => 'kasi', 'kci' => 'kasi',
        'lnag' => 'lang', 'lng' => 'lang', 'lnng' => 'lang',
        'dn' => 'din', 'dinn' => 'din', 'diin' => 'din',
        'rn' => 'rin', 'riin' => 'rin', 'riiin' => 'rin',
        
        // Common mixed language mistakes
        'serbisyo' => 'service', 'serbisyoo' => 'service',
        'bayad' => 'payment', 'byad' => 'payment', 'bayd' => 'payment',
        'presyo' => 'price', 'preso' => 'price', 'presio' => 'price',
        'opisina' => 'office', 'opsina' => 'office', 'opissina' => 'office',
        'abugado' => 'lawyer', 'abogado' => 'lawyer', 'abgado' => 'lawyer',
        'notaryo' => 'notary', 'notario' => 'notary', 'nutaryo' => 'notary',
        'dokumento' => 'document', 'dcumento' => 'document', 'dokumeto' => 'document',
        'kontrata' => 'contract', 'kontrata' => 'contract', 'kuntrata' => 'contract',
        
        // Time expressions  
        'ngaun' => 'ngayon', 'ngyon' => 'ngayon', 'ngyn' => 'ngayon',
        'buaks' => 'bukas', 'bkas' => 'bukas', 'bukass' => 'bukas',
        'kahaon' => 'kahapon', 'khapon' => 'kahapon', 'kahaopn' => 'kahapon',
        'mamya' => 'mamaya', 'mamay' => 'mamaya', 'mmaya' => 'mamaya',
        'kaagad' => 'agad', 'agadd' => 'agad', 'agaad' => 'agad',
        
        // Numbers in text  
        'isa' => 'isa', 'dalwa' => 'dalawa', 'dalwa' => 'dalawa',
        'tatlo' => 'tatlo', 'tatloo' => 'tatlo', 'tatlok' => 'tatlo',
        'apat' => 'apat', 'apatt' => 'apat', 'apaat' => 'apat',
        'lima' => 'lima', 'limaa' => 'lima', 'liima' => 'lima',
    ];

    /**
     * Taglish/slang normalization mapping
     * Enhanced with comprehensive Filipino expressions, internet slang, 
     * and contextual understanding for natural language processing
     * 
     * DESIGN: Maps informal/slang terms to normalized forms for better intent matching
     */
    private array $slangNormalization = [
        // ==================== FILIPINO QUESTION WORDS ====================
        'paki' => 'please',           // Polite request marker
        'pakisabi' => 'please tell',
        'pakibigay' => 'please give',
        'pakitulong' => 'please help',
        'sana' => 'hope',
        'gusto' => 'want',
        'gustong' => 'want to',
        'kailangan' => 'need',
        'kailangang' => 'need to',
        'pwede' => 'can',
        'pwedeng' => 'can',
        'puwede' => 'can',
        'paano' => 'how',
        'paanong' => 'how',
        'ano' => 'what',
        'anong' => 'what',
        'anung' => 'what',
        'kailan' => 'when',
        'kelan' => 'when',
        'saan' => 'where',
        'nasaan' => 'where is',
        'sino' => 'who',
        'sinong' => 'who',
        'bakit' => 'why',
        'bat' => 'why',
        'magkano' => 'how much',
        'gaano' => 'how much',
        'ilan' => 'how many',
        'ilang' => 'how many',
        
        // ==================== FILIPINO VERBS (Actions) ====================
        'bayad' => 'payment',
        'bayaran' => 'pay',
        'magbayad' => 'pay',
        'nagbayad' => 'paid',
        'babayaran' => 'will pay',
        'singil' => 'charge',
        'singilin' => 'collect payment',
        'ibalik' => 'return',
        'ibabalik' => 'will return',
        'kumuha' => 'get',
        'kunin' => 'get',
        'makuha' => 'get',
        'tingnan' => 'check',
        'tignan' => 'check',
        'tiningnan' => 'checked',
        'tanungin' => 'ask',
        'magtanong' => 'ask',
        'itanong' => 'ask',
        'pakitawag' => 'please call',
        'tawagan' => 'call',
        'tumawag' => 'call',
        'magbook' => 'book',
        'magpareserve' => 'reserve',
        'icancel' => 'cancel',
        'ikansela' => 'cancel',
        'ikansel' => 'cancel',
        'kanselahin' => 'cancel',
        'baguhin' => 'change',
        'palitan' => 'replace',
        'ipakita' => 'show',
        'pakita' => 'show',
        'ipakitang' => 'show',
        
        // ==================== FILIPINO NOUNS ====================
        'salamat' => 'thank you',
        'maraming salamat' => 'thank you very much',
        'tulong' => 'help',
        'problema' => 'problem',
        'isyu' => 'issue',
        'serbisyo' => 'service',
        'mga serbisyo' => 'services',
        'opisina' => 'office',
        'oras' => 'time',
        'petsa' => 'date',
        'bukas' => 'tomorrow',
        'ngayon' => 'today',
        'kahapon' => 'yesterday',
        'mamaya' => 'later',
        'agad' => 'immediately',
        'kaagad' => 'immediately',
        'abugado' => 'lawyer',
        'abogado' => 'lawyer',
        'notaryo' => 'notary',
        'dokumento' => 'document',
        'papeles' => 'documents',
        'kasulatan' => 'document',
        'kontrata' => 'contract',
        'kasunduan' => 'agreement',
        'resibo' => 'receipt',
        'presyo' => 'price',
        'halaga' => 'amount',
        'pera' => 'money',
        'kwarta' => 'money',
        'balanse' => 'balance',
        'listahan' => 'list',
        'schedule' => 'schedule',
        'iskedyul' => 'schedule',
        
        // ==================== COMMON EXPRESSIONS ====================
        'eto' => 'here',
        'ito' => 'this',
        'dito' => 'here',
        'doon' => 'there',
        'oo' => 'yes',
        'opo' => 'yes',
        'hindi' => 'no',
        'hindi po' => 'no',
        'wala' => 'none',
        'walang' => 'no',
        'meron' => 'there is',
        'mayroon' => 'there is',
        'sige' => 'okay',
        'sge' => 'okay',
        'ayos' => 'okay',
        'ok' => 'okay',
        'okey' => 'okay',
        'okei' => 'okay',
        'oki' => 'okay',
        'ge' => 'okay',
        'g' => 'okay',
        'sure' => 'sure',
        'syur' => 'sure',
        'syempre' => 'of course',
        'oo naman' => 'yes of course',
        'siyempre' => 'of course',
        'siguro' => 'maybe',
        'marahil' => 'perhaps',
        'baka' => 'maybe',
        'parang' => 'like',
        'tulad' => 'like',
        'katulad' => 'like',
        
        // ==================== POLITE MARKERS ====================
        'po' => '',              // Polite marker (skip in normalization)
        'ho' => '',              // Less formal polite marker
        'nga' => '',             // Emphasis marker
        'naman' => '',           // Softener
        'pala' => '',            // Discovery marker
        'na' => '',              // Already/now marker
        'pa' => '',              // Still/more marker
        'ba' => '',              // Question marker
        'kaya' => '',            // Ability/wonder marker
        'daw' => '',             // Hearsay marker
        'raw' => '',             // Hearsay marker (formal)
        
        // ==================== INTERNET SLANG (TAGALOG) ====================
        'haha' => '',            // Skip
        'hehe' => '',            // Skip
        'hihi' => '',            // Skip
        'char' => '',            // "Just kidding"
        'charot' => '',          // "Just kidding"
        'chos' => '',            // "Just kidding"
        'eme' => '',             // Filler word
        'emz' => '',             // Filler word
        'lodi' => 'idol',        // Reversed "idol"
        'petmalu' => 'amazing',  // "Malupet" reversed
        'werpa' => 'power',      // "Power" reversed
        'awit' => 'sad',         // Expression of sadness
        'sana all' => 'wish everyone had that',
        'skl' => 'just sharing', // "Share ko lang"
        'ctto' => '',            // "Credits to the owner"
        
        // ==================== INTERNET SLANG (ENGLISH) ====================
        'asap' => 'as soon as possible',
        'pls' => 'please',
        'plsss' => 'please',
        'ty' => 'thank you',
        'tysm' => 'thank you so much',
        'tyvm' => 'thank you very much',
        'np' => 'no problem',
        'nvm' => 'never mind',
        'idk' => 'i do not know',
        'btw' => 'by the way',
        'rn' => 'right now',
        'irl' => 'in real life',
        'lol' => '',             // Skip
        'lmao' => '',            // Skip
        'omg' => '',             // Skip
        'wtf' => '',             // Skip (filter)
        'brb' => 'be right back',
        'ttyl' => 'talk to you later',
        'fyi' => 'for your information',
        'imo' => 'in my opinion',
        'tbh' => 'to be honest',
        'tho' => 'though',
        'tfw' => '',             // Skip
        'mfw' => '',             // Skip
        
        // ==================== ABBREVIATIONS ====================
        'appt' => 'appointment',
        'apt' => 'appointment',
        'svc' => 'service',
        'svcs' => 'services',
        'amt' => 'amount',
        'bal' => 'balance',
        'acct' => 'account',
        'info' => 'information',
        'mins' => 'minutes',
        'hrs' => 'hours',
        'wk' => 'week',
        'wks' => 'weeks',
        'mo' => 'month',
        'mos' => 'months',
        'yr' => 'year',
        'yrs' => 'years',
        'no' => 'number',
        'num' => 'number',
        'ref' => 'reference',
        'id' => 'identification',
        'approx' => 'approximately',
        'est' => 'estimated',
        
        // ==================== GREETING VARIATIONS ====================
        'kamusta' => 'hello',
        'kumusta' => 'hello',
        'musta' => 'hello',
        'uy' => 'hi',
        'hoy' => 'hi',
        'oi' => 'hi',
        'pre' => 'friend',       // "Pare"
        'par' => 'friend',       // "Pare"
        'bro' => 'friend',
        'sis' => 'friend',
        'besh' => 'friend',      // "Best friend"
        'beshie' => 'friend',
        'mare' => 'friend',      // "Kumare"
        'teh' => 'friend',       // "Ate"
        'tsong' => 'friend',     // "Katotohanan"
        
        // ==================== FAREWELL VARIATIONS ====================
        'paalam' => 'goodbye',
        'bye' => 'goodbye',
        'babay' => 'goodbye',
        'ingat' => 'take care',
        'mag-ingat' => 'take care',
        'kita' => 'see you',
        'see you' => 'goodbye',
        'hanggang sa muli' => 'until next time',
    ];

    /**
     * Detect intent from user message with role awareness
     * 
     * @param string $message User message
     * @param array|null $contextHints Additional context (role, user_id, conversation_id)
     * @return array Intent detection result
     */
    public function detectIntent(string $message, ?array $contextHints = null): array
    {
        try {
            $message = $this->cleanMessage($message);
            $lowerMessage = strtolower($message);
            $userRole = $contextHints['role'] ?? 'client';
            $userId = $contextHints['user_id'] ?? null;
            $conversationId = $contextHints['conversation_id'] ?? null;

            // Get conversation context for better understanding
            $conversationContext = $this->getConversationContext($userId, $conversationId);

            // Check for follow-up intents based on conversation context
            $followUpIntent = $this->detectFollowUpIntent($lowerMessage, $conversationContext);
            if ($followUpIntent) {
                return $followUpIntent;
            }

            // Try exact pattern matching first (highest confidence)
            foreach ($this->intentPatterns as $intent => $data) {
                // Check if intent is valid for user's role
                if (!$this->isIntentValidForRole($intent, $userRole, $data)) {
                    continue;
                }

                $score = $this->matchIntent($lowerMessage, $intent, $data);
                if ($score > 0.85) {
                    $result = [
                        'intent' => $intent,
                        'confidence' => $score,
                        'detected_at' => now()->toDateTimeString(),
                        'raw_message' => $message,
                        'requires_clarification' => $score < 0.95,
                        'role_validated' => true,
                        'match_type' => 'exact',
                    ];

                    // Check if intent requires an entity
                    if (isset($data['requires_entity'])) {
                        $entities = $this->extractEntities($message);
                        if (empty($entities[$data['requires_entity']])) {
                            $result['missing_entity'] = $data['requires_entity'];
                            $result['requires_clarification'] = true;
                        }
                    }

                    // Store context for follow-ups
                    $this->storeConversationContext($userId, $conversationId, $intent, $this->extractEntities($message));

                    return $result;
                }
            }

            // Try Taglish pattern matching
            $taglishMatch = $this->matchTaglishIntent($lowerMessage, $userRole);
            if ($taglishMatch['score'] > 0.7) {
                $this->storeConversationContext($userId, $conversationId, $taglishMatch['intent'], $this->extractEntities($message));
                return [
                    'intent' => $taglishMatch['intent'],
                    'confidence' => $taglishMatch['score'],
                    'detected_at' => now()->toDateTimeString(),
                    'raw_message' => $message,
                    'requires_clarification' => true,
                    'match_type' => 'taglish',
                    'language_detected' => 'tl',
                ];
            }

            // Try fuzzy matching as fallback
            $bestMatch = $this->fuzzyMatchIntent($lowerMessage, $userRole);
            if ($bestMatch['score'] > 0.6) {
                $this->storeConversationContext($userId, $conversationId, $bestMatch['intent'], $this->extractEntities($message));
                return [
                    'intent' => $bestMatch['intent'],
                    'confidence' => $bestMatch['score'],
                    'detected_at' => now()->toDateTimeString(),
                    'raw_message' => $message,
                    'requires_clarification' => true,
                    'match_type' => 'fuzzy',
                    'note' => 'Fuzzy matched - may need clarification',
                ];
            }

            // Check for greeting/farewell patterns
            $socialIntent = $this->detectSocialIntent($lowerMessage);
            if ($socialIntent) {
                return $socialIntent;
            }

            // Default to general question with LOW confidence
            // This signals to the controller to use LLM for a smarter response
            // IMPORTANT: Provide hints about what the user might be asking about
            $generalHints = $this->analyzeMessageForHints($lowerMessage);

            return [
                'intent' => 'general_question',
                'confidence' => 0.3, // LOW confidence = controller should use LLM
                'detected_at' => now()->toDateTimeString(),
                'raw_message' => $message,
                'requires_clarification' => true,
                'match_type' => 'default',
                'note' => 'Could not determine specific intent - using LLM for intelligent response',
                'possible_topics' => $generalHints['topics'] ?? [], // Help LLM understand context
                'message_keywords' => $generalHints['keywords'] ?? [], // Extracted keywords for context
            ];
        } catch (\Exception $e) {
            Log::warning('Error detecting intent', ['message' => $message, 'error' => $e->getMessage()]);
            return [
                'intent' => 'general_question',
                'confidence' => 0.3,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if intent is valid for user's role
     */
    private function isIntentValidForRole(string $intent, string $userRole, array $intentData): bool
    {
        if (empty($intentData['role_hint'])) {
            return true; // No role restriction
        }

        $allowedRoles = $intentData['role_hint'];
        
        // Map roles
        $roleMap = [
            'user' => 'client',
            'customer' => 'client',
        ];
        
        $normalizedRole = $roleMap[$userRole] ?? $userRole;
        
        return in_array($normalizedRole, $allowedRoles);
    }

    /**
     * Match Taglish patterns specifically
     */
    private function matchTaglishIntent(string $message, string $userRole): array
    {
        $bestMatch = ['intent' => null, 'score' => 0];

        foreach ($this->intentPatterns as $intent => $data) {
            if (!$this->isIntentValidForRole($intent, $userRole, $data)) {
                continue;
            }

            if (!empty($data['taglish'])) {
                foreach ($data['taglish'] as $pattern) {
                    if (stripos($message, $pattern) !== false) {
                        return ['intent' => $intent, 'score' => 0.85];
                    }
                    
                    // Fuzzy match for Taglish
                    $similarity = $this->calculateSimilarity($message, $pattern);
                    if ($similarity > $bestMatch['score']) {
                        $bestMatch = ['intent' => $intent, 'score' => $similarity];
                    }
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Detect social intents (greetings, farewells)
     */
    private function detectSocialIntent(string $message): ?array
    {
        $greetings = ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'kamusta', 'kumusta', 'musta'];
        $farewells = ['bye', 'goodbye', 'thanks', 'thank you', 'salamat', 'paalam', 'sige'];

        foreach ($greetings as $greeting) {
            if (stripos($message, $greeting) !== false) {
                return [
                    'intent' => 'greeting',
                    'confidence' => 0.95,
                    'detected_at' => now()->toDateTimeString(),
                    'raw_message' => $message,
                    'requires_clarification' => false,
                    'match_type' => 'social',
                ];
            }
        }

        foreach ($farewells as $farewell) {
            if (stripos($message, $farewell) !== false) {
                return [
                    'intent' => 'farewell',
                    'confidence' => 0.95,
                    'detected_at' => now()->toDateTimeString(),
                    'raw_message' => $message,
                    'requires_clarification' => false,
                    'match_type' => 'social',
                ];
            }
        }

        return null;
    }

    /**
     * Detect follow-up intent based on conversation context
     */
    private function detectFollowUpIntent(string $message, ?array $context): ?array
    {
        if (!$context || empty($context['last_intent'])) {
            return null;
        }

        $lastIntent = $context['last_intent'];
        $lastEntities = $context['last_entities'] ?? [];

        // Confirmation patterns
        $confirmPatterns = ['yes', 'yeah', 'yep', 'confirm', 'proceed', 'okay', 'ok', 'sure', 'go ahead', 'oo', 'sige'];
        $denyPatterns = ['no', 'nope', 'cancel', 'stop', 'nevermind', 'hindi', 'wag'];

        foreach ($confirmPatterns as $pattern) {
            if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $message)) {
                return [
                    'intent' => $lastIntent . '_confirm',
                    'confidence' => 0.9,
                    'detected_at' => now()->toDateTimeString(),
                    'raw_message' => $message,
                    'is_followup' => true,
                    'original_intent' => $lastIntent,
                    'inherited_entities' => $lastEntities,
                    'action' => 'confirm',
                ];
            }
        }

        foreach ($denyPatterns as $pattern) {
            if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $message)) {
                return [
                    'intent' => $lastIntent . '_cancel',
                    'confidence' => 0.9,
                    'detected_at' => now()->toDateTimeString(),
                    'raw_message' => $message,
                    'is_followup' => true,
                    'original_intent' => $lastIntent,
                    'action' => 'cancel',
                ];
            }
        }

        // Check if user is providing missing entity
        $newEntities = $this->extractEntities($message);
        if (!empty($newEntities)) {
            return [
                'intent' => $lastIntent,
                'confidence' => 0.85,
                'detected_at' => now()->toDateTimeString(),
                'raw_message' => $message,
                'is_followup' => true,
                'original_intent' => $lastIntent,
                'entities_provided' => $newEntities,
                'merged_entities' => array_merge($lastEntities, $newEntities),
            ];
        }

        return null;
    }

    /**
     * Store conversation context for follow-up detection
     */
    private function storeConversationContext(?int $userId, ?string $conversationId, string $intent, array $entities): void
    {
        if (!$userId && !$conversationId) {
            return;
        }

        $key = self::CONTEXT_CACHE_PREFIX . ($userId ?? 'guest_' . $conversationId);
        
        Cache::put($key, [
            'last_intent' => $intent,
            'last_entities' => $entities,
            'timestamp' => now()->toDateTimeString(),
        ], self::CONTEXT_TTL);
    }

    /**
     * Get conversation context
     */
    private function getConversationContext(?int $userId, ?string $conversationId): ?array
    {
        if (!$userId && !$conversationId) {
            return null;
        }

        $key = self::CONTEXT_CACHE_PREFIX . ($userId ?? 'guest_' . $conversationId);
        
        return Cache::get($key);
    }

    /**
     * Calculate string similarity using multiple algorithms
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Levenshtein similarity
        $distance = levenshtein(strtolower($str1), strtolower($str2));
        $maxLen = max(strlen($str1), strlen($str2));
        $levenshteinScore = $maxLen > 0 ? 1 - ($distance / $maxLen) : 1;

        // Similar text percentage
        similar_text(strtolower($str1), strtolower($str2), $percent);
        $similarTextScore = $percent / 100;

        // Return weighted average
        return ($levenshteinScore * 0.6) + ($similarTextScore * 0.4);
    }

    /**
     * Extract entities from user message - Enhanced
     * 
     * @param string $message User message
     * @return array Extracted entities
     */
    public function extractEntities(string $message): array
    {
        $entities = [];

        try {
            // Extract appointment ID
            if (preg_match($this->entityPatterns['appointment_id'], $message, $matches)) {
                $entities['appointment_id'] = (int) $matches[1];
            }

            // Extract user ID
            if (preg_match($this->entityPatterns['user_id'], $message, $matches)) {
                $entities['user_id'] = (int) $matches[1];
            }

            // Extract refund ID
            if (preg_match($this->entityPatterns['refund_id'], $message, $matches)) {
                $entities['refund_id'] = (int) $matches[1];
            }

            // Extract payment ID
            if (preg_match($this->entityPatterns['payment_id'], $message, $matches)) {
                $entities['payment_id'] = (int) $matches[1];
            }

            // Extract date (multiple formats)
            if (preg_match($this->entityPatterns['date'], $message, $matches)) {
                if (!empty($matches[4])) {
                    // YYYY-MM-DD format
                    $entities['date'] = [
                        'year' => (int) $matches[4],
                        'month' => (int) $matches[5],
                        'day' => (int) $matches[6],
                        'raw' => $matches[0],
                        'formatted' => "{$matches[4]}-{$matches[5]}-{$matches[6]}",
                    ];
                } else {
                    // DD-MM-YYYY format
                    $entities['date'] = [
                        'day' => (int) $matches[1],
                        'month' => (int) $matches[2],
                        'year' => (int) $matches[3],
                        'raw' => $matches[0],
                    ];
                }
            }

            // Extract relative date
            if (preg_match($this->entityPatterns['date_relative'], $message, $matches)) {
                $entities['date_relative'] = strtolower($matches[1]);
                $entities['date'] = $this->resolveRelativeDate($matches[1]);
            }

            // Extract time
            if (preg_match($this->entityPatterns['time'], $message, $matches)) {
                $hour = (int) ($matches[1] ?? $matches[3]);
                $minute = (int) ($matches[2] ?? 0);
                
                // Check for AM/PM
                if (preg_match('/pm/i', $matches[0]) && $hour < 12) {
                    $hour += 12;
                } elseif (preg_match('/am/i', $matches[0]) && $hour === 12) {
                    $hour = 0;
                }

                $entities['time'] = [
                    'hour' => $hour,
                    'minute' => $minute,
                    'raw' => $matches[0],
                    'formatted' => sprintf('%02d:%02d', $hour, $minute),
                ];
            }

            // Extract amount
            if (preg_match($this->entityPatterns['amount'], $message, $matches)) {
                $amount = str_replace(',', '', $matches[1]);
                $entities['amount'] = floatval($amount);
            }

            // Extract status
            if (preg_match($this->entityPatterns['status'], $message, $matches)) {
                $entities['status'] = strtolower($matches[1]);
            }

            // Extract service name
            if (preg_match($this->entityPatterns['service_name'], $message, $matches)) {
                $entities['service'] = strtolower($matches[1]);
            }

            // Extract email
            if (preg_match($this->entityPatterns['email'], $message, $matches)) {
                $entities['email'] = strtolower($matches[1]);
            }

            // Extract phone
            if (preg_match($this->entityPatterns['phone'], $message, $matches)) {
                $entities['phone'] = $matches[1];
            }

            // Extract name
            if (preg_match($this->entityPatterns['name'], $message, $matches)) {
                $entities['name'] = trim($matches[1]);
            }

            return $entities;
        } catch (\Exception $e) {
            Log::warning('Error extracting entities', ['message' => $message, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Resolve relative date to actual date
     */
    private function resolveRelativeDate(string $relativeDate): array
    {
        $date = match (strtolower($relativeDate)) {
            'today' => now(),
            'tomorrow' => now()->addDay(),
            'yesterday' => now()->subDay(),
            'next week' => now()->addWeek(),
            'this week' => now()->startOfWeek(),
            'next month' => now()->addMonth(),
            default => now(),
        };

        return [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'formatted' => $date->format('Y-m-d'),
            'relative' => $relativeDate,
        ];
    }

    /**
     * Match intent against message with scoring
     * 
     * @param string $lowerMessage Lowercase message
     * @param string $intent Intent to match
     * @param array $intentData Intent pattern data
     * @return float Match score (0-1)
     */
    private function matchIntent(string $lowerMessage, string $intent, array $intentData): float
    {
        $maxScore = 0;

        // Check patterns (exact and partial)
        if (!empty($intentData['patterns'])) {
            foreach ($intentData['patterns'] as $pattern) {
                if (stripos($lowerMessage, $pattern) !== false) {
                    return 0.95;
                }
            }
        }

        // Check keywords
        if (!empty($intentData['keywords'])) {
            $matchedKeywords = 0;
            foreach ($intentData['keywords'] as $keyword) {
                if (stripos($lowerMessage, $keyword) !== false) {
                    $matchedKeywords++;
                }
            }
            if ($matchedKeywords > 0) {
                $score = min(0.85, 0.5 + ($matchedKeywords * 0.15));
                $maxScore = max($maxScore, $score);
            }
        }

        // Check shortcuts
        if (!empty($intentData['shortcuts'])) {
            foreach ($intentData['shortcuts'] as $shortcut) {
                if (preg_match('/\b' . preg_quote($shortcut, '/') . '\b/i', $lowerMessage)) {
                    return 0.85;
                }
            }
        }

        return $maxScore;
    }

    /**
     * Fuzzy match intent using Levenshtein distance
     * 
     * @param string $message Lowercase message
     * @param string $userRole User's role for filtering
     * @return array Best match result
     */
    private function fuzzyMatchIntent(string $message, string $userRole = 'client'): array
    {
        $bestMatch = ['intent' => 'general_question', 'score' => 0];

        foreach ($this->intentPatterns as $intent => $data) {
            // Check if intent is valid for user's role
            if (!$this->isIntentValidForRole($intent, $userRole, $data)) {
                continue;
            }

            // Check all keywords with Levenshtein
            if (!empty($data['keywords'])) {
                foreach ($data['keywords'] as $keyword) {
                    foreach (explode(' ', $message) as $word) {
                        if (strlen($word) < 3) continue; // Skip very short words
                        
                        $distance = levenshtein($word, $keyword);
                        $maxLen = max(strlen($word), strlen($keyword));
                        $score = $maxLen > 0 ? 1 - ($distance / $maxLen) : 0;

                        if ($score > $bestMatch['score'] && $score > 0.7) {
                            $bestMatch = ['intent' => $intent, 'score' => $score];
                        }
                    }
                }
            }

            // Also check patterns with fuzzy matching
            if (!empty($data['patterns'])) {
                foreach ($data['patterns'] as $pattern) {
                    $similarity = $this->calculateSimilarity($message, $pattern);
                    if ($similarity > $bestMatch['score'] && $similarity > 0.6) {
                        $bestMatch = ['intent' => $intent, 'score' => $similarity];
                    }
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Correct common misspellings
     * 
     * @param string $message User message
     * @return string Corrected message
     */
    private function correctMisspellings(string $message): string
    {
        $corrected = $message;

        foreach ($this->commonMisspellings as $misspelled => $correct) {
            $corrected = preg_replace('/\b' . $misspelled . '\b/i', $correct, $corrected);
        }

        return $corrected;
    }

    /**
     * Clean message (normalize whitespace, remove special chars)
     * 
     * @param string $message Raw message
     * @return string Cleaned message
     */
    private function cleanMessage(string $message): string
    {
        // Correct misspellings first
        $message = $this->correctMisspellings($message);

        // Normalize whitespace
        $message = preg_replace('/\s+/', ' ', trim($message));

        // Remove extra punctuation
        $message = preg_replace('/[?!]{2,}/', '?', $message);

        return $message;
    }

    /**
     * Analyze sentiment of message
     * 
     * @param string $message User message
     * @return array Sentiment analysis
     */
    public function analyzeSentiment(string $message): array
    {
        $lowerMessage = strtolower($message);

        // Positive indicators
        $positiveWords = ['thank', 'love', 'great', 'excellent', 'perfect', 'good', 'awesome', 'happy'];
        $positiveCount = 0;
        foreach ($positiveWords as $word) {
            if (stripos($message, $word) !== false) {
                $positiveCount++;
            }
        }

        // Negative indicators
        $negativeWords = ['angry', 'frustrated', 'angry', 'upset', 'problem', 'issue', 'error', 'bad', 'terrible', 'hate'];
        $negativeCount = 0;
        foreach ($negativeWords as $word) {
            if (stripos($message, $word) !== false) {
                $negativeCount++;
            }
        }

        // Urgency indicators
        $urgencyWords = ['urgent', 'asap', 'immediately', 'emergency', 'critical', 'help', 'please'];
        $hasUrgency = false;
        foreach ($urgencyWords as $word) {
            if (stripos($message, $word) !== false) {
                $hasUrgency = true;
                break;
            }
        }

        // Determine sentiment
        if ($negativeCount > $positiveCount) {
            $sentiment = 'negative';
            $score = min(5, 3 + $negativeCount);
        } elseif ($positiveCount > $negativeCount) {
            $sentiment = 'positive';
            $score = min(5, 3 + $positiveCount);
        } else {
            $sentiment = 'neutral';
            $score = 3;
        }

        return [
            'sentiment' => $sentiment,
            'score' => $score, // 1-5 scale
            'has_urgency' => $hasUrgency,
            'confidence' => min(0.95, 0.5 + (max($positiveCount, $negativeCount) * 0.15)),
        ];
    }

    /**
     * Check if message contains inappropriate content
     * 
     * @param string $message User message
     * @return array Content check result
     */
    public function checkContentSafety(string $message): array
    {
        $normalizedMessage = mb_strtolower(trim($message));
        
        // Check for profanity
        foreach ($this->profanityPatterns as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                Log::warning('Chatbot: Profanity detected', [
                    'message_snippet' => substr($message, 0, 50),
                ]);
                return [
                    'safe' => false,
                    'type' => 'profanity',
                    'response' => $this->getInappropriateResponse(),
                ];
            }
        }
        
        // Check for harmful content
        foreach ($this->harmfulPatterns as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                Log::warning('Chatbot: Harmful content detected', [
                    'message_snippet' => substr($message, 0, 50),
                ]);
                return [
                    'safe' => false,
                    'type' => 'harmful',
                    'response' => $this->getHarmfulContentResponse(),
                ];
            }
        }
        
        return ['safe' => true, 'type' => null, 'response' => null];
    }

    /**
     * Check if message is within system scope
     * 
     * @param string $message User message
     * @return array Scope check result
     */
    public function checkSystemScope(string $message): array
    {
        $normalizedMessage = mb_strtolower(trim($message));
        
        // System-related keywords
        $systemKeywords = [
            'appointment', 'booking', 'schedule', 'service', 'payment', 'refund',
            'account', 'profile', 'password', 'login', 'register', 'status',
            'cancel', 'reschedule', 'price', 'cost', 'fee', 'available',
            'hour', 'open', 'close', 'office', 'location', 'help',
            // Filipino
            'bayad', 'presyo', 'magkano', 'serbisyo', 'appointment', 'book',
            'schedule', 'oras', 'bukas', 'sarado', 'tulong'
        ];
        
        // Check if message contains system keywords
        foreach ($systemKeywords as $keyword) {
            if (stripos($normalizedMessage, $keyword) !== false) {
                return ['in_scope' => true, 'reason' => null];
            }
        }
        
        // Out of scope patterns
        $outOfScopePatterns = [
            '/\b(weather|news|sports|movie|music|recipe|joke|story|poem)\b/i',
            '/\b(who\s+is|what\s+is\s+the\s+capital|president|history)\b/i',
            '/\b(translate|translation|language\s+learning)\b/i',
            '/\b(code|program|develop)\s+(me|a)\s+(website|app|software)/i',
            '/\b(medical|health)\s+(advice|diagnosis)/i',
            '/\b(financial|investment|stock|crypto)\s+(advice|tips)/i',
        ];
        
        foreach ($outOfScopePatterns as $pattern) {
            if (preg_match($pattern, $normalizedMessage)) {
                return [
                    'in_scope' => false,
                    'reason' => 'out_of_scope',
                    'response' => $this->getOutOfScopeResponse(),
                ];
            }
        }
        
        // Short greetings are in scope
        if (strlen($normalizedMessage) < 15) {
            return ['in_scope' => true, 'reason' => null];
        }
        
        // Default to in scope but flag as uncertain
        return ['in_scope' => true, 'reason' => null, 'uncertain' => true];
    }

    /**
     * Get response for inappropriate language
     */
    private function getInappropriateResponse(): string
    {
        return "I am here to provide professional assistance with system-related inquiries. Please maintain a professional tone so I can better assist you with appointments, services, or payments.";
    }

    /**
     * Get response for harmful content
     */
    private function getHarmfulContentResponse(): string
    {
        return "I'm not able to assist with that request. If you're experiencing difficulties, please consider reaching out to appropriate support services. I'm here to help with system-related questions about appointments, services, and payments.";
    }

    /**
     * Get response for out of scope requests
     * Provides a friendly response without listing all capabilities
     */
    private function getOutOfScopeResponse(): string
    {
        return "This request is outside the scope of my assistance.";
    }

    /**
     * Build clarification questions for ambiguous intents
     * 
     * @param string $intent Detected intent
     * @param array $extractedEntities Extracted entities
     * @return array Clarification questions
     */
    public function buildClarificationQuestions(string $intent, array $extractedEntities = []): array
    {
        $questions = [];

        switch ($intent) {
            case 'view_appointments':
                if (empty($extractedEntities['appointment_id'])) {
                    $questions[] = "Would you like to see all your appointments or a specific one?";
                }
                break;

            case 'cancel_appointment':
                if (empty($extractedEntities['appointment_id'])) {
                    $questions[] = "Which appointment would you like to cancel? Please provide the appointment ID or date.";
                }
                break;

            case 'check_payment_status':
                if (empty($extractedEntities['appointment_id'])) {
                    $questions[] = "Which appointment's payment status would you like to check?";
                }
                break;

            case 'service_pricing':
                if (empty($extractedEntities['service'])) {
                    $questions[] = "Which service would you like to know the price for?";
                }
                break;

            case 'reschedule_appointment':
                if (empty($extractedEntities['appointment_id'])) {
                    $questions[] = "Which appointment would you like to reschedule? Please provide the appointment ID.";
                }
                if (empty($extractedEntities['date']) && empty($extractedEntities['time'])) {
                    $questions[] = "What date and time would you prefer?";
                }
                break;
        }

        return $questions;
    }

    /**
     * Comprehensive message analysis with safety checks
     * 
     * @param string $message User message
     * @param int|null $userId User ID for context
     * @return array Complete analysis result
     */
    public function analyzeMessageComprehensive(string $message, ?int $userId = null): array
    {
        // Step 1: Content safety check
        $safetyCheck = $this->checkContentSafety($message);
        if (!$safetyCheck['safe']) {
            return [
                'safe' => false,
                'type' => $safetyCheck['type'],
                'response' => $safetyCheck['response'],
                'intent' => null,
                'entities' => [],
                'sentiment' => ['sentiment' => 'negative', 'score' => 5],
            ];
        }
        
        // Step 2: Scope check
        $scopeCheck = $this->checkSystemScope($message);
        if (!$scopeCheck['in_scope']) {
            return [
                'safe' => true,
                'in_scope' => false,
                'response' => $scopeCheck['response'],
                'intent' => 'out_of_scope',
                'entities' => [],
                'sentiment' => $this->analyzeSentiment($message),
            ];
        }
        
        // Step 3: Full NLU analysis
        $analysis = $this->analyze($message, $userId);
        
        return array_merge($analysis, [
            'safe' => true,
            'in_scope' => true,
        ]);
    }

    /**
     * Analyze message to extract hints about possible topics
     * Helps LLM understand what the user might be asking about
     * even when intent pattern matching fails
     * 
     * @param string $message Lowercase message
     * @return array Topics and keywords hints
     */
    private function analyzeMessageForHints(string $message): array
    {
        $topics = [];
        $keywords = [];

        // Extract all words for LLM context
        $words = array_filter(str_word_count($message, 1));
        $keywords = array_slice($words, 0, 10); // Top 10 keywords

        // Analyze for common system-related topics
        $topicPatterns = [
            'appointment' => ['appointment', 'booking', 'schedule', 'book', 'booked', 'appointment'],
            'service' => ['service', 'services', 'offer', 'provided', 'available'],
            'payment' => ['payment', 'pay', 'paid', 'charge', 'cost', 'price', 'bill'],
            'refund' => ['refund', 'return', 'money back', 'refunded', 'reimburs'],
            'status' => ['status', 'pending', 'approved', 'completed', 'where', 'what', 'check'],
            'profile' => ['profile', 'account', 'personal', 'info', 'information'],
            'help' => ['help', 'how', 'guide', 'tutorial', 'assist', 'support'],
            'admin' => ['pending', 'approve', 'review', 'admin', 'approve', 'decline'],
            'cashier' => ['payment', 'collect', 'refund', 'transaction', 'receipt'],
        ];

        foreach ($topicPatterns as $topic => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($message, $pattern)) {
                    $topics[] = $topic;
                    break;
                }
            }
        }

        return [
            'topics' => array_unique($topics),
            'keywords' => array_unique($keywords),
        ];
    }
}
