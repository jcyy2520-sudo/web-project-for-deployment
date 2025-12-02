<?php

/**
 * FEATURE STATUS CONFIGURATION
 * 
 * Defines which features are production-ready vs experimental
 * Experimental features are wrapped with safety handlers
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Production Ready Features
    |--------------------------------------------------------------------------
    | These features are fully tested, documented, and safe for production
    |
    */
    'production' => [
        'authentication' => [
            'register' => true,
            'login' => true,
            'logout' => true,
            'password_reset' => true,
        ],
        'appointments' => [
            'create' => true,
            'list' => true,
            'view' => true,
            'update_status' => true,
            'cancel' => true,
            'daily_limit' => true,  // ✅ TESTED
            'slot_capacity' => true, // ✅ TESTED
            'booking_rules' => true, // ✅ TESTED
        ],
        'profile' => [
            'view' => true,
            'update' => true,
            'change_password' => true,
        ],
        'calendar' => [
            'view_availability' => true,
            'get_available_slots' => true,
            'unavailable_dates' => true,
        ],
        'messages' => [
            'send' => true,
            'receive' => true,
            'view' => true,
        ],
        'users' => [
            'list' => true,
            'create' => true,
            'update' => true,
            'delete' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Experimental Features
    |--------------------------------------------------------------------------
    | These features exist but need more testing or refinement
    | They are wrapped with safety handlers to prevent crashes
    |
    */
    'experimental' => [
        'analytics' => [
            'enabled' => false,
            'status' => 'EXPERIMENTAL',
            'routes' => [
                'dashboard',
                'slot_utilization',
                'no_show_patterns',
                'demand_forecast',
                'quality_report',
                'auto_alerts',
            ],
            'notes' => 'Dashboard built but no clear use case. Endpoints may return incomplete data.',
            'tested' => false,
        ],
        'decision_support' => [
            'enabled' => false,
            'status' => 'EXPERIMENTAL',
            'routes' => [
                'staff_recommendations',
                'time_slot_recommendations',
                'appointment_risk',
                'workload_optimization',
                'dashboard',
            ],
            'notes' => 'AI-like recommendations built but untested in production. May return inaccurate suggestions.',
            'tested' => false,
        ],
        'batch_operations' => [
            'enabled' => false,
            'status' => 'EXPERIMENTAL',
            'routes' => [
                'batch/dashboard',
                'batch/full-load',
            ],
            'notes' => 'Combines multiple API calls. May have performance issues with large datasets.',
            'tested' => false,
        ],
        'document_versioning' => [
            'enabled' => false,
            'status' => 'EXPERIMENTAL',
            'routes' => [
                'documents',
                'documents/{id}/versions',
            ],
            'notes' => 'Document upload works. Versioning and recovery untested.',
            'tested' => false,
        ],
        'auto_notifications' => [
            'enabled' => false,
            'status' => 'EXPERIMENTAL',
            'routes' => [
                'notifications',
                'notifications/preferences',
            ],
            'notes' => 'Notification system built but delivery method untested.',
            'tested' => false,
        ],
        'archive_system' => [
            'enabled' => false,
            'status' => 'EXPERIMENTAL',
            'routes' => [
                'archive',
                'archive/restore',
                'appointments/archived/list',
                'users/archived/list',
                'services/archived/list',
            ],
            'notes' => 'Soft delete archive built. Recovery process untested.',
            'tested' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deprecated/To Remove
    |--------------------------------------------------------------------------
    | These endpoints duplicate functionality and should be deprecated
    |
    */
    'deprecated' => [
        'admin/unavailable-dates' => [
            'reason' => 'Duplicated by /admin/blackout-dates and /unavailable-dates',
            'replace_with' => '/admin/blackout-dates',
            'status' => 'DEPRECATED',
            'will_remove' => '2025-12-31',
        ],
        'admin/users' => [
            'reason' => 'Duplicated by /users endpoint with role filtering',
            'replace_with' => '/admin/users (without prefix)',
            'status' => 'DEPRECATED',
            'will_remove' => '2025-12-31',
        ],
        'admin/services' => [
            'reason' => 'Duplicated by /services endpoint with admin middleware',
            'replace_with' => '/services',
            'status' => 'DEPRECATED',
            'will_remove' => '2025-12-31',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | TODO/Planned Features
    |--------------------------------------------------------------------------
    | These features are in the backlog but not yet implemented
    |
    */
    'planned' => [
        'video_consultations' => [
            'status' => 'PLANNED',
            'priority' => 'high',
            'notes' => 'Video call integration for remote consultations',
        ],
        'sms_notifications' => [
            'status' => 'PLANNED',
            'priority' => 'medium',
            'notes' => 'SMS reminders for appointments',
        ],
        'payment_processing' => [
            'status' => 'PLANNED',
            'priority' => 'high',
            'notes' => 'Accept online payments for services',
        ],
        'recurring_appointments' => [
            'status' => 'PLANNED',
            'priority' => 'medium',
            'notes' => 'Support for recurring appointments',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    | Enable/disable features without code changes
    |
    */
    'flags' => [
        'experimental_analytics' => env('FEATURE_ANALYTICS', false),
        'experimental_decision_support' => env('FEATURE_DECISION_SUPPORT', false),
        'experimental_batch' => env('FEATURE_BATCH', false),
        'experimental_documents' => env('FEATURE_DOCUMENTS', false),
        'experimental_notifications' => env('FEATURE_NOTIFICATIONS', false),
        'experimental_archive' => env('FEATURE_ARCHIVE', false),
    ],
];
