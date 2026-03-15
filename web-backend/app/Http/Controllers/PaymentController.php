<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentController extends Controller
{
    /**
     * Get all payment methods
     */
    public function getPaymentMethods()
    {
        try {
            $methods = PaymentMethod::all();
            return response()->json([
                'success' => true,
                'data' => $methods
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    /**
     * Record a payment for an appointment
     */
    public function recordPayment(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_date' => 'required|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'goods_description' => 'nullable|string',
            'is_pwd' => 'nullable|boolean',
            'is_senior_citizen' => 'nullable|boolean',
            'is_student' => 'nullable|boolean'
        ]);

        try {
            DB::beginTransaction();

            $appointment = Appointment::findOrFail($request->appointment_id);

            // Check if payment already exists — use lockForUpdate to prevent duplicate payments
            $existingPayment = Payment::where('appointment_id', $appointment->id)
                ->lockForUpdate()
                ->first();
            if ($existingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already recorded for this appointment'
                ], 400);
            }

            $servicePrice = $appointment->service->price ?? 0;
            $amountPaid = (float) $request->amount_paid;
            $discountAmount = (float) ($request->discount_amount ?? 0);

            $payment = new Payment([
                'appointment_id' => $appointment->id,
                'recorded_by' => auth()->id(),
                'service_price' => $servicePrice,
                'amount_paid' => $amountPaid,
                'discount_amount' => $discountAmount,
                'payment_method_id' => $request->payment_method_id,
                'notes' => $request->notes,
                'goods_description' => $request->goods_description,
                'payment_date' => $request->payment_date,
                'is_pwd' => $request->boolean('is_pwd'),
                'is_senior_citizen' => $request->boolean('is_senior_citizen'),
                'is_student' => $request->boolean('is_student')
            ]);

            // Apply category discounts
            $pwdRate = \App\Models\DiscountRate::getByType('pwd')?->discount_percentage ?? 0;
            $seniorRate = \App\Models\DiscountRate::getByType('senior_citizen')?->discount_percentage ?? 0;
            $studentRate = \App\Models\DiscountRate::getByType('student')?->discount_percentage ?? 0;

            $payment->applyDiscounts($pwdRate, $seniorRate, $studentRate);

            // Calculate final values
            $finalPrice = $payment->amount_after_discount ?? $servicePrice;
            $totalDiscount = $payment->total_discount_applied + $discountAmount;
            $shortfall = Payment::calculateShortfall($finalPrice, $amountPaid, 0);
            $paymentStatus = Payment::determinePaymentStatus($finalPrice, $amountPaid);

            $payment->shortfall = $shortfall;
            $payment->payment_status = $paymentStatus;
            $payment->save();

            // Log action
            \App\Models\ActionLog::log(
                'payment_recorded',
                "Payment of {$amountPaid} recorded for appointment {$appointment->id}",
                'Payment',
                $payment->id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $payment->load(['paymentMethod', 'recordedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    /**
     * Get payment for an appointment
     */
    public function getPaymentByAppointment($appointmentId)
    {
        try {
            $appointment = Appointment::findOrFail($appointmentId);

            // SECURITY: Verify requesting user has access to this appointment's payment
            $user = request()->user();
            if ($user && !in_array($user->role, ['admin', 'staff', 'cashier'])) {
                if ($appointment->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to view this payment'
                    ], 403);
                }
            }

            $payment = Payment::where('appointment_id', $appointmentId)->first();

            if (!$payment) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No payment recorded yet'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $payment->load(['paymentMethod', 'recordedBy', 'appointment'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }

    /**
     * Update payment record
     */
    public function updatePayment(Request $request, $paymentId)
    {
        $request->validate([
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_date' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'goods_description' => 'nullable|string|max:1000',
            'edit_notes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $payment = Payment::findOrFail($paymentId);

            // SECURITY: Verify requesting user has permission to update this payment
            $user = $request->user();
            if ($user && !in_array($user->role, ['admin', 'staff', 'cashier'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this payment'
                ], 403);
            }

            $oldValues = $payment->getAttributes();

            // Update fields
            if ($request->has('amount_paid')) {
                $payment->amount_paid = (float) $request->amount_paid;
                $shortfall = Payment::calculateShortfall(
                    $payment->service_price,
                    $payment->amount_paid,
                    $payment->discount_amount
                );
                $payment->shortfall = $shortfall;
                $payment->payment_status = Payment::determinePaymentStatus(
                    $payment->service_price,
                    $payment->amount_paid
                );
            }

            if ($request->has('payment_method_id')) {
                $payment->payment_method_id = $request->payment_method_id;
            }

            if ($request->has('payment_date')) {
                $payment->payment_date = $request->payment_date;
            }

            if ($request->has('discount_amount')) {
                $payment->discount_amount = (float) $request->discount_amount;
            }

            if ($request->has('notes')) {
                $payment->notes = $request->notes;
            }

            if ($request->has('goods_description')) {
                $payment->goods_description = $request->goods_description;
            }

            $payment->is_edited = true;
            $payment->edit_notes = $request->edit_notes;
            $payment->last_edited_by = auth()->id();
            $payment->last_edited_at = now();
            $payment->save();

            // Log action
            \App\Models\ActionLog::log(
                'payment_updated',
                "Payment {$payment->id} updated. " . ($request->edit_notes ? "Reason: {$request->edit_notes}" : ''),
                'Payment',
                $payment->id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'data' => $payment->load(['paymentMethod', 'recordedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred'
            ], 500);
        }
    }
}
