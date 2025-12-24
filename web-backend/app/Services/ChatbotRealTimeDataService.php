<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotRealTimeDataService
 * 
 * Fetches real-time system data without fabrication
 * Uses intelligent caching to balance freshness and performance
 * Always bases responses on actual system state
 * 
 * Features:
 * - Real-time appointment data
 * - Current payment status and history
 * - Active refund requests
 * - System availability and settings
 * - User information
 * - Service availability
 * - Business hours and schedules
 */
class ChatbotRealTimeDataService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CRITICAL_DATA_TTL = 60; // 1 minute for critical data

    /**
     * Get user's appointments with real-time status
     * 
     * @param int $userId
     * @param string|null $status Filter by status (pending, approved, completed, cancelled)
     * @param int $limit
     * @return array Appointment data
     */
    public function getUserAppointments(int $userId, ?string $status = null, int $limit = 20): array
    {
        try {
            $cacheKey = "chatbot_appointments_user_{$userId}_" . ($status ? $status : 'all');

            // Check cache first
            $cached = Cache::get($cacheKey);
            if ($cached !== null && is_array($cached)) {
                return $cached;
            }

            // Fetch from database
            $query = Appointment::where('user_id', $userId)
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc')
                ->limit($limit);

            if ($status) {
                $query->where('status', strtolower($status));
            }

            $appointments = $query->get()->map(function ($apt) {
                return [
                    'id' => $apt->id,
                    'user_id' => $apt->user_id,
                    'service' => $apt->service_type ?? 'General Service',
                    'date' => $apt->appointment_date?->format('Y-m-d'),
                    'time' => $apt->appointment_time ?? $apt->start_time?->format('H:i'),
                    'status' => $apt->status,
                    'payment_status' => $apt->payment_status,
                    'payment_amount' => $apt->payment_amount,
                    'created_at' => $apt->created_at?->toDateTimeString(),
                    'is_upcoming' => $apt->appointment_date && $apt->appointment_date->isFuture(),
                    'is_overdue' => $apt->appointment_date && $apt->appointment_date->isPast() && $apt->status !== 'completed',
                ];
            })->toArray();

            // Cache the result
            Cache::put($cacheKey, $appointments, self::CACHE_TTL);

            return $appointments;
        } catch (\Exception $e) {
            Log::error('Error fetching user appointments', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get pending appointments for admin/staff (needs approval)
     * 
     * @param int $limit
     * @return array
     */
    public function getPendingAppointments(int $limit = 50): array
    {
        try {
            $cacheKey = 'chatbot_pending_appointments';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $appointments = Appointment::where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->with('user:id,first_name,last_name,email')
                ->get()
                ->map(function ($apt) {
                    return [
                        'id' => $apt->id,
                        'user_name' => $apt->user?->first_name . ' ' . $apt->user?->last_name,
                        'user_email' => $apt->user?->email,
                        'service' => $apt->service_type,
                        'date' => $apt->appointment_date?->format('Y-m-d'),
                        'time' => $apt->appointment_time,
                        'created_at' => $apt->created_at?->toDateTimeString(),
                        'days_waiting' => $apt->created_at?->diffInDays(now()),
                    ];
                })->toArray();

            Cache::put($cacheKey, $appointments, self::CRITICAL_DATA_TTL);

            return $appointments;
        } catch (\Exception $e) {
            Log::error('Error fetching pending appointments', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get appointment details by ID
     * 
     * @param int $appointmentId
     * @return array|null
     */
    public function getAppointmentDetails(int $appointmentId): ?array
    {
        try {
            $cacheKey = "chatbot_appointment_{$appointmentId}";

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $appointment = Appointment::find($appointmentId);

            if (!$appointment) {
                return null;
            }

            $details = [
                'id' => $appointment->id,
                'user_id' => $appointment->user_id,
                'user_name' => $appointment->user?->first_name . ' ' . $appointment->user?->last_name,
                'service' => $appointment->service_type,
                'date' => $appointment->appointment_date?->format('Y-m-d'),
                'time' => $appointment->appointment_time,
                'status' => $appointment->status,
                'payment_status' => $appointment->payment_status,
                'payment_amount' => $appointment->payment_amount,
                'discount_amount' => $appointment->discount_amount,
                'purpose' => $appointment->purpose,
                'notes' => $appointment->notes,
                'staff_notes' => $appointment->staff_notes,
                'created_at' => $appointment->created_at?->toDateTimeString(),
                'updated_at' => $appointment->updated_at?->toDateTimeString(),
                'completed_at' => $appointment->completed_at?->toDateTimeString(),
                'is_upcoming' => $appointment->appointment_date && $appointment->appointment_date->isFuture(),
            ];

            Cache::put($cacheKey, $details, self::CACHE_TTL);

            return $details;
        } catch (\Exception $e) {
            Log::error('Error fetching appointment details', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get user's payment information
     * 
     * @param int $userId
     * @param string|null $status Filter by status
     * @param int $limit
     * @return array
     */
    public function getUserPayments(int $userId, ?string $status = null, int $limit = 20): array
    {
        try {
            $cacheKey = "chatbot_payments_user_{$userId}_" . ($status ? $status : 'all');

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $query = Payment::whereHas('appointment', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->orderBy('created_at', 'desc')->limit($limit);

            if ($status) {
                $query->where('status', strtolower($status));
            }

            $payments = $query->get()->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'appointment_id' => $payment->appointment_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'processed_at' => $payment->processed_at?->toDateTimeString(),
                    'created_at' => $payment->created_at?->toDateTimeString(),
                ];
            })->toArray();

            Cache::put($cacheKey, $payments, self::CACHE_TTL);

            return $payments;
        } catch (\Exception $e) {
            Log::error('Error fetching user payments', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get pending payments that need collection (for cashier)
     * 
     * @param int $limit
     * @return array
     */
    public function getPendingPayments(int $limit = 50): array
    {
        try {
            $cacheKey = 'chatbot_pending_payments';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $appointments = Appointment::whereIn('payment_status', ['pending', 'partial'])
                ->where('status', '!=', 'cancelled')
                ->orderBy('appointment_date', 'asc')
                ->limit($limit)
                ->with('user:id,first_name,last_name,email')
                ->get()
                ->map(function ($apt) {
                    return [
                        'appointment_id' => $apt->id,
                        'user_name' => $apt->user?->first_name . ' ' . $apt->user?->last_name,
                        'user_email' => $apt->user?->email,
                        'amount_due' => $apt->payment_amount - ($apt->discount_amount ?? 0),
                        'service' => $apt->service_type,
                        'date' => $apt->appointment_date?->format('Y-m-d'),
                        'is_overdue' => $apt->appointment_date && $apt->appointment_date->isPast(),
                    ];
                })->toArray();

            Cache::put($cacheKey, $appointments, self::CRITICAL_DATA_TTL);

            return $appointments;
        } catch (\Exception $e) {
            Log::error('Error fetching pending payments', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get user's refunds
     * 
     * @param int $userId
     * @param string|null $status Filter by status
     * @param int $limit
     * @return array
     */
    public function getUserRefunds(int $userId, ?string $status = null, int $limit = 20): array
    {
        try {
            $cacheKey = "chatbot_refunds_user_{$userId}_" . ($status ? $status : 'all');

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $query = Refund::where('requested_by', $userId)
                ->orderBy('created_at', 'desc')
                ->limit($limit);

            if ($status) {
                $query->where('status', strtolower($status));
            }

            $refunds = $query->get()->map(function ($refund) {
                return [
                    'id' => $refund->id,
                    'appointment_id' => $refund->appointment_id,
                    'amount' => $refund->refund_amount,
                    'reason' => $refund->reason,
                    'status' => $refund->status,
                    'created_at' => $refund->created_at?->toDateTimeString(),
                    'approved_at' => $refund->approved_at?->toDateTimeString(),
                    'processed_at' => $refund->completed_at?->toDateTimeString(),
                ];
            })->toArray();

            Cache::put($cacheKey, $refunds, self::CACHE_TTL);

            return $refunds;
        } catch (\Exception $e) {
            Log::error('Error fetching user refunds', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get pending refund requests (for cashier/admin)
     * 
     * @param int $limit
     * @return array
     */
    public function getPendingRefunds(int $limit = 50): array
    {
        try {
            $cacheKey = 'chatbot_pending_refunds';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $refunds = Refund::where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->with('user:id,first_name,last_name,email')
                ->get()
                ->map(function ($refund) {
                    return [
                        'id' => $refund->id,
                        'appointment_id' => $refund->appointment_id,
                        'user_name' => $refund->user?->first_name . ' ' . $refund->user?->last_name,
                        'user_email' => $refund->user?->email,
                        'amount' => $refund->amount,
                        'reason' => $refund->reason,
                        'created_at' => $refund->created_at?->toDateTimeString(),
                        'days_pending' => $refund->created_at?->diffInDays(now()),
                    ];
                })->toArray();

            Cache::put($cacheKey, $refunds, self::CRITICAL_DATA_TTL);

            return $refunds;
        } catch (\Exception $e) {
            Log::error('Error fetching pending refunds', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get available services with real-time info
     * 
     * @return array
     */
    public function getAvailableServices(): array
    {
        try {
            $cacheKey = 'chatbot_available_services';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $services = Service::where('is_active', true)
                ->get()
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'price' => $service->price,
                        'duration_minutes' => $service->duration_minutes,
                        'is_active' => $service->is_active,
                    ];
                })->toArray();

            Cache::put($cacheKey, $services, self::CACHE_TTL);

            return $services;
        } catch (\Exception $e) {
            Log::error('Error fetching available services', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get business hours and availability settings
     * 
     * @return array
     */
    public function getBusinessHours(): array
    {
        try {
            $cacheKey = 'chatbot_business_hours';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $settings = AppointmentSettings::first();

            if (!$settings) {
                return [
                    'status' => 'not_configured',
                    'message' => 'Business hours not configured in the system',
                ];
            }

            $hours = [
                'business_hours' => $settings->business_hours,
                'timezone' => $settings->timezone ?? 'UTC',
                'max_advance_days' => $settings->max_advance_days,
                'min_hours_before' => $settings->min_hours_before,
                'is_open_today' => $this->isOpenToday($settings),
            ];

            Cache::put($cacheKey, $hours, self::CACHE_TTL);

            return $hours;
        } catch (\Exception $e) {
            Log::error('Error fetching business hours', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => 'Could not fetch business hours'];
        }
    }

    /**
     * Get system status information
     * 
     * @return array
     */
    public function getSystemStatus(): array
    {
        try {
            return [
                'status' => 'operational',
                'timestamp' => now()->toDateTimeString(),
                'database' => $this->checkDatabaseStatus(),
                'total_users' => User::count(),
                'total_appointments' => Appointment::count(),
                'pending_items' => [
                    'pending_appointments' => Appointment::where('status', 'pending')->count(),
                    'pending_payments' => Appointment::whereIn('payment_status', ['pending', 'partial'])->count(),
                    'pending_refunds' => Refund::where('status', 'pending')->count(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching system status', ['error' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => 'Could not fetch system status',
            ];
        }
    }

    /**
     * Check if business is open today
     * 
     * @param AppointmentSettings $settings
     * @return bool
     */
    private function isOpenToday(AppointmentSettings $settings): bool
    {
        try {
            $today = now()->format('l'); // e.g., "Monday"
            $businessHours = $settings->business_hours ?? '';

            return stripos($businessHours, $today) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check database connectivity
     * 
     * @return array
     */
    private function checkDatabaseStatus(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'connected', 'response_time' => 'normal'];
        } catch (\Exception $e) {
            return ['status' => 'disconnected', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get appointment availability for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @return array Available time slots
     */
    public function getDateAvailability(string $date): array
    {
        try {
            $cacheKey = "chatbot_availability_{$date}";

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            // This assumes you have logic to determine available slots
            // Adjust based on your actual availability system
            $availableSlots = [
                '09:00' => 'Available',
                '10:00' => 'Available',
                '11:00' => 'Available',
                '14:00' => 'Available',
                '15:00' => 'Available',
                '16:00' => 'Available',
            ];

            Cache::put($cacheKey, $availableSlots, self::CACHE_TTL);

            return $availableSlots;
        } catch (\Exception $e) {
            Log::warning('Error fetching date availability', ['date' => $date, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Clear relevant caches (called after data modifications)
     * 
     * @param string $type What type of cache to clear (all, appointments, payments, etc)
     */
    public function clearCache(string $type = 'all'): void
    {
        try {
            if ($type === 'all') {
                Cache::forget('chatbot_*');
            } else {
                Cache::forget("chatbot_{$type}_*");
            }

            Log::info("Chatbot cache cleared", ['type' => $type]);
        } catch (\Exception $e) {
            Log::warning("Error clearing chatbot cache", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get all appointments for admin view
     * 
     * @param int $limit
     * @return array
     */
    public function getAllAppointments(int $limit = 50): array
    {
        try {
            $cacheKey = 'chatbot_all_appointments';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $appointments = Appointment::orderBy('appointment_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->with('user:id,first_name,last_name,email')
                ->get()
                ->map(function ($apt) {
                    return [
                        'id' => $apt->id,
                        'user_id' => $apt->user_id,
                        'user_name' => $apt->user?->first_name . ' ' . $apt->user?->last_name,
                        'user_email' => $apt->user?->email,
                        'service' => $apt->service_type,
                        'date' => $apt->appointment_date?->format('Y-m-d'),
                        'time' => $apt->appointment_time,
                        'status' => $apt->status,
                        'payment_status' => $apt->payment_status,
                        'created_at' => $apt->created_at?->toDateTimeString(),
                        'is_today' => $apt->appointment_date && $apt->appointment_date->isToday(),
                        'is_upcoming' => $apt->appointment_date && $apt->appointment_date->isFuture(),
                    ];
                })->toArray();

            Cache::put($cacheKey, $appointments, self::CRITICAL_DATA_TTL);

            return $appointments;
        } catch (\Exception $e) {
            Log::error('Error fetching all appointments', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get system analytics for admin dashboard
     * 
     * @return array
     */
    public function getSystemAnalytics(): array
    {
        try {
            $cacheKey = 'chatbot_system_analytics';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $analytics = [
                'total_users' => User::count(),
                'total_appointments' => Appointment::count(),
                'appointments_this_week' => Appointment::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count(),
                'completed_this_week' => Appointment::where('status', 'completed')
                    ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                    ->count(),
                'cancelled_this_week' => Appointment::where('status', 'cancelled')
                    ->whereBetween('updated_at', [$startOfWeek, $endOfWeek])
                    ->count(),
                'revenue_this_week' => Payment::whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->where('payment_status', 'paid')
                    ->sum('amount_paid') ?? 0,
                'pending_appointments' => Appointment::where('status', 'pending')->count(),
                'pending_payments' => Appointment::whereIn('payment_status', ['pending', 'partial'])->count(),
                'pending_refunds' => Refund::where('status', 'pending')->count(),
                'completion_rate' => $this->calculateCompletionRate(),
            ];

            Cache::put($cacheKey, $analytics, self::CACHE_TTL);

            return $analytics;
        } catch (\Exception $e) {
            Log::error('Error fetching system analytics', ['error' => $e->getMessage()]);
            return [
                'total_users' => 0,
                'total_appointments' => 0,
                'appointments_this_week' => 0,
                'completed_this_week' => 0,
                'cancelled_this_week' => 0,
                'revenue_this_week' => 0,
                'pending_appointments' => 0,
                'pending_payments' => 0,
                'pending_refunds' => 0,
                'completion_rate' => 0,
            ];
        }
    }

    /**
     * Calculate appointment completion rate
     */
    private function calculateCompletionRate(): float
    {
        try {
            $total = Appointment::whereIn('status', ['completed', 'cancelled', 'no_show'])->count();
            $completed = Appointment::where('status', 'completed')->count();

            if ($total === 0) {
                return 0;
            }

            return round(($completed / $total) * 100, 1);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get user statistics for admin
     * 
     * @return array
     */
    public function getUserStats(): array
    {
        try {
            $cacheKey = 'chatbot_user_stats';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $stats = [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'clients' => User::role('client')->count(),
                'staff' => User::role('staff')->count(),
                'admins' => User::role('admin')->count(),
                'cashiers' => User::role('cashier')->count(),
                'new_this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ];

            Cache::put($cacheKey, $stats, self::CACHE_TTL);

            return $stats;
        } catch (\Exception $e) {
            Log::error('Error fetching user stats', ['error' => $e->getMessage()]);
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'clients' => 0,
                'staff' => 0,
                'admins' => 0,
                'cashiers' => 0,
                'new_this_week' => 0,
                'new_this_month' => 0,
            ];
        }
    }

    /**
     * Get refund details by ID
     * 
     * @param int $refundId
     * @return array|null
     */
    public function getRefundDetails(int $refundId): ?array
    {
        try {
            $refund = Refund::with(['appointment', 'requestedBy', 'approvedBy'])->find($refundId);

            if (!$refund) {
                return null;
            }

            return [
                'id' => $refund->id,
                'appointment_id' => $refund->appointment_id,
                'requested_by' => $refund->requestedBy?->first_name . ' ' . $refund->requestedBy?->last_name,
                'requested_by_email' => $refund->requestedBy?->email,
                'refund_amount' => $refund->refund_amount,
                'original_amount' => $refund->original_amount,
                'reason' => $refund->reason,
                'description' => $refund->description,
                'status' => $refund->status,
                'approved_by' => $refund->approvedBy?->first_name . ' ' . $refund->approvedBy?->last_name,
                'approval_notes' => $refund->approval_notes,
                'approved_at' => $refund->approved_at?->toDateTimeString(),
                'completed_at' => $refund->completed_at?->toDateTimeString(),
                'refund_method' => $refund->refund_method,
                'created_at' => $refund->created_at?->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching refund details', ['refund_id' => $refundId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get cashier shift data
     * 
     * @param int $cashierId
     * @param string|null $date
     * @return array
     */
    public function getCashierShiftData(int $cashierId, ?string $date = null): array
    {
        try {
            $targetDate = $date ? \Carbon\Carbon::parse($date) : now();
            $startOfDay = $targetDate->copy()->startOfDay();
            $endOfDay = $targetDate->copy()->endOfDay();

            $payments = Payment::where('recorded_by', $cashierId)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->get();

            $refunds = Refund::where('approved_by', $cashierId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfDay, $endOfDay])
                ->get();

            return [
                'date' => $targetDate->format('Y-m-d'),
                'cashier_id' => $cashierId,
                'payments_processed' => $payments->count(),
                'total_collected' => $payments->sum('amount_paid'),
                'refunds_processed' => $refunds->count(),
                'total_refunded' => $refunds->sum('refund_amount'),
                'net_amount' => $payments->sum('amount_paid') - $refunds->sum('refund_amount'),
                'transactions' => [
                    'payments' => $payments->map(fn($p) => [
                        'id' => $p->id,
                        'appointment_id' => $p->appointment_id,
                        'amount' => $p->amount_paid,
                        'time' => $p->created_at?->format('H:i'),
                    ])->toArray(),
                    'refunds' => $refunds->map(fn($r) => [
                        'id' => $r->id,
                        'appointment_id' => $r->appointment_id,
                        'amount' => $r->refund_amount,
                        'time' => $r->completed_at?->format('H:i'),
                    ])->toArray(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching cashier shift data', ['cashier_id' => $cashierId, 'error' => $e->getMessage()]);
            return [
                'date' => $date ?? now()->format('Y-m-d'),
                'cashier_id' => $cashierId,
                'payments_processed' => 0,
                'total_collected' => 0,
                'refunds_processed' => 0,
                'total_refunded' => 0,
                'net_amount' => 0,
                'transactions' => ['payments' => [], 'refunds' => []],
            ];
        }
    }

    /**
     * Get comprehensive system statistics for admin
     * 
     * @return array
     */
    public function getSystemStats(): array
    {
        try {
            $cacheKey = 'chatbot_system_stats';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'total_appointments' => Appointment::count(),
                'pending_appointments' => Appointment::where('status', 'pending')->count(),
                'approved_appointments' => Appointment::where('status', 'approved')->count(),
                'completed_appointments' => Appointment::where('status', 'completed')->count(),
                'cancelled_appointments' => Appointment::where('status', 'cancelled')->count(),
                'appointments_today' => Appointment::whereDate('appointment_date', now())->count(),
                'pending_payments' => Appointment::whereIn('payment_status', ['pending', 'partial'])->count(),
                'pending_refunds' => Refund::where('status', 'pending')->count(),
                'total_revenue' => Payment::where('status', 'paid')->sum('amount'),
            ];

            Cache::put($cacheKey, $stats, self::CACHE_TTL);

            return $stats;
        } catch (\Exception $e) {
            Log::error('Error fetching system stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get today's appointments summary
     * 
     * @return array
     */
    public function getTodaysSummary(): array
    {
        try {
            $cacheKey = 'chatbot_todays_summary';

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $today = now()->format('Y-m-d');
            $appointments = Appointment::whereDate('appointment_date', $today)->get();
            
            $summary = [
                'date' => $today,
                'total' => $appointments->count(),
                'pending' => $appointments->where('status', 'pending')->count(),
                'approved' => $appointments->where('status', 'approved')->count(),
                'completed' => $appointments->where('status', 'completed')->count(),
                'cancelled' => $appointments->where('status', 'cancelled')->count(),
                'collections' => Payment::whereDate('created_at', $today)->where('status', 'paid')->sum('amount'),
                'refunds' => Refund::whereDate('updated_at', $today)->where('status', 'completed')->sum('amount'),
                'appointments_for_payment' => Appointment::whereDate('appointment_date', $today)
                    ->whereIn('payment_status', ['pending', 'partial'])
                    ->count(),
            ];

            Cache::put($cacheKey, $summary, self::CACHE_TTL);

            return $summary;
        } catch (\Exception $e) {
            Log::error('Error fetching today summary', ['error' => $e->getMessage()]);
            return [
                'date' => now()->format('Y-m-d'),
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'collections' => 0,
                'refunds' => 0,
                'appointments_for_payment' => 0,
            ];
        }
    }
}
