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
Route::post('/resend-verification', [AuthController::class, 'resendVerificationCode']);
Route::get('/check-verification-status', [AuthController::class, 'checkVerificationStatus']);

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

// User-facing analytics (public for checking slot availability)
Route::get('/analytics/cancellation-risk', [AnalyticsController::class, 'cancellationRisk']);
Route::get('/analytics/alternative-slots', [AnalyticsController::class, 'alternativeSlots']);

// Public unavailable dates endpoint for clients (merged legacy + new blackout dates)
Route::get('/unavailable-dates', [UnavailableDateController::class, 'index']);
Route::get('/unavailable-dates/last-update', [UnavailableDateController::class, 'lastUpdate']);

// Public appointment settings (for user booking limit checks)
Route::get('/appointment-settings/current', [AppointmentSettingsController::class, 'index']);
Route::get('/appointment-settings/user-limit/{userId}/{date?}', [AppointmentSettingsController::class, 'getUserLimit']);

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
        Route::post('/cancel-bulk-appointments', [AdminController::class, 'cancelBulkAppointments']);
        
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

    // CHATBOT ROUTES (All authenticated users)
    Route::prefix('chatbot')->group(function () {
        Route::get('/history', [ChatbotController::class, 'getHistory']);
        Route::post('/send-message', [ChatbotController::class, 'sendMessage']);
        Route::delete('/clear-history', [ChatbotController::class, 'clearHistory']);
        Route::get('/conversation-summary', [ChatbotController::class, 'getConversationSummary']);
    });
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'message' => 'API endpoint not found',
    ], 404);
});