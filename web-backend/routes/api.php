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
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppointmentSettingsController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FeedbackSettingsController;
use App\Http\Controllers\Admin\FrontendErrorLogController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\ChatbotPositionController;
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
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is running!',
        'endpoints' => [
            'GET /api/test' => 'Basic API test',
            'GET /api/test-db' => 'Database connection test',
            'GET /api/services' => 'Get all active services',
            'GET /api/health' => 'Health check'
        ],
        'timestamp' => now()->toDateTimeString(),
        'version' => '1.0'
    ]);
});

// ==================== TEST ROUTES ====================
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working!',
        'timestamp' => now()->toDateTimeString(),
        'environment' => app()->environment()
    ]);
});

Route::get('/test-db', function () {
    try {
        \DB::connection()->getPdo();
        return response()->json([
            'status' => 'success',
            'message' => '✅ Database connected successfully!',
            'database' => \DB::connection()->getDatabaseName()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => '❌ Database connection failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

// ==================== PUBLIC ROUTES ====================

// Public routes
Route::post('/register-step1', [AuthController::class, 'registerStep1']);
Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/complete-registration', [AuthController::class, 'completeRegistration']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/resend-verification', [AuthController::class, 'resendVerificationCode']);
Route::get('/check-verification-status', [AuthController::class, 'checkVerificationStatus']);

// Health check route
Route::get('/health', [\App\Http\Controllers\HealthCheckController::class, 'check']);

// ==================== CRITICAL FIX: /services ROUTE ====================

// Public services endpoint - WORKING VERSION
Route::get('/services', function() {
    try {
        // Get active services from database
        $services = \App\Models\Service::where('is_active', true)->orderBy('name')->get();
        
        return response()->json([
            'data' => $services,
            'success' => true,
            'count' => count($services),
            'timestamp' => now()->toDateTimeString()
        ]);
    } catch (\Exception $e) {
        \Log::error('Services API Error: ' . $e->getMessage());
        
        return response()->json([
            'message' => 'Failed to fetch services',
            'success' => false
        ], 500);
    }
});

// ==================== OTHER PUBLIC ROUTES ====================

Route::get('/stats/summary', [StatsController::class, 'summary']);

// User-facing analytics (public for checking slot availability)
Route::get('/analytics/cancellation-risk', [AnalyticsController::class, 'cancellationRisk']);
Route::get('/analytics/alternative-slots', [AnalyticsController::class, 'alternativeSlots']);

// Public unavailable dates endpoint for clients (merged legacy + new blackout dates)
Route::get('/unavailable-dates', [UnavailableDateController::class, 'index']);
Route::get('/unavailable-dates/last-update', [UnavailableDateController::class, 'lastUpdate']);

// Real-time updates endpoints (public - no auth needed for polling)
Route::prefix('realtime')->group(function () {
    Route::get('/updates', [\App\Http\Controllers\RealtimeUpdateController::class, 'getUpdates']);
    Route::get('/slot-capacities', [\App\Http\Controllers\RealtimeUpdateController::class, 'getSlotCapacityData']);
    Route::get('/appointment-settings', [\App\Http\Controllers\RealtimeUpdateController::class, 'getAppointmentSettings']);
});

// Public appointment settings (for user booking limit checks)
Route::get('/appointment-settings/current', [AppointmentSettingsController::class, 'index']);
Route::get('/appointment-settings/user-limit/{userId}/{date?}', [AppointmentSettingsController::class, 'getUserLimit']);

// Public business information endpoint (for chatbot and public use)
Route::get('/business-info', function () {
    try {
        $settings = \App\Models\AppointmentSettings::first();
        $services = \App\Models\Service::where('is_active', true)->get(['id', 'name', 'description', 'price', 'duration_minutes']);
        $staff = \App\Models\User::where('is_active', true)
            ->whereIn('role', ['admin', 'staff', 'attorney', 'lawyer'])
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        return response()->json([
            'success' => true,
            'data' => [
                'location' => [
                    'business_hours' => $settings->business_hours ?? 'Not specified',
                    'address' => $settings->address ?? 'Not specified',
                    'phone' => $settings->phone ?? 'Not specified',
                    'timezone' => $settings->timezone ?? 'UTC'
                ],
                'services' => $services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'price' => $service->price,
                        'duration' => $service->duration_minutes
                    ];
                })->toArray(),
                'staff' => $staff->map(function ($person) {
                    return [
                        'name' => $person->first_name . ' ' . $person->last_name,
                        'email' => $person->email,
                        'phone' => $person->phone
                    ];
                })->toArray()
            ],
            'timestamp' => now()->toDateTimeString()
        ]);
    } catch (\Exception $e) {
        \Log::error('Business info endpoint error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch business information',
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
});

// Frontend error logging - SECURED with rate limiting and abuse detection
Route::post('/frontend-errors/log', [\App\Http\Controllers\Admin\FrontendErrorLogController::class, 'storePublic'])
    ->middleware(['throttle:30,1', 'abuse.detect']); // Rate limit: 30 requests per minute per user/IP + abuse detection

// ==================== TOKENIZED/SECURE ROUTES ====================

// Password reset with tokenized URL
Route::post('/password-reset-request', function (Request $request) {
    $validated = $request->validate([
        'email' => 'required|email|exists:users,email'
    ]);

    $user = \App\Models\User::where('email', $validated['email'])->first();
    $tokenData = \App\Services\TokenService::generateTokenizedUrl(
        $user->id,
        'password_reset',
        3600 // 1 hour
    );

    \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $tokenData) {
        $message->from(config('mail.from.address'))
                ->to($user->email)
                ->setBody("Reset password: {$tokenData['secure_url']}", 'text/html');
    });

    return response()->json([
        'message' => 'Password reset link sent to email',
        'user_uuid' => $user->uuid,
        'test_token_url' => $tokenData['secure_url'] // Only for testing, remove in production
    ]);
});

Route::post('/password-reset/{uuid}', function (Request $request, $uuid) {
    $validated = $request->validate([
        'token' => 'required|string',
        'password' => 'required|string|min:8|confirmed'
    ]);

    $result = \App\Services\TokenService::verifyTokenByUuid($uuid, $validated['token']);

    if (!$result || $result['purpose'] !== 'password_reset') {
        return response()->json(['error' => 'Invalid or expired token'], 401);
    }

    $result['user']->update(['password' => bcrypt($validated['password'])]);
    \App\Services\TokenService::revokeAllUserTokens($result['user']->id);

    return response()->json(['message' => 'Password reset successfully']);
})->middleware('throttle:5,1');

// Email verification with tokenized URL
Route::get('/verify-email/{uuid}', function ($uuid) {
    $result = \App\Services\TokenService::verifyTokenByUuid($uuid);

    if (!$result || $result['purpose'] !== 'email_verification') {
        return response()->json(['error' => 'Invalid or expired token'], 401);
    }

    $result['user']->update([
        'email_verified_at' => now(),
        'verification_code' => null
    ]);

    return response()->json(['message' => 'Email verified successfully']);
})->name('verify.email');

// Generate share link (7 days expiration)
Route::middleware('auth:sanctum')->post('/generate-share-token/{resourceType}/{resourceId}', function (Request $request, $resourceType, $resourceId) {
    $user = auth()->user();

    $tokenData = \App\Services\TokenService::generateTokenizedUrl(
        $user->id,
        "share_{$resourceType}",
        604800, // 7 days
        [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'created_by' => $user->uuid
        ]
    );

    return response()->json([
        'share_token' => $tokenData['token'],
        'share_url' => $tokenData['secure_url'],
        'expires_at' => $tokenData['expires_at'],
        'uuid' => $tokenData['uuid']
    ]);
});

// Access shared resource via token
Route::get('/shared-resource/{uuid}', function (Request $request, $uuid) {
    $token = $request->query('token');
    
    if (!$token) {
        return response()->json(['error' => 'Missing access token'], 401);
    }

    $result = \App\Services\TokenService::verifyTokenByUuid($uuid, $token);

    if (!$result || !str_starts_with($result['purpose'], 'share_')) {
        return response()->json(['error' => 'Invalid or expired share link'], 401);
    }

    return response()->json([
        'user' => \App\Services\TokenService::getSecureUserData($result['user']),
        'resource' => $result['metadata'],
        'token_data' => $result['token_data']
    ]);
});

// Public appointment-related endpoints
Route::get('/testimonials/completed-appointments', [AppointmentController::class, 'getCompletedAppointmentsPublic']);

// Public feedback endpoints
Route::post('/feedback', [FeedbackController::class, 'store']);
Route::get('/testimonials/feedbacks', [FeedbackController::class, 'getTestimonials']);
Route::get('/testimonials/feedbacks/all', [FeedbackController::class, 'getAllTestimonials']);

// ==================== PROTECTED ROUTES ====================

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Chatbot position persistence (per-user)
    Route::get('/chatbot/position', [ChatbotPositionController::class, 'show']);
    Route::post('/chatbot/position', [ChatbotPositionController::class, 'store']);

    // CASHIER ROUTES - For payment processing and cashier dashboard
    // SECURITY: Restricted to cashier, staff and admin roles only
    Route::prefix('cashier')->middleware(['role:cashier,staff,admin'])->group(function () {
        Route::get('/dashboard-stats', [CashierController::class, 'getDashboardStats']);
        Route::get('/appointments/approved', [CashierController::class, 'getApprovedAppointments']);
        Route::get('/appointments/completed', [CashierController::class, 'getCompletedAppointments']);
        Route::post('/appointments/{appointment}/email-receipt', [CashierController::class, 'sendReceiptEmail']);
        Route::post('/appointments/{appointment}/process-payment', [CashierController::class, 'processPayment']);
        Route::get('/calendar/appointments', [CashierController::class, 'getCalendarAppointments']);
        Route::get('/action-logs', [CashierController::class, 'getActionLogs']);
        Route::get('/profile', [CashierController::class, 'getProfile']);
        Route::put('/profile', [CashierController::class, 'updateProfile']);
        Route::get('/shift-reports', [CashierController::class, 'getShiftReport']);
        Route::post('/shift-reports/export', [CashierController::class, 'exportShiftReport']);
        
        // REFUND ROUTES - Request and view refunds
        Route::post('/refunds/request', [RefundController::class, 'requestRefund']);
        Route::get('/refunds/pending', [RefundController::class, 'getPendingRefunds']);
        Route::get('/appointments/{appointment}/refunds', [RefundController::class, 'getAppointmentRefunds']);
    });

    // ADMIN DASHBOARD ROUTES - UPDATED WITH ROLE FILTERING
    Route::prefix('admin')->middleware(['role:admin'])->group(function () {
        // ERROR LOG MANAGEMENT ROUTES - System monitoring and debugging
        Route::prefix('error-logs')->group(function () {
            Route::get('/', [ErrorLogController::class, 'index']);
            Route::get('/summary', [ErrorLogController::class, 'summary']);
            Route::get('/{id}', [ErrorLogController::class, 'show']);
            Route::post('/cleanup', [ErrorLogController::class, 'cleanup']);
            Route::post('/clear', [ErrorLogController::class, 'clear']);
        });

        // PERFORMANCE METRICS ROUTES - Request monitoring and analysis
        Route::prefix('metrics')->group(function () {
            Route::get('/dashboard', [MetricsController::class, 'dashboard']);
            Route::get('/endpoint', [MetricsController::class, 'endpoint']);
            Route::get('/slow-requests', [MetricsController::class, 'slowRequests']);
            Route::get('/errors', [MetricsController::class, 'errors']);
            Route::post('/cleanup', [MetricsController::class, 'cleanup']);
        });

        // ALERT MANAGEMENT ROUTES - System alerts and notifications
        Route::prefix('alerts')->group(function () {
            Route::get('/dashboard', [AlertController::class, 'dashboard']);
            Route::get('/', [AlertController::class, 'index']);
            Route::get('/{id}', [AlertController::class, 'show']);
            Route::post('/{id}/acknowledge', [AlertController::class, 'acknowledge']);
            Route::post('/acknowledge-multiple', [AlertController::class, 'acknowledgeMultiple']);
            
            // Alert Rules Management
            Route::get('/rules', [AlertController::class, 'rules']);
            Route::post('/rules', [AlertController::class, 'createRule']);
            Route::put('/rules/{id}', [AlertController::class, 'updateRule']);
            Route::delete('/rules/{id}', [AlertController::class, 'deleteRule']);
        });

        // BACKUP MANAGEMENT ROUTES - Database backups and restoration
        Route::prefix('backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/', [BackupController::class, 'create']);
            Route::post('/{backup}/restore', [BackupController::class, 'restore']);
            Route::delete('/{backup}', [BackupController::class, 'delete']);
            Route::post('/cleanup', [BackupController::class, 'cleanup']);
            Route::get('/statistics', [BackupController::class, 'statistics']);
        });

        // FRONTEND ERROR LOG ROUTES - Client-side error tracking
        Route::prefix('frontend-errors')->group(function () {
            Route::get('/', [FrontendErrorLogController::class, 'index']);
            Route::get('/{frontendErrorLog}', [FrontendErrorLogController::class, 'show']);
            Route::post('/{frontendErrorLog}/report', [FrontendErrorLogController::class, 'report']);
            Route::get('/stats', [FrontendErrorLogController::class, 'stats']);
            Route::post('/cleanup', [FrontendErrorLogController::class, 'cleanup']);
            Route::post('/bulk-report', [FrontendErrorLogController::class, 'bulkReport']);
        });

        // JOB MONITORING ROUTES - Background job tracking and management
        Route::prefix('jobs')->group(function () {
            Route::get('/dashboard', [JobController::class, 'dashboard']);
            Route::get('/', [JobController::class, 'index']);
            Route::get('/{job}', [JobController::class, 'show']);
            Route::get('/stats', [JobController::class, 'stats']);
            Route::get('/by-queue', [JobController::class, 'byQueue']);
            Route::get('/by-name', [JobController::class, 'byName']);
            Route::get('/slow-jobs', [JobController::class, 'slowJobs']);
            Route::get('/failed', [JobController::class, 'failedJobs']);
            Route::get('/stuck', [JobController::class, 'stuckJobs']);
            Route::post('/{job}/retry', [JobController::class, 'retry']);
            Route::post('/cleanup', [JobController::class, 'cleanup']);
        });

        // SYSTEM ADMINISTRATION ROUTES - Comprehensive admin dashboard
        Route::prefix('system')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Admin\SystemAdminController::class, 'dashboard']);
            Route::get('/health', [\App\Http\Controllers\Admin\SystemAdminController::class, 'health']);
            Route::get('/security-audit', [\App\Http\Controllers\Admin\SystemAdminController::class, 'securityAudit']);
            Route::post('/backup', [\App\Http\Controllers\Admin\SystemAdminController::class, 'runBackup']);
            Route::post('/clear-cache', [\App\Http\Controllers\Admin\SystemAdminController::class, 'clearCache']);
            Route::get('/users', [\App\Http\Controllers\Admin\SystemAdminController::class, 'listUsers']);
            Route::put('/users/{user}/role', [\App\Http\Controllers\Admin\SystemAdminController::class, 'updateUserRole']);
            Route::post('/users/{user}/toggle-status', [\App\Http\Controllers\Admin\SystemAdminController::class, 'toggleUserStatus']);
        });
        
        // Optimized admin stats
        Route::get('/stats/summary', [StatsController::class, 'summary']);
        Route::get('/stats', [StatsController::class, 'index']);
        
        // REFUND MANAGEMENT ROUTES - Admin approval and processing
        Route::prefix('refunds')->group(function () {
            Route::get('/stats', [RefundController::class, 'getRefundStats']);
            Route::get('/all', [RefundController::class, 'getAllRefunds']);
            Route::post('/{refund}/approve', [RefundController::class, 'approveRefund']);
            Route::post('/{refund}/reject', [RefundController::class, 'rejectRefund']);
            Route::post('/{refund}/complete', [RefundController::class, 'completeRefund']);
        });
        
        // ANALYTICS ROUTES - Smart Insights Dashboard
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('/slot-utilization', [AnalyticsController::class, 'slotUtilization']);
            Route::get('/no-show-patterns', [AnalyticsController::class, 'noShowPatterns']);
            Route::get('/demand-forecast', [AnalyticsController::class, 'demandForecast']);
            Route::get('/quality-report', [AnalyticsController::class, 'qualityReport']);
            Route::get('/auto-alerts', [AnalyticsController::class, 'autoAlerts']);
            Route::post('/clear-cache', [AnalyticsController::class, 'clearCache']);
        });
        
        // BATCH ENDPOINTS - Combine multiple API calls into one request
        Route::get('/batch/dashboard', [BatchController::class, 'dashboardData']);
        Route::get('/batch/full-load', [BatchController::class, 'fullDashboardLoad']);
        
        // Admin appointments endpoint
        Route::get('/appointments', [AdminController::class, 'getAllAppointments']);
        Route::get('/sales', [AdminController::class, 'getSalesData']);
        Route::post('/cancel-bulk-appointments', [AdminController::class, 'cancelBulkAppointments']);
        Route::post('/send-bulk-message', [AdminController::class, 'sendBulkMessage']);
        
        // Reports
        Route::post('/reports/generate', [AdminController::class, 'generateReport']);
        
        // Decision Support Actions
        Route::post('/reserve-suggested-slot', [AdminController::class, 'reserveSuggestedSlot']);
        Route::post('/assign-staff', [AdminController::class, 'assignStaff']);
        
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
            Route::post('/affected', [UnavailableDateController::class, 'getAffectedAppointments']); // Reuse the method from UnavailableDateController
            Route::put('/{blackoutDate}', [BlackoutDateController::class, 'update']);
            Route::delete('/{blackoutDate}', [BlackoutDateController::class, 'destroy']);
        });

        // Appointment Settings Management
        Route::prefix('appointment-settings')->group(function () {
            Route::get('/', [AppointmentSettingsController::class, 'index']);
            Route::put('/', [AppointmentSettingsController::class, 'update']);
            Route::get('/history', [AppointmentSettingsController::class, 'getHistory']);
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

        // Attorney management removed
    });

    // User management (Admin only) - Keep for backward compatibility
    Route::middleware(['role:admin'])->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        // Specific routes must come BEFORE generic {user} catch-all
        Route::get('/archived/list', [UserController::class, 'getArchived']);
        Route::put('/restore/{id}', [UserController::class, 'restore']);
        Route::delete('/permanent/{id}', [UserController::class, 'permanentDelete']);
        Route::put('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        // Generic routes last
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });

    // Profile routes (All authenticated users)
    Route::put('/profile', [UserController::class, 'updateProfile']);

    // NEW PROFILE ROUTES FOR USER DASHBOARD
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile/update', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Appointments - FIXED ROUTES (Static routes MUST come before wildcard routes)
    Route::prefix('appointments')->group(function () {
        // STATIC ROUTES FIRST - These must be defined before wildcard {appointment} routes
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::post('/suggest-alternative', [AppointmentController::class, 'suggestAlternative']);
        Route::get('/today', [AppointmentController::class, 'getTodayAppointments']);
        Route::get('/stats', [AppointmentController::class, 'getStats']);
        Route::get('/archived/list', [AppointmentController::class, 'getArchived']);
        Route::get('/my/appointments', [AppointmentController::class, 'userAppointments']);
        Route::get('/types/all', [AppointmentController::class, 'getTypes']);
        Route::get('/available-slots/{date}', [AppointmentController::class, 'availableSlots']);
        Route::delete('/permanent/{id}', [AppointmentController::class, 'permanentDelete']);
        
        // WILDCARD ROUTES LAST - These catch {appointment} parameter
        Route::get('/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/{appointment}/status', [AppointmentController::class, 'updateStatus']);
        Route::put('/{appointment}/approve', [AppointmentController::class, 'approve']);
        Route::put('/{appointment}/decline', [AppointmentController::class, 'decline']);
        Route::put('/{appointment}/complete', [AppointmentController::class, 'complete']);
        Route::put('/{appointment}/restore', [AppointmentController::class, 'restore']);
        Route::put('/{appointment}/assign-staff', [AppointmentController::class, 'assignStaff']);
        Route::put('/{id}/cancel', [AppointmentController::class, 'cancel']);
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy']);
    });

    // USER REFUND ROUTES - For users to request refunds
    Route::post('/refunds/request', [RefundController::class, 'requestRefund']);
    Route::get('/refunds/my', [RefundController::class, 'getUserRefunds']);
    Route::get('/refunds/{refund}', [RefundController::class, 'getRefund']);

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
        Route::get('/conversation/user/{userId}', [MessageController::class, 'conversation']);
        Route::post('/', [MessageController::class, 'store']);
        Route::delete('/conversation/{userId}', [MessageController::class, 'deleteConversation']);
        
        // NEW MESSAGE ROUTES FOR USER DASHBOARD
        Route::get('/staff/list', [MessageController::class, 'getStaff']);
        Route::get('/can-message/{userId}', [MessageController::class, 'canMessage']);
    });

    // UNAVAILABLE DATES ROUTES (Admin only)
    Route::prefix('admin/unavailable-dates')->middleware(['role:admin'])->group(function () {
        Route::get('/', [UnavailableDateController::class, 'index']);
        Route::post('/', [UnavailableDateController::class, 'store']);
        Route::post('/affected', [UnavailableDateController::class, 'getAffectedAppointments']);
        Route::delete('/{id}', [UnavailableDateController::class, 'destroy']);
    });

    // FEEDBACK ROUTES (Admin only)
    Route::prefix('admin/feedback')->middleware(['role:admin'])->group(function () {
        Route::get('/', [FeedbackController::class, 'index']);
        Route::get('/stats', [FeedbackController::class, 'getStats']);
        Route::get('/settings', [FeedbackSettingsController::class, 'show']);
        Route::put('/settings', [FeedbackSettingsController::class, 'update']);
        Route::get('/{id}', [FeedbackController::class, 'show']);
        Route::put('/{id}/testimonial', [FeedbackController::class, 'updateTestimonial']);
        Route::post('/{id}/report', [FeedbackController::class, 'reportFeedback']);
        Route::post('/{id}/block-user', [FeedbackController::class, 'blockUser']);
        Route::delete('/{id}', [FeedbackController::class, 'destroy']);
    });

    // USER FEEDBACK ROUTES (Authenticated users)
    Route::prefix('user/feedback')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [FeedbackController::class, 'getUserFeedback']);
        Route::post('/', [FeedbackController::class, 'store']);
        Route::get('/check-limit', [FeedbackController::class, 'checkRateLimit']);
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

    // APPOINTMENT SETTINGS ROUTES (User can check their limits)
    Route::prefix('appointment-settings')->group(function () {
        Route::get('/user-limit/{userId}/{date?}', [AppointmentSettingsController::class, 'getUserLimit']);
        Route::get('/can-book/{userId}', [AppointmentSettingsController::class, 'canUserBook']);
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

    // USER REFUNDS ROUTES - Users can view their own refund history
    Route::prefix('user')->group(function () {
        Route::get('/refunds', [RefundController::class, 'getUserRefunds']);
    });

    // SERVICES ROUTES (Public read, admin write) - COMMENTED OUT TEMPORARILY
    // Route::prefix('services')->group(function () {
    //     Route::get('/', [ServiceController::class, 'index']);
    //     Route::middleware(['role:admin'])->group(function () {
    //         Route::post('/', [ServiceController::class, 'store']);
    //         Route::put('/{service}', [ServiceController::class, 'update']);
    //         Route::delete('/{service}', [ServiceController::class, 'destroy']);
    //         Route::get('/archived/list', [ServiceController::class, 'getArchived']);
    //         Route::put('/{id}/restore', [ServiceController::class, 'restore']);
    //         Route::delete('/{id}/permanent', [ServiceController::class, 'permanentDelete']);
    //         Route::get('/stats', [ServiceController::class, 'getStats']);
    //         Route::post('/sync/appointments', [ServiceController::class, 'syncServicesFromAppointments']);
    //         Route::post('/sync/defaults', [ServiceController::class, 'syncDefaultAppointmentTypes']);
    //     });
    // });

    // Attorney endpoints removed

    // DECISION SUPPORT ROUTES (Staff and Admin)
    Route::prefix('decision-support')->middleware(['role:staff,admin'])->group(function () {
        Route::get('/staff-recommendations', [DecisionSupportController::class, 'getStaffRecommendations']);
        Route::get('/time-slot-recommendations', [DecisionSupportController::class, 'getTimeSlotRecommendations']);
        Route::get('/appointment-risk/{appointmentId}', [DecisionSupportController::class, 'getAppointmentRisk']);
        Route::get('/workload-optimization', [DecisionSupportController::class, 'getWorkloadOptimization']);
        Route::get('/dashboard', [DecisionSupportController::class, 'getDashboard']);
    });
});

// ==================== UNIFIED CHATBOT V2 ROUTES (LLM-First) ====================
// New unified chatbot endpoint that uses LLM as primary (not fallback)
Route::prefix('chatbot/v2')->group(function () {
    // Public endpoints
    Route::post('/send-message', [\App\Http\Controllers\UnifiedChatbotController::class, 'sendMessage']);
    Route::post('/stream', [\App\Http\Controllers\UnifiedChatbotController::class, 'streamMessage']);
    Route::get('/status', [\App\Http\Controllers\UnifiedChatbotController::class, 'getStatus']);
    Route::get('/history', [\App\Http\Controllers\UnifiedChatbotController::class, 'getHistory']);
    
    // Feedback endpoint (semi-public - works for guests and users)
    Route::post('/feedback', [\App\Http\Controllers\UnifiedChatbotController::class, 'submitFeedback']);
    
    // Admin endpoints
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/analytics', [\App\Http\Controllers\UnifiedChatbotController::class, 'getFeedbackAnalytics']);
    });
});

// CHATBOT ROUTES (PUBLIC - Allow guests and authenticated users) - LEGACY ENDPOINTS
Route::prefix('chatbot')->group(function () {
    // Public routes (guests can ask questions)
    Route::post('/send-message', [ChatbotController::class, 'sendMessage']);
    Route::get('/suggested-questions', [ChatbotController::class, 'getSuggestedQuestions']);
    Route::get('/rate-limit-status', [ChatbotController::class, 'getRateLimitStatus']);
    Route::get('/capabilities', [ChatbotController::class, 'getCapabilities']);
    
    // NEW: Streaming & Advanced AI routes
    Route::post('/stream', [\App\Http\Controllers\ChatbotStreamController::class, 'streamMessage']);
    Route::get('/status', [\App\Http\Controllers\ChatbotStreamController::class, 'getStatus']);
    Route::get('/suggestions', [\App\Http\Controllers\ChatbotStreamController::class, 'getSuggestions']);
    Route::post('/search-knowledge', [\App\Http\Controllers\ChatbotStreamController::class, 'searchKnowledge']);
    
    // Semi-public routes (work for both authenticated and guest users)
    Route::get('/history', [ChatbotController::class, 'getHistory']);
    Route::delete('/clear-history', [ChatbotController::class, 'clearHistory']);
    Route::get('/conversations', [ChatbotController::class, 'getConversations']);
    
    // Protected routes (authenticated users only)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/save-to-messages', [ChatbotController::class, 'saveMessageToMessageCenter']);
        Route::get('/conversation-summary', [ChatbotController::class, 'getConversationSummary']);
        
        // Action execution routes
        Route::post('/execute-action', [ChatbotController::class, 'executeAction']);
        Route::post('/confirm-action', [ChatbotController::class, 'confirmAction']);
        Route::post('/real-time-data', [ChatbotController::class, 'getRealTimeData']);
        
        // Conversation management routes (create, update, delete)
        Route::post('/conversations/new', [ChatbotController::class, 'startNewConversation']);
        Route::get('/conversations/{conversationId}', [ChatbotController::class, 'getConversationMessages']);
        Route::delete('/conversations/{conversationId}', [ChatbotController::class, 'deleteConversation']);
        
        // NEW: User preference management
        Route::post('/preferences', [\App\Http\Controllers\ChatbotStreamController::class, 'setPreference']);
    });
    
    // Admin-only analytics routes
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/analytics', [ChatbotController::class, 'getAnalytics']);
        Route::get('/priority-conversations', [ChatbotController::class, 'getPriorityConversations']);
        Route::get('/training-data', [ChatbotController::class, 'getTrainingData']);
    });
});

// CHATBOT ADVANCED FEATURES ROUTES (WebSocket, Workflows, Threading, Metrics)
Route::prefix('chatbot/advanced')->middleware(['auth:sanctum'])->group(function () {
    // WebSocket Management
    Route::post('/websocket/init', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'initializeWebSocket']);
    Route::post('/websocket/subscribe', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'subscribeToUpdates']);
    Route::get('/websocket/messages', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getPendingMessages']);
    Route::get('/websocket/stats', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getWebSocketStats']);

    // Workflow Orchestration
    Route::post('/workflow/execute', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'executeWorkflow']);
    Route::get('/workflow/available', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getAvailableWorkflows']);

    // Permission & Action Control
    Route::post('/permission/check', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'checkActionPermission']);
    Route::get('/permission/actions', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getPermittedActions']);

    // Conversation Threading
    Route::post('/thread/create', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'createThread']);
    Route::get('/thread/list', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getUserThreads']);
    Route::post('/thread/switch', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'switchThread']);
    Route::get('/thread/suggestions', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getConversationSuggestions']);

    // Metrics & Analytics
    Route::post('/metrics/satisfaction', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'recordSatisfaction']);
    Route::get('/metrics/quality', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getConversationQuality']);
    Route::get('/metrics/performance', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getPerformanceMetrics']);
    Route::get('/metrics/bottlenecks', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'identifyBottlenecks']);

    // Error Handling
    Route::get('/errors/summary', [\App\Http\Controllers\ChatbotAdvancedFeaturesController::class, 'getErrorSummary']);
});

// ==================== PHASE 3: INTELLIGENCE AT SCALE ====================

// Analytics Dashboard endpoints (Admin only)
Route::middleware(['auth:sanctum', 'admin'])->prefix('analytics')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\AnalyticsDashboardController::class, 'dashboard']);
    Route::get('/cpu', [App\Http\Controllers\AnalyticsDashboardController::class, 'cpuMetrics']);
    Route::get('/memory', [App\Http\Controllers\AnalyticsDashboardController::class, 'memoryMetrics']);
    Route::get('/disk', [App\Http\Controllers\AnalyticsDashboardController::class, 'diskMetrics']);
    Route::get('/health', [App\Http\Controllers\AnalyticsDashboardController::class, 'healthOverview']);
    Route::get('/trends', [App\Http\Controllers\AnalyticsDashboardController::class, 'trends']);
});

// Security & DDoS endpoints (Admin only)
Route::middleware(['auth:sanctum', 'admin'])->prefix('security')->group(function () {
    Route::get('/events', [\App\Http\Controllers\SecurityController::class, 'getSecurityEvents']);
    Route::get('/blocked-ips', [\App\Http\Controllers\SecurityController::class, 'getBlockedIps']);
    Route::post('/ip/block', [\App\Http\Controllers\SecurityController::class, 'blockIp']);
    Route::post('/ip/unblock', [\App\Http\Controllers\SecurityController::class, 'unblockIp']);
    Route::get('/summary', [\App\Http\Controllers\SecurityController::class, 'securitySummary']);
    Route::get('/rate-limit/{ip}', [\App\Http\Controllers\SecurityController::class, 'getRateLimit']);
    Route::post('/rate-limit/update', [\App\Http\Controllers\SecurityController::class, 'updateRateLimit']);
});

// Backup & Recovery endpoints (Admin only)
Route::middleware(['auth:sanctum', 'admin'])->prefix('backups')->group(function () {
    Route::get('/', [\App\Http\Controllers\BackupController::class, 'list']);
    Route::post('/create', [\App\Http\Controllers\BackupController::class, 'create']);
    Route::get('/{id}/verify', [\App\Http\Controllers\BackupController::class, 'verify']);
    Route::post('/{id}/restore', [\App\Http\Controllers\BackupController::class, 'restore']);
    Route::post('/{id}/test-restore', [\App\Http\Controllers\BackupController::class, 'testRestore']);
    Route::get('/{id}/recovery-plan', [\App\Http\Controllers\BackupController::class, 'recoveryPlan']);
    Route::get('/schedule/status', [\App\Http\Controllers\BackupController::class, 'scheduleStatus']);
    Route::post('/schedule/update', [\App\Http\Controllers\BackupController::class, 'updateSchedule']);
    Route::get('/statistics', [\App\Http\Controllers\BackupController::class, 'statistics']);
});

// Cleanup & Maintenance endpoints (Admin only)
Route::middleware(['auth:sanctum', 'admin'])->prefix('maintenance')->group(function () {
    Route::post('/cleanup', [\App\Http\Controllers\MaintenanceController::class, 'cleanup']);
    Route::post('/cleanup/logs', [\App\Http\Controllers\MaintenanceController::class, 'rotateLogs']);
    Route::post('/cleanup/cache', [\App\Http\Controllers\MaintenanceController::class, 'clearCache']);
    Route::post('/cleanup/old-backups', [\App\Http\Controllers\MaintenanceController::class, 'removeOldBackups']);
    Route::post('/cleanup/temp-files', [\App\Http\Controllers\MaintenanceController::class, 'cleanupTempFiles']);
    Route::post('/cleanup/sessions', [\App\Http\Controllers\MaintenanceController::class, 'cleanupSessions']);
    Route::get('/tasks/status', [\App\Http\Controllers\MaintenanceController::class, 'getTaskStatus']);
});

// System Health & Monitoring (Admin only, with optional public health endpoint)
Route::get('/health/public', [\App\Http\Controllers\HealthCheckController::class, 'publicCheck']);
Route::middleware(['auth:sanctum', 'admin'])->get('/health/detailed', [\App\Http\Controllers\HealthCheckController::class, 'detailedCheck']);

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'message' => 'API endpoint not found',
    ], 404);
});