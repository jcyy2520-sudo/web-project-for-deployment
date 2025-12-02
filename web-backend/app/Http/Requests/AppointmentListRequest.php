<?php

namespace App\Http\Requests;

/**
 * Appointment List Request
 * Validation for appointment listing with pagination
 */
class AppointmentListRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,approved,completed,cancelled,no_show',
            'user_id' => 'nullable|integer|exists:users,id',
            'staff_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|string|in:date,status,user,created_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }
}
