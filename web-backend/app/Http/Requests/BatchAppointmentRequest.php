<?php

namespace App\Http\Requests;

/**
 * Batch Appointment Request
 * Validation for batch operations (cancel, complete, etc)
 */
class BatchAppointmentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'appointment_ids' => 'required|array|min:1|max:100',
            'appointment_ids.*' => 'integer|exists:appointments,id',
            'reason' => 'nullable|string|max:500',
            'send_notification' => 'boolean',
            'include_reason' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'appointment_ids.required' => 'At least one appointment ID is required.',
            'appointment_ids.max' => 'Cannot process more than 100 appointments at once.',
            'appointment_ids.*.exists' => 'One or more appointment IDs do not exist.',
        ]);
    }
}
