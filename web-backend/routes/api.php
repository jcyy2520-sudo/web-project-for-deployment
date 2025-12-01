<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UnavailableDateController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ActionLogController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\DecisionSupportController;
use App\Http\Controllers\TimeSlotCapacityController;
use App\Http\Controllers\BlackoutDateController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register-step1', [AuthController::class, 'registerStep1']);
Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/complete-registration', [AuthController::class, 'completeRegistration']);
Route::post('/login', [AuthController::class, 'login']);
// Add to public routes
Route::post('/resend-verification', [AuthController::class, 'resendVerificationCode']);
Route::get('/check-verification-status', [AuthController::class, 'checkVerificationStatus']);

// Debug routes for email registration issues
Route::get('/debug-email/{email}', [AuthController::class, 'debugEmailStatus']);
Route::get('/debug-cache-clear', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        
        return response()->json([
            'status' => 'success',
            'message' => 'All caches cleared successfully',
            'commands_executed' => [
                'cache:clear',
                'config:clear', 
                'route:clear',
                'view:clear'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

// Debug route to check verification codes for an email
Route::get('/debug-verification-codes/{email}', function ($email) {
    try {
        $codes = VerificationCode::where('email', $email)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'email' => $email,
            'verification_codes_count' => $codes->count(),
            'verification_codes' => $codes->map(function($code) {
                return [
                    'id' => $code->id,
                    'code' => $code->code,
                    'used' => $code->used,
                    'expires_at' => $code->expires_at,
                    'created_at' => $code->created_at,
                    'is_valid' => !$code->used && $code->expires_at->isFuture(),
                    'minutes_until_expiry' => $code->expires_at->diffInMinutes(now())
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

// Test if email exists in database
Route::get('/debug-check-email/{email}', function ($email) {
    $userExists = \App\Models\User::where('email', $email)->exists();
    $verificationCodes = \App\Models\VerificationCode::where('email', $email)->count();
    
    return response()->json([
        'email' => $email,
        'user_exists' => $userExists,
        'verification_codes_count' => $verificationCodes,
        'can_register' => !$userExists
    ]);
});

// Test sending verification code directly
Route::post('/debug-send-code', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    
    // Clean up old codes
    \App\Models\VerificationCode::where('email', $request->email)->delete();
    
    // Create new code
    $code = sprintf("%06d", random_int(1, 999999));
    $verification = \App\Models\VerificationCode::create([
        'email' => $request->email,
        'code' => $code,
        'expires_at' => now()->addMinutes(30),
        'used' => false,
    ]);
    
    try {
        Mail::to($request->email)->send(new \App\Mail\VerificationCodeMail($code));
        return response()->json([
            'success' => true,
            'message' => 'Code sent successfully',
            'code' => $code, // Only for debugging - remove in production
            'email' => $request->email
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage()
        ], 500);
    }
});

// Test verification code directly
Route::post('/debug-verify-code', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'code' => 'required|string'
    ]);
    
    $verification = \App\Models\VerificationCode::where('email', $request->email)
        ->where('code', $request->code)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->first();
    
    if ($verification) {
        $verification->update(['used' => true]);
        return response()->json([
            'success' => true,
            'message' => 'Code verified successfully',
            'verified' => true
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Invalid or expired code',
        'verified' => false
    ], 422);
});

// Add this debug route to test the verification flow
Route::post('/debug-test-verification', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'code' => 'required|string'
    ]);
    
    Log::info('DEBUG VERIFICATION TEST', [
        'email' => $request->email,
        'code_attempted' => $request->code
    ]);
    
    $verification = VerificationCode::where('email', $request->email)
        ->where('code', $request->code)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->first();
    
    if ($verification) {
        Log::info('DEBUG: Code is VALID', ['verification_id' => $verification->id]);
        return response()->json([
            'success' => true,
            'message' => 'Code is valid',
            'verified' => true
        ]);
    }
    
    Log::warning('DEBUG: Code is INVALID', [
        'email' => $request->email,
        'available_codes' => VerificationCode::where('email', $request->email)->get()->pluck('code')
    ]);
    
    return response()->json([
        'success' => false,
        'message' => 'Code is invalid',
        'verified' => false
    ], 422);
});

// Test routes for debugging (remove in production)
Route::get('/test-email-sandbox', function () {
    try {
        $code = sprintf("%06d", mt_rand(1, 999999));
        Log::info('Sandbox email test with code: ' . $code);
        
        // Test with any email - will go to Mailtrap sandbox
        Mail::to('test@lawnotary.com')->send(new VerificationCodeMail($code));
        
        return response()->json([
            'status' => 'success',
            'message' => 'Sandbox email sent. Check Mailtrap Testing Inbox at: https://mailtrap.io/inboxes',
            'test_code' => $code,
            'instructions' => '1. Go to https://mailtrap.io 2. Login 3. Check "Demo Inbox"',
            'mail_config' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name')
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Sandbox email test failed: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'mail_config' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'password_set' => !empty(config('mail.mailers.smtp.password'))
            ]
        ], 500);
    }
});

Route::get('/test-email-live', function () {
    try {
        $code = sprintf("%06d", mt_rand(1, 999999));
        Log::info('Live email test with code: ' . $code);
        
        // Test with a real email address
        Mail::to('jcfajutagana3@gmail.com')->send(new VerificationCodeMail($code));
        
        return response()->json([
            'status' => 'success',
            'message' => 'Live email sent to jcfajutagana3@gmail.com. Check your Gmail inbox.',
            'test_code' => $code,
            'mail_config' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username')
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Live email test failed: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get('/test-email-log', function () {
    try {
        $code = sprintf("%06d", mt_rand(1, 999999));
        Log::info('Log email test with code: ' . $code);
        
        // This will log the email instead of sending it
        Mail::to('test@example.com')->send(new VerificationCodeMail($code));
        
        return response()->json([
            'status' => 'success',
            'message' => 'Email logged (not sent). Check Laravel logs for the verification code.',
            'test_code' => $code,
            'log_file' => storage_path('logs/laravel.log')
        ]);
    } catch (\Exception $e) {
        Log::error('Log email test failed: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/test-db', function () {
    try {
        // Test database connection
        DB::connection()->getPdo();
        
        // Test creating verification code
        $code = sprintf("%06d", mt_rand(1, 999999));
        $verification = VerificationCode::create([
            'email' => 'test@example.com',
            'code' => $code,
            'expires_at' => now()->addMinutes(30),
        ]);
        
        // Count verification codes
        $count = VerificationCode::count();
        
        // Get all tables
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map(function($table) {
            return array_values((array)$table)[0];
        }, $tables);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Database test passed',
            'verification_code_saved' => $code,
            'total_verification_codes' => $count,
            'verification_id' => $verification->id,
            'database_tables' => $tableNames,
            'database_name' => config('database.connections.mysql.database')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'database_config' => [
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'username' => config('database.connections.mysql.username')
            ]
        ], 500);
    }
});

Route::get('/debug-config', function () {
    return response()->json([
        'mail' => [
            'default' => config('mail.default'),
            'mailers' => [
                'smtp' => [
                    'transport' => config('mail.mailers.smtp.transport'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.encryption'),
                    'username' => config('mail.mailers.smtp.username'),
                    'password' => config('mail.mailers.smtp.password') ? '***' : 'empty',
                ]
            ],
            'from' => [
                'address' => config('mail.from.address'),
                'name' => config('mail.from.name')
            ]
        ],
        'database' => [
            'default' => config('database.default'),
            'connections' => [
                'mysql' => [
                    'host' => config('database.connections.mysql.host'),
                    'port' => config('database.connections.mysql.port'),
                    'database' => config('database.connections.mysql.database'),
                    'username' => config('database.connections.mysql.username'),
                    'password' => config('database.connections.mysql.password') ? '***' : 'empty',
                ]
            ]
        ],
        'app' => [
            'url' => config('app.url'),
            'env' => config('app.env'),
            'debug' => config('app.debug')
        ]
    ]);
});

Route::get('/test-registration-flow', function () {
    try {
        // Test the entire flow
        $testEmail = 'test-' . time() . '@example.com';
        $testUsername = 'testuser-' . time();
        $testCode = sprintf("%06d", mt_rand(1, 999999));
        
        Log::info('Testing registration flow for: ' . $testEmail);
        
        // Step 1: Create verification code
        VerificationCode::where('email', $testEmail)->delete();
        $verification = VerificationCode::create([
            'email' => $testEmail,
            'code' => $testCode,
            'expires_at' => now()->addMinutes(30),
        ]);
        
        // Step 2: Send email
        Mail::to($testEmail)->send(new VerificationCodeMail($testCode));
        
        return response()->json([
            'status' => 'success',
            'message' => 'Registration flow test completed',
            'test_data' => [
                'email' => $testEmail,
                'username' => $testUsername,
                'verification_code' => $testCode,
                'verification_id' => $verification->id
            ],
            'steps' => [
                '1. Verification code saved to database',
                '2. Email sent with verification code',
                '3. You can now test the registration form'
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('Registration flow test failed: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'suggestion' => 'Try using MAIL_MAILER=log in your .env file for development'
        ], 500);
    }
});

Route::get('/check-logs', function () {
    try {
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $lines = explode("\n", $content);
            $recentLines = array_slice($lines, -50); // Last 50 lines
            return response()->json([
                'status' => 'success',
                'recent_logs' => implode("\n", $recentLines)
            ]);
        }
        return response()->json([
            'status' => 'error',
            'message' => 'No log file found at: ' . $logFile
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/clear-logs', function () {
    try {
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            return response()->json([
                'status' => 'success',
                'message' => 'Logs cleared successfully'
            ]);
        }
        return response()->json([
            'status' => 'error',
            'message' => 'No log file found'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toDateTimeString(),
        'services' => [
            'database' => 'connected',
            'mail' => config('mail.default'),
            'session' => config('session.driver')
        ]
    ]);
});

// Public routes for landing page
Route::get('/services', [ServiceController::class, 'allServices']);
Route::get('/stats/summary', [StatsController::class, 'summary']);

// Public unavailable dates endpoint for clients (merged legacy + new blackout dates)
Route::get('/unavailable-dates', [UnavailableDateController::class, 'index']);
Route::get('/unavailable-dates/last-update', [UnavailableDateController::class, 'lastUpdate']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // ADMIN DASHBOARD ROUTES - UPDATED WITH ROLE FILTERING
    Route::prefix('admin')->middleware(['role:admin'])->group(function () {
        // Optimized admin stats
        Route::get('/stats/summary', [StatsController::class, 'summary']);
        Route::get('/stats', [StatsController::class, 'index']);
        
        // BATCH ENDPOINTS - Combine multiple API calls into one request
        Route::get('/batch/dashboard', [BatchController::class, 'dashboardData']);
        Route::get('/batch/full-load', [BatchController::class, 'fullDashboardLoad']);
        
        // Admin appointments endpoint
        Route::get('/appointments', [AdminController::class, 'getAllAppointments']);
        
        // Reports
        Route::post('/reports/generate', [AdminController::class, 'generateReport']);
        
        // User management with role filtering
        Route::get('/users', [UserController::class, 'getUsersByRole']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        
        // Unavailable dates
        Route::get('/unavailable-dates', [UnavailableDateController::class, 'index']);
        Route::post('/unavailable-dates', [UnavailableDateController::class, 'store']);
        Route::delete('/unavailable-dates/{id}', [UnavailableDateController::class, 'destroy']);
        
        // Time Slot Capacity Management
        Route::prefix('slot-capacities')->group(function () {
            Route::get('/', [TimeSlotCapacityController::class, 'index']);
            Route::post('/apply-all', [TimeSlotCapacityController::class, 'applyAll']);
            Route::post('/', [TimeSlotCapacityController::class, 'store']);
            Route::put('/{timeSlotCapacity}', [TimeSlotCapacityController::class, 'update']);
            Route::delete('/{timeSlotCapacity}', [TimeSlotCapacityController::class, 'destroy']);
            Route::get('/summary', [TimeSlotCapacityController::class, 'getCapacitySummary']);
        });
        
        // Blackout Dates Management
        Route::prefix('blackout-dates')->group(function () {
            Route::get('/', [BlackoutDateController::class, 'index']);
            Route::post('/', [BlackoutDateController::class, 'store']);
            Route::put('/{blackoutDate}', [BlackoutDateController::class, 'update']);
            Route::delete('/{blackoutDate}', [BlackoutDateController::class, 'destroy']);
        });
        
        // Services management
        Route::get('/services', [ServiceController::class, 'adminServices']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
        Route::get('/services/archived/list', [ServiceController::class, 'getArchived']);
        Route::put('/services/{id}/restore', [ServiceController::class, 'restore']);
        Route::delete('/services/{id}/permanent', [ServiceController::class, 'permanentDelete']);
        Route::get('/services/stats', [ServiceController::class, 'getStats']);
        Route::post('/services/sync/appointments', [ServiceController::class, 'syncServicesFromAppointments']);
        Route::post('/services/sync/defaults', [ServiceController::class, 'syncDefaultAppointmentTypes']);
        
        // Admin message sending
        Route::post('/send-message', [AdminController::class, 'sendMessage']);
    });

    // User management (Admin only) - Keep for backward compatibility
    Route::middleware(['role:admin'])->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::put('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::get('/archived/list', [UserController::class, 'getArchived']);
        Route::put('/restore/{id}', [UserController::class, 'restore']);
        Route::delete('/permanent/{id}', [UserController::class, 'permanentDelete']);
    });

    // Profile routes (All authenticated users)
    Route::put('/profile', [UserController::class, 'updateProfile']);

    // NEW PROFILE ROUTES FOR USER DASHBOARD
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile/update', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Appointments - FIXED ROUTES
    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::post('/suggest-alternative', [AppointmentController::class, 'suggestAlternative']);
        Route::get('/today', [AppointmentController::class, 'getTodayAppointments']);
        Route::get('/stats', [AppointmentController::class, 'getStats']);
        Route::get('/archived/list', [AppointmentController::class, 'getArchived']);
        Route::get('/{appointment}', [AppointmentController::class, 'show']);
        
        // FIXED: Use PUT for status updates and specific approval/decline routes
        Route::put('/{appointment}/status', [AppointmentController::class, 'updateStatus']);
        Route::put('/{appointment}/approve', [AppointmentController::class, 'approve']);
        Route::put('/{appointment}/decline', [AppointmentController::class, 'decline']);
        Route::put('/{appointment}/complete', [AppointmentController::class, 'complete']);
        Route::put('/{appointment}/restore', [AppointmentController::class, 'restore']);
        
        Route::put('/{appointment}/assign-staff', [AppointmentController::class, 'assignStaff']);
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy']);
        Route::delete('/permanent/{id}', [AppointmentController::class, 'permanentDelete']);
        
        // NEW USER APPOINTMENT ROUTES
        Route::get('/my/appointments', [AppointmentController::class, 'userAppointments']);
        Route::put('/{id}/cancel', [AppointmentController::class, 'cancel']);
        Route::get('/available-slots/{date}', [AppointmentController::class, 'availableSlots']);
        Route::get('/types/all', [AppointmentController::class, 'getTypes']);
    });

    // Calendar
    Route::prefix('calendar')->group(function () {
        Route::get('/', [CalendarController::class, 'index']);
        Route::get('/available-slots', [CalendarController::class, 'getAvailableSlots']);
        Route::get('/unavailable-dates', [CalendarController::class, 'getUnavailableDates']); // Public - for clients
        Route::get('/slot-capacities', [CalendarController::class, 'getSlotCapacities']); // Public - for clients
        Route::post('/', [CalendarController::class, 'store'])->middleware(['role:admin,staff']);
        Route::put('/{calendarEvent}', [CalendarController::class, 'update'])->middleware(['role:admin,staff']);
        Route::delete('/{calendarEvent}', [CalendarController::class, 'destroy'])->middleware(['role:admin,staff']);
    });

    // Messages
    Route::prefix('messages')->group(function () {
        Route::get('/', [MessageController::class, 'index']);
        Route::get('/all/messages', [MessageController::class, 'getAllMessages']);
        Route::get('/users', [MessageController::class, 'getUsers']);
        Route::get('/conversation/{otherUser}', [MessageController::class, 'show']);
        Route::post('/', [MessageController::class, 'store']);
        Route::delete('/conversation/{userId}', [MessageController::class, 'deleteConversation']);
        
        // NEW MESSAGE ROUTES FOR USER DASHBOARD
        Route::get('/staff/list', [MessageController::class, 'getStaff']);
        Route::get('/conversation/user/{userId}', [MessageController::class, 'conversation']);
        Route::get('/can-message/{userId}', [MessageController::class, 'canMessage']);
    });

    // UNAVAILABLE DATES ROUTES (Admin only)
    Route::prefix('admin/unavailable-dates')->middleware(['role:admin'])->group(function () {
        Route::get('/', [UnavailableDateController::class, 'index']);
        Route::post('/', [UnavailableDateController::class, 'store']);
        Route::delete('/{id}', [UnavailableDateController::class, 'destroy']);
    });

    // STAFF APPOINTMENTS ROUTES
    Route::middleware(['role:staff,admin'])->get('/staff/appointments', [AppointmentController::class, 'staffAppointments']);

    // ARCHIVE ROUTES
    Route::prefix('archive')->middleware(['role:admin'])->group(function () {
        Route::get('/', [ArchiveController::class, 'index']);
        Route::post('/restore', [ArchiveController::class, 'restore']);
        Route::delete('/{id}', [ArchiveController::class, 'destroy']);
    });

    // NOTIFICATIONS ROUTES
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::put('/{id}/unread', [NotificationController::class, 'markAsUnread']);
        Route::delete('/{id}', [NotificationController::class, 'delete']);
        Route::delete('/', [NotificationController::class, 'deleteAll']);
        
        // Preferences
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
    });

    // DOCUMENTS ROUTES
    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentController::class, 'index']);
        Route::post('/', [DocumentController::class, 'store']);
        Route::get('/{id}', [DocumentController::class, 'show']);
        Route::get('/{id}/download', [DocumentController::class, 'download']);
        Route::delete('/{id}', [DocumentController::class, 'delete']);
        Route::get('/{id}/versions', [DocumentController::class, 'getVersions']);
    });

    // AUDIT LOGS ROUTES (Admin only)
    Route::prefix('audit-logs')->middleware(['role:admin'])->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
        Route::get('/user/{userId}/activity', [AuditLogController::class, 'getUserActivityReport']);
        Route::get('/report/security', [AuditLogController::class, 'securityReport']);
    });

    // ACTION LOGS ROUTES
    Route::prefix('action-logs')->group(function () {
        // All users can see their own action logs
        Route::get('/my/logs', [ActionLogController::class, 'userLogs']);
        Route::get('/stats', [ActionLogController::class, 'getStats']);
        
        // Admin can see all action logs
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/', [ActionLogController::class, 'adminLogs']);
        });
    });

    // SERVICES ROUTES (Public read, admin write)
    Route::prefix('services')->group(function () {
        Route::get('/', [ServiceController::class, 'index']);
        Route::middleware(['role:admin'])->group(function () {
            Route::post('/', [ServiceController::class, 'store']);
            Route::put('/{service}', [ServiceController::class, 'update']);
            Route::delete('/{service}', [ServiceController::class, 'destroy']);
            Route::get('/archived/list', [ServiceController::class, 'getArchived']);
            Route::put('/{id}/restore', [ServiceController::class, 'restore']);
            Route::delete('/{id}/permanent', [ServiceController::class, 'permanentDelete']);
            Route::get('/stats', [ServiceController::class, 'getStats']);
            Route::post('/sync/appointments', [ServiceController::class, 'syncServicesFromAppointments']);
            Route::post('/sync/defaults', [ServiceController::class, 'syncDefaultAppointmentTypes']);
        });
    });

    // DECISION SUPPORT ROUTES (Staff and Admin)
    Route::prefix('decision-support')->middleware(['role:staff,admin'])->group(function () {
        Route::get('/staff-recommendations', [DecisionSupportController::class, 'getStaffRecommendations']);
        Route::get('/time-slot-recommendations', [DecisionSupportController::class, 'getTimeSlotRecommendations']);
        Route::get('/appointment-risk/{appointmentId}', [DecisionSupportController::class, 'getAppointmentRisk']);
        Route::get('/workload-optimization', [DecisionSupportController::class, 'getWorkloadOptimization']);
        Route::get('/dashboard', [DecisionSupportController::class, 'getDashboard']);
    });
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'POST /api/register-step1',
            'POST /api/verify-code', 
            'POST /api/complete-registration',
            'POST /api/login',
            'POST /api/resend-verification',
            'GET /api/check-verification-status',
            'GET /api/debug-email/{email}',
            'GET /api/debug-verification-codes/{email}',
            'GET /api/debug-check-email/{email}',
            'POST /api/debug-send-code',
            'POST /api/debug-verify-code',
            'POST /api/debug-test-verification',
            'GET /api/debug-cache-clear',
            'GET /api/test-email-sandbox',
            'GET /api/test-email-live',
            'GET /api/test-email-log',
            'GET /api/test-db',
            'GET /api/debug-config',
            'GET /api/test-registration-flow',
            'GET /api/health',
            'GET /api/check-logs',
            'GET /api/clear-logs'
        ]
    ], 404);
});