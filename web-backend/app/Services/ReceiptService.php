<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;

class ReceiptService
{
    /**
     * Generate receipt data with an HMAC integrity hash.
     */
    public static function generate(Appointment $appointment, ?Payment $payment = null): array
    {
        $appointment->loadMissing(['user', 'service']);

        $servicePrice = (float) ($appointment->service->price ?? 0);
        $totalPaid = (float) ($appointment->payment_amount ?? 0);
        $discount = (float) ($appointment->discount_amount ?? 0);
        $balanceRemaining = (float) ($appointment->balance_remaining ?? 0);

        $receiptData = [
            'receipt_id' => 'RCT-' . str_pad($appointment->id, 6, '0', STR_PAD_LEFT),
            'appointment_id' => $appointment->id,
            'date' => now()->toIso8601String(),
            'client_name' => $appointment->user
                ? "{$appointment->user->first_name} {$appointment->user->last_name}"
                : 'N/A',
            'client_email' => $appointment->user->email ?? '',
            'service' => $appointment->service->name ?? 'N/A',
            'appointment_date' => $appointment->appointment_date,
            'service_price' => $servicePrice,
            'discount' => $discount,
            'discount_type' => $appointment->discount_type ?? '',
            'total_paid' => $totalPaid,
            'balance_remaining' => $balanceRemaining,
            'payment_type' => $appointment->payment_type ?? 'cash',
            'processed_by' => $appointment->processedBy
                ? "{$appointment->processedBy->first_name} {$appointment->processedBy->last_name}"
                : null,
        ];

        // Include latest payment details when available
        if ($payment) {
            $receiptData['payment_amount'] = (float) $payment->amount_paid;
            $receiptData['payment_date'] = $payment->payment_date?->toIso8601String();
            $receiptData['in_kind_description'] = $payment->goods_description;
            $receiptData['in_kind_estimated_value'] = $payment->in_kind_estimated_value ? (float) $payment->in_kind_estimated_value : null;
        }

        $receiptData['integrity_hash'] = self::computeHash($receiptData);

        return $receiptData;
    }

    /**
     * Verify a receipt's integrity hash.
     */
    public static function verify(array $receiptData): bool
    {
        $hash = $receiptData['integrity_hash'] ?? null;
        if (!$hash) {
            return false;
        }

        unset($receiptData['integrity_hash']);
        return hash_equals(self::computeHash($receiptData), $hash);
    }

    /**
     * Compute HMAC-SHA256 over deterministic receipt fields.
     */
    private static function computeHash(array $data): string
    {
        $payload = implode('|', [
            $data['receipt_id'] ?? '',
            $data['appointment_id'] ?? '',
            $data['total_paid'] ?? 0,
            $data['date'] ?? '',
        ]);

        return hash_hmac('sha256', $payload, config('app.key', 'fallback-receipt-key'));
    }
}
