<?php return array (
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => '12',
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => 'C:\\laragon\\www\\web\\web-backend\\resources\\views',
    ),
    'compiled' => 'C:\\laragon\\www\\web\\web-backend\\storage\\framework\\views',
  ),
  'app' => 
  array (
    'name' => 'Law Notary System',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://localhost:8000',
    'frontend_url' => 'http://localhost:3000',
    'asset_url' => NULL,
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => 'base64:X1tyJlBOhzX77ZdOVcgeh1YNMNcFF4jQLBmOnz2HVkI=',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'broadcasting' => 
  array (
    'default' => 'log',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => 'e0twx3nmhx1ud9wp1znl',
        'secret' => 'qt97kmmtssoiwbbgiwcp',
        'app_id' => '711682',
        'options' => 
        array (
          'host' => 'localhost',
          'port' => '8080',
          'scheme' => 'http',
          'useTLS' => false,
        ),
        'client_options' => 
        array (
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => 'local-key',
        'secret' => 'local-secret',
        'app_id' => 'local',
        'options' => 
        array (
          'cluster' => 'mt1',
          'useTLS' => true,
          'host' => '127.0.0.1',
          'port' => '6001',
          'scheme' => 'http',
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
      ),
    ),
  ),
  'cache' => 
  array (
    'default' => 'database',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'cache',
        'lock_connection' => NULL,
        'lock_table' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => 'C:\\laragon\\www\\web\\web-backend\\storage\\framework/cache/data',
        'lock_path' => 'C:\\laragon\\www\\web\\web-backend\\storage\\framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
    ),
    'prefix' => 'law-notary-system-cache-',
  ),
  'chatbot' => 
  array (
    'identity' => 
    array (
      'name' => 'AI Assistant',
      'description' => 'A smart, accurate, and reliable assistant for the appointment booking system',
      'version' => '2.0.0',
    ),
    'behavior' => 
    array (
      'allowed_actions' => 
      array (
        0 => 'assist',
        1 => 'inform',
        2 => 'guide',
        3 => 'explain',
        4 => 'clarify',
        5 => 'suggest',
      ),
      'forbidden_actions' => 
      array (
        0 => 'execute',
        1 => 'modify',
        2 => 'approve',
        3 => 'reject',
        4 => 'cancel',
        5 => 'delete',
        6 => 'create',
        7 => 'impersonate',
        8 => 'access_external',
        9 => 'submit',
        10 => 'process',
      ),
      'response_style' => 
      array (
        'tone' => 'professional_neutral',
        'verbosity' => 'concise',
        'emoji_usage' => 'none',
        'max_length' => 5000,
        'target_length' => 
        array (
          0 => 50,
          1 => 150,
        ),
      ),
    ),
    'scope' => 
    array (
      'in_scope' => 
      array (
        'appointments' => 
        array (
          0 => 'booking',
          1 => 'scheduling',
          2 => 'rescheduling',
          3 => 'cancellation',
          4 => 'status_checking',
          5 => 'information',
        ),
        'services' => 
        array (
          0 => 'information',
          1 => 'pricing',
          2 => 'availability',
          3 => 'requirements',
          4 => 'process_explanation',
        ),
        'payments' => 
        array (
          0 => 'status',
          1 => 'history',
          2 => 'methods',
          3 => 'refund_requests',
          4 => 'process_explanation',
        ),
        'account' => 
        array (
          0 => 'profile_info',
          1 => 'settings_guidance',
          2 => 'registration_help',
          3 => 'login_help',
        ),
        'system' => 
        array (
          0 => 'business_hours',
          1 => 'location',
          2 => 'contact_info',
          3 => 'features',
          4 => 'how_to_use',
        ),
      ),
      'out_of_scope' => 
      array (
        0 => 'general_knowledge',
        1 => 'entertainment',
        2 => 'personal_opinions',
        3 => 'medical_advice',
        4 => 'legal_advice',
        5 => 'financial_advice',
        6 => 'coding_help',
        7 => 'translation',
        8 => 'news_weather',
        9 => 'external_services',
      ),
    ),
    'roles' => 
    array (
      'guest' => 
      array (
        'display_name' => 'Guest',
        'can_view' => 
        array (
          0 => 'services',
          1 => 'business_hours',
          2 => 'contact_info',
          3 => 'registration_help',
        ),
        'restrictions' => 'Cannot access personal data or perform any account actions',
      ),
      'client' => 
      array (
        'display_name' => 'User',
        'can_view' => 
        array (
          0 => 'own_appointments',
          1 => 'own_payments',
          2 => 'own_refunds',
          3 => 'own_profile',
          4 => 'services',
        ),
        'restrictions' => 'Can only view and manage own data',
      ),
      'cashier' => 
      array (
        'display_name' => 'Cashier',
        'can_view' => 
        array (
          0 => 'pending_payments',
          1 => 'approved_refunds',
          2 => 'shift_reports',
          3 => 'transactions',
        ),
        'restrictions' => 'Limited to payment-related operations',
      ),
      'admin' => 
      array (
        'display_name' => 'Administrator',
        'can_view' => 
        array (
          0 => 'all_appointments',
          1 => 'all_users',
          2 => 'all_payments',
          3 => 'all_refunds',
          4 => 'analytics',
        ),
        'restrictions' => 'Full system visibility, but chatbot still cannot execute actions',
      ),
    ),
    'safety' => 
    array (
      'content_filtering' => 
      array (
        'enabled' => true,
        'log_blocked_content' => true,
        'block_profanity' => true,
        'block_harmful' => true,
        'block_harassment' => true,
        'block_hate_speech' => true,
        'block_sexual_content' => true,
        'block_violence' => true,
        'block_self_harm' => true,
        'block_discrimination' => true,
      ),
      'response_to_inappropriate' => 
      array (
        'calm_redirect' => true,
        'maintain_professionalism' => true,
        'offer_system_help' => true,
        'never_engage' => true,
        'polite_refusal' => true,
      ),
      'harmful_content_handling' => 
      array (
        'log_warning' => true,
        'provide_resources' => false,
        'redirect_to_system' => true,
        'flag_for_review' => true,
      ),
      'moderation_languages' => 
      array (
        0 => 'english',
        1 => 'filipino',
        2 => 'tagalog',
        3 => 'taglish',
      ),
    ),
    'language' => 
    array (
      'supported' => 
      array (
        0 => 'english',
        1 => 'filipino',
        2 => 'tagalog',
        3 => 'taglish',
      ),
      'default' => 'english',
      'auto_detect' => true,
      'respond_in_user_language' => true,
      'handle_informal' => true,
      'handle_misspellings' => true,
      'handle_sms_speak' => true,
      'handle_leetspeak' => true,
      'normalize_input' => true,
      'filipino' => 
      array (
        'handle_po_opo' => true,
        'handle_particle_words' => true,
        'handle_reduplications' => true,
      ),
    ),
    'reliability' => 
    array (
      'no_hallucination' => true,
      'allowed_data_sources' => 
      array (
        0 => 'database_records',
        1 => 'system_configuration',
        2 => 'user_session_data',
        3 => 'business_settings',
      ),
      'missing_data_responses' => 
      array (
        'general' => 'I don\'t have access to that information in the system.',
        'appointment' => 'I can\'t find that appointment information. Please check your dashboard.',
        'payment' => 'Payment details are not available to me right now.',
        'user' => 'I don\'t have access to that user information.',
      ),
      'confidence_thresholds' => 
      array (
        'high' => 0.85,
        'medium' => 0.65,
        'low' => 0.4,
      ),
      'uncertainty_behavior' => 
      array (
        'ask_clarification' => true,
        'use_llm_for_context' => true,
        'never_guess' => true,
        'admit_uncertainty' => true,
      ),
    ),
    'transparency' => 
    array (
      'data_unavailable_response' => 'I don\'t have access to that information in the system.',
      'clarification_needed_response' => 'I want to make sure I understand correctly. Could you please clarify?',
      'capability_limit_response' => 'I\'m designed to assist with information and guidance, but I cannot perform that action directly.',
      'out_of_scope_response' => 'That\'s outside my area of expertise. I specialize in helping with appointments, services, and payments for this system.',
      'acknowledge_ai_nature' => true,
      'never_pretend_human' => true,
      'never_pretend_capabilities' => true,
      'filipino_responses' => 
      array (
        'data_unavailable' => 'Pasensya na, wala akong access sa information na iyan sa system.',
        'clarification_needed' => 'Pakiklarify po kung pwede para masiguradong naiintindihan ko ng tama.',
        'capability_limit' => 'Hindi ko po kayang gawin iyan directly, pero pwede ko po kayong gabayan kung paano.',
        'out_of_scope' => 'Pasensya na, hindi po iyan sakop ng aking tulong. Pwede po akong tumulong sa appointments, services, at payments.',
      ),
    ),
    'error_handling' => 
    array (
      'explain_possible_causes' => true,
      'provide_resolution_steps' => true,
      'suggest_alternatives' => true,
      'offer_support_contact' => true,
      'log_all_errors' => true,
      'error_types' => 
      array (
        'database_error' => 'System data access issue - temporary problem',
        'authentication_error' => 'Session issue - may need to log in again',
        'permission_error' => 'Role-based access restriction',
        'validation_error' => 'Input format issue',
        'not_found' => 'Requested item not found in system',
        'rate_limit' => 'Message limit reached',
        'llm_unavailable' => 'AI service temporarily unavailable',
        'general' => 'Unexpected issue occurred',
      ),
      'hide_from_users' => 
      array (
        0 => 'stack_traces',
        1 => 'database_queries',
        2 => 'api_keys',
        3 => 'internal_paths',
        4 => 'system_architecture',
      ),
    ),
    'consistency' => 
    array (
      'terminology' => 
      array (
        'appointment' => 
        array (
          0 => 'booking',
          1 => 'schedule',
          2 => 'reservation',
        ),
        'cancel' => 
        array (
          0 => 'remove',
          1 => 'delete',
        ),
        'payment' => 
        array (
          0 => 'transaction',
          1 => 'fee',
          2 => 'charge',
        ),
        'refund' => 
        array (
          0 => 'return',
          1 => 'reimburse',
        ),
      ),
      'behavior' => 
      array (
        'always_professional' => true,
        'always_helpful' => true,
        'never_argumentative' => true,
        'consistent_formatting' => true,
        'never_condescending' => true,
        'respect_user_privacy' => true,
      ),
      'tone' => 
      array (
        'professional' => true,
        'friendly' => false,
        'neutral' => true,
        'respectful' => true,
        'empathetic_when_needed' => false,
      ),
    ),
    'dynamic_responses' => 
    array (
      'prohibited' => 
      array (
        'hardcoded_responses' => false,
        'static_decision_trees' => false,
        'scripted_answers' => false,
        'canned_responses' => false,
      ),
      'required_context' => 
      array (
        'real_time_data' => true,
        'user_role' => true,
        'system_state' => true,
        'conversation_history' => true,
      ),
      'characteristics' => 
      array (
        'personalized' => true,
        'data_driven' => true,
        'role_appropriate' => true,
        'context_aware' => true,
      ),
    ),
    'rate_limits' => 
    array (
      'enabled' => true,
      'messages_per_conversation' => 100,
      'conversations_per_hour' => 20,
      'cooldown_message' => 'You\'ve reached the message limit for this conversation. Please start a new conversation to continue.',
      'cooldown_message_filipino' => 'Naabot na po ninyo ang message limit. Magsimula po ng bagong conversation para makapag-patuloy.',
    ),
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
      2 => 'login',
      3 => 'logout',
    ),
    'allowed_methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
      2 => 'PUT',
      3 => 'DELETE',
      4 => 'PATCH',
      5 => 'OPTIONS',
    ),
    'allowed_origins' => 
    array (
      0 => 'http://localhost:3000',
      1 => 'http://localhost:3001',
      2 => 'http://localhost:5173',
      3 => 'http://127.0.0.1:5173',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => 'Content-Type',
      1 => 'Authorization',
      2 => 'Accept',
      3 => 'X-Requested-With',
    ),
    'exposed_headers' => 
    array (
      0 => 'Authorization',
      1 => 'X-Total-Count',
      2 => 'X-Page-Count',
    ),
    'max_age' => 3600,
    'supports_credentials' => true,
  ),
  'database' => 
  array (
    'default' => 'mysql',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'web2',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => NULL,
        'journal_mode' => NULL,
        'synchronous' => NULL,
        'transaction_mode' => 'DEFERRED',
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'web2',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'mariadb' => 
      array (
        'driver' => 'mariadb',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'web2',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'web2',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'web2',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'law-notary-system-database-',
        'persistent' => false,
      ),
      'default' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
      'cache' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
        'max_retries' => 3,
        'backoff_algorithm' => 'decorrelated_jitter',
        'backoff_base' => 100,
        'backoff_cap' => 1000,
      ),
    ),
  ),
  'features' => 
  array (
    'production' => 
    array (
      'authentication' => 
      array (
        'register' => true,
        'login' => true,
        'logout' => true,
        'password_reset' => true,
      ),
      'appointments' => 
      array (
        'create' => true,
        'list' => true,
        'view' => true,
        'update_status' => true,
        'cancel' => true,
        'daily_limit' => true,
        'slot_capacity' => true,
        'booking_rules' => true,
      ),
      'profile' => 
      array (
        'view' => true,
        'update' => true,
        'change_password' => true,
      ),
      'calendar' => 
      array (
        'view_availability' => true,
        'get_available_slots' => true,
        'unavailable_dates' => true,
      ),
      'messages' => 
      array (
        'send' => true,
        'receive' => true,
        'view' => true,
      ),
      'users' => 
      array (
        'list' => true,
        'create' => true,
        'update' => true,
        'delete' => true,
      ),
    ),
    'experimental' => 
    array (
      'analytics' => 
      array (
        'enabled' => false,
        'status' => 'EXPERIMENTAL',
        'routes' => 
        array (
          0 => 'dashboard',
          1 => 'slot_utilization',
          2 => 'no_show_patterns',
          3 => 'demand_forecast',
          4 => 'quality_report',
          5 => 'auto_alerts',
        ),
        'notes' => 'Dashboard built but no clear use case. Endpoints may return incomplete data.',
        'tested' => false,
      ),
      'decision_support' => 
      array (
        'enabled' => false,
        'status' => 'EXPERIMENTAL',
        'routes' => 
        array (
          0 => 'staff_recommendations',
          1 => 'time_slot_recommendations',
          2 => 'appointment_risk',
          3 => 'workload_optimization',
          4 => 'dashboard',
        ),
        'notes' => 'AI-like recommendations built but untested in production. May return inaccurate suggestions.',
        'tested' => false,
      ),
      'batch_operations' => 
      array (
        'enabled' => false,
        'status' => 'EXPERIMENTAL',
        'routes' => 
        array (
          0 => 'batch/dashboard',
          1 => 'batch/full-load',
        ),
        'notes' => 'Combines multiple API calls. May have performance issues with large datasets.',
        'tested' => false,
      ),
      'document_versioning' => 
      array (
        'enabled' => false,
        'status' => 'EXPERIMENTAL',
        'routes' => 
        array (
          0 => 'documents',
          1 => 'documents/{id}/versions',
        ),
        'notes' => 'Document upload works. Versioning and recovery untested.',
        'tested' => false,
      ),
      'auto_notifications' => 
      array (
        'enabled' => false,
        'status' => 'EXPERIMENTAL',
        'routes' => 
        array (
          0 => 'notifications',
          1 => 'notifications/preferences',
        ),
        'notes' => 'Notification system built but delivery method untested.',
        'tested' => false,
      ),
      'archive_system' => 
      array (
        'enabled' => false,
        'status' => 'EXPERIMENTAL',
        'routes' => 
        array (
          0 => 'archive',
          1 => 'archive/restore',
          2 => 'appointments/archived/list',
          3 => 'users/archived/list',
          4 => 'services/archived/list',
        ),
        'notes' => 'Soft delete archive built. Recovery process untested.',
        'tested' => false,
      ),
    ),
    'deprecated' => 
    array (
      'admin/unavailable-dates' => 
      array (
        'reason' => 'Duplicated by /admin/blackout-dates and /unavailable-dates',
        'replace_with' => '/admin/blackout-dates',
        'status' => 'DEPRECATED',
        'will_remove' => '2025-12-31',
      ),
      'admin/users' => 
      array (
        'reason' => 'Duplicated by /users endpoint with role filtering',
        'replace_with' => '/admin/users (without prefix)',
        'status' => 'DEPRECATED',
        'will_remove' => '2025-12-31',
      ),
      'admin/services' => 
      array (
        'reason' => 'Duplicated by /services endpoint with admin middleware',
        'replace_with' => '/services',
        'status' => 'DEPRECATED',
        'will_remove' => '2025-12-31',
      ),
    ),
    'planned' => 
    array (
      'video_consultations' => 
      array (
        'status' => 'PLANNED',
        'priority' => 'high',
        'notes' => 'Video call integration for remote consultations',
      ),
      'sms_notifications' => 
      array (
        'status' => 'PLANNED',
        'priority' => 'medium',
        'notes' => 'SMS reminders for appointments',
      ),
      'payment_processing' => 
      array (
        'status' => 'PLANNED',
        'priority' => 'high',
        'notes' => 'Accept online payments for services',
      ),
      'recurring_appointments' => 
      array (
        'status' => 'PLANNED',
        'priority' => 'medium',
        'notes' => 'Support for recurring appointments',
      ),
    ),
    'flags' => 
    array (
      'experimental_analytics' => false,
      'experimental_decision_support' => false,
      'experimental_batch' => false,
      'experimental_documents' => false,
      'experimental_notifications' => false,
      'experimental_archive' => false,
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'public',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\laragon\\www\\web\\web-backend\\storage\\app/private',
        'serve' => true,
        'throw' => false,
        'report' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\laragon\\www\\web\\web-backend\\storage\\app/public',
        'url' => 'http://localhost:8000/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
      ),
    ),
    'links' => 
    array (
      'C:\\laragon\\www\\web\\web-backend\\public\\storage' => 'C:\\laragon\\www\\web\\web-backend\\storage\\app/public',
    ),
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => 'C:\\laragon\\www\\web\\web-backend\\storage\\logs/laravel.log',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\laragon\\www\\web\\web-backend\\storage\\logs/laravel.log',
        'level' => 'debug',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'handler_with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'formatter' => NULL,
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
        'facility' => 8,
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => 'C:\\laragon\\www\\web\\web-backend\\storage\\logs/laravel.log',
      ),
      'security' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\laragon\\www\\web\\web-backend\\storage\\logs/security.log',
        'level' => 'debug',
        'days' => 90,
        'replace_placeholders' => true,
      ),
      'audit' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\laragon\\www\\web\\web-backend\\storage\\logs/audit.log',
        'level' => 'info',
        'days' => 365,
        'replace_placeholders' => true,
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'smtp',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'host' => 'smtp.gmail.com',
        'port' => '587',
        'encryption' => 'tls',
        'username' => 'christiannjc25@gmail.com',
        'password' => 'wlkhykvptqbxqneq',
        'timeout' => NULL,
        'local_domain' => NULL,
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => 'stack',
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
        'retry_after' => 60,
      ),
      'mailgun' => 
      array (
        'transport' => 'mailgun',
      ),
    ),
    'from' => 
    array (
      'address' => 'christiannjc25@gmail.com',
      'name' => 'Law Notary System',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => 'C:\\laragon\\www\\web\\web-backend\\resources\\views/vendor/mail',
      ),
    ),
  ),
  'permission' => 
  array (
    'models' => 
    array (
      'permission' => 'Spatie\\Permission\\Models\\Permission',
      'role' => 'Spatie\\Permission\\Models\\Role',
    ),
    'table_names' => 
    array (
      'roles' => 'roles',
      'permissions' => 'permissions',
      'model_has_permissions' => 'model_has_permissions',
      'model_has_roles' => 'model_has_roles',
      'role_has_permissions' => 'role_has_permissions',
    ),
    'column_names' => 
    array (
      'role_pivot_key' => NULL,
      'permission_pivot_key' => NULL,
      'model_morph_key' => 'model_id',
      'team_foreign_key' => 'team_id',
    ),
    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,
    'events_enabled' => false,
    'teams' => false,
    'team_resolver' => 'Spatie\\Permission\\DefaultTeamResolver',
    'use_passport_client_credentials' => false,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,
    'cache' => 
    array (
      'expiration_time' => 
      \DateInterval::__set_state(array(
         'from_string' => true,
         'date_string' => '24 hours',
      )),
      'key' => 'spatie.permission.cache',
      'store' => 'default',
    ),
  ),
  'queue' => 
  array (
    'default' => 'sync',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => NULL,
        'after_commit' => false,
      ),
    ),
    'batching' => 
    array (
      'database' => 'mysql',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'mysql',
      'table' => 'failed_jobs',
    ),
  ),
  'reverb' => 
  array (
    'default' => 'reverb',
    'servers' => 
    array (
      'reverb' => 
      array (
        'host' => '0.0.0.0',
        'port' => 8080,
        'path' => '',
        'hostname' => 'localhost',
        'options' => 
        array (
          'tls' => 
          array (
          ),
        ),
        'max_request_size' => 10000,
        'scaling' => 
        array (
          'enabled' => false,
          'channel' => 'reverb',
          'server' => 
          array (
            'url' => NULL,
            'host' => '127.0.0.1',
            'port' => '6379',
            'username' => NULL,
            'password' => NULL,
            'database' => '0',
            'timeout' => 60,
          ),
        ),
        'pulse_ingest_interval' => 15,
        'telescope_ingest_interval' => 15,
      ),
    ),
    'apps' => 
    array (
      'provider' => 'config',
      'apps' => 
      array (
        0 => 
        array (
          'key' => 'e0twx3nmhx1ud9wp1znl',
          'secret' => 'qt97kmmtssoiwbbgiwcp',
          'app_id' => '711682',
          'options' => 
          array (
            'host' => 'localhost',
            'port' => '8080',
            'scheme' => 'http',
            'useTLS' => false,
          ),
          'allowed_origins' => 
          array (
            0 => '*',
          ),
          'ping_interval' => 60,
          'activity_timeout' => 30,
          'max_connections' => NULL,
          'max_message_size' => 10000,
        ),
      ),
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => 'localhost:3000',
      2 => 'localhost:5173',
      3 => '127.0.0.1',
      4 => '127.0.0.1:8000',
      5 => '127.0.0.1:5173',
      6 => '::1',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'verify_csrf_token' => 'App\\Http\\Middleware\\VerifyCsrfToken',
      'encrypt_cookies' => 'App\\Http\\Middleware\\EncryptCookies',
    ),
  ),
  'security' => 
  array (
    'enabled_features' => 
    array (
      'rate_limiting' => true,
      'error_logging' => true,
      'request_monitoring' => true,
      'security_headers' => true,
      'cors_enforcement' => true,
    ),
    'rate_limiting' => 
    array (
      'default' => '60,1',
      'auth_attempts' => '5,15',
      'api_key' => '1000,1',
    ),
    'cors' => 
    array (
      'allowed_origins' => 
      array (
        0 => 'http://localhost:3000',
        1 => 'http://localhost:5173',
      ),
      'allowed_methods' => 
      array (
        0 => 'GET',
        1 => 'POST',
        2 => 'PUT',
        3 => 'DELETE',
        4 => 'PATCH',
        5 => 'OPTIONS',
      ),
      'allowed_headers' => 
      array (
        0 => 'Content-Type',
        1 => 'Authorization',
        2 => 'Accept',
        3 => 'X-Requested-With',
        4 => 'X-XSRF-TOKEN',
      ),
      'exposed_headers' => 
      array (
        0 => 'X-Total-Count',
        1 => 'X-Page-Number',
      ),
      'max_age' => 86400,
      'supports_credentials' => true,
    ),
    'session' => 
    array (
      'secure' => false,
      'http_only' => true,
      'same_site' => 'lax',
      'same_site_strict' => false,
    ),
    'password' => 
    array (
      'min_length' => 8,
      'require_uppercase' => true,
      'require_lowercase' => true,
      'require_numbers' => true,
      'require_special_chars' => false,
    ),
    'sensitive_fields' => 
    array (
      0 => 'password',
      1 => 'pin',
      2 => 'token',
      3 => 'api_key',
      4 => 'secret',
      5 => 'credit_card',
      6 => 'cvv',
      7 => 'ssn',
      8 => 'authorization',
      9 => 'x-api-key',
    ),
    'headers' => 
    array (
      'X-Frame-Options' => 'DENY',
      'X-Content-Type-Options' => 'nosniff',
      'X-XSS-Protection' => '1; mode=block',
      'Referrer-Policy' => 'strict-origin-when-cross-origin',
      'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
      'Strict-Transport-Security' => NULL,
    ),
    'debug' => 
    array (
      'enabled' => true,
      'show_errors_to_users' => false,
      'log_full_stack_traces' => true,
    ),
    'ip_whitelist' => 
    array (
      'enabled' => false,
      'ips' => 
      array (
        0 => '',
      ),
      'protected_endpoints' => 
      array (
        0 => '/admin/*',
        1 => '/api/admin/*',
      ),
    ),
    'account_lockout' => 
    array (
      'enabled' => true,
      'max_attempts' => 5,
      'lockout_duration_minutes' => 15,
    ),
    'audit_logging' => 
    array (
      'enabled' => true,
      'log_events' => 
      array (
        0 => 'user.login',
        1 => 'user.logout',
        2 => 'user.created',
        3 => 'user.deleted',
        4 => 'user.password_changed',
        5 => 'admin.action',
      ),
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'token' => NULL,
    ),
    'resend' => 
    array (
      'key' => NULL,
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => NULL,
        'channel' => NULL,
      ),
    ),
    'anthropic' => 
    array (
      'api_key' => NULL,
      'model' => 'claude-sonnet-4-20250514',
    ),
    'openai' => 
    array (
      'api_key' => NULL,
      'model' => 'gpt-4o',
    ),
    'mistral' => 
    array (
      'api_key' => NULL,
      'model' => 'mistral-large-latest',
    ),
    'ollama' => 
    array (
      'host' => 'http://localhost:11434',
      'model' => 'llama2',
    ),
    'huggingface' => 
    array (
      'api_key' => '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',
    ),
    'embedding' => 
    array (
      'provider' => 'openai',
      'model' => 'text-embedding-3-small',
    ),
    'voyage' => 
    array (
      'api_key' => NULL,
    ),
    'chatbot' => 
    array (
      'provider_order' => 'claude,openai,mistral,ollama',
      'default_personality' => 'professional',
      'max_context_messages' => 50,
      'enable_streaming' => true,
      'enable_rag' => true,
      'enable_memory' => true,
    ),
  ),
  'session' => 
  array (
    'driver' => 'cookie',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => 'C:\\laragon\\www\\web\\web-backend\\storage\\framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'law-notary-system-session',
    'path' => '/',
    'domain' => 'localhost',
    'secure' => NULL,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'resend' => 
  array (
    'api_key' => NULL,
    'domain' => NULL,
    'path' => 'resend',
    'webhook' => 
    array (
      'secret' => NULL,
      'tolerance' => 300,
    ),
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
  ),
);
