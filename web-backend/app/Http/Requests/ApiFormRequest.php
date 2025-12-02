<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base API FormRequest
 * Provides standardized error handling and validation for API endpoints
 */
class ApiFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     * Returns JSON response instead of redirecting
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Get the validation rules that apply to the request.
     * Override in child classes
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get custom validation messages.
     * Override in child classes for custom messages
     */
    public function messages(): array
    {
        return [
            '*.required' => 'The :attribute field is required.',
            '*.email' => 'The :attribute field must be a valid email.',
            '*.unique' => 'The :attribute has already been taken.',
            '*.date' => 'The :attribute must be a valid date.',
            '*.numeric' => 'The :attribute must be numeric.',
            '*.min' => 'The :attribute must be at least :min.',
            '*.max' => 'The :attribute may not be greater than :max.',
        ];
    }

    /**
     * Get custom validation attribute names.
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Get the validation rules for batch operations (with array syntax)
     * Override in child classes
     */
    public function batchRules(): array
    {
        return [
            '*.id' => 'required|integer|exists:appointments,id',
        ];
    }
}
