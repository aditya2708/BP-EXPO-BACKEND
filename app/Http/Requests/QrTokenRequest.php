<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QrTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Authorization will be handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $method = $this->route()->getActionMethod();
        
        switch ($method) {
            case 'generate':
                return [
                    'id_anak' => 'required|exists:anak,id_anak',
                    'valid_days' => 'nullable|integer|min:1|max:365'
                ];
                
            case 'generateBatch':
                return [
                    'student_ids' => 'required|array',
                    'student_ids.*' => 'required|exists:anak,id_anak',
                    'valid_days' => 'nullable|integer|min:1|max:365'
                ];
                
            case 'validate':
            case 'invalidate':
                return [
                    'token' => 'required|string'
                ];
                
            default:
                return [];
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'id_anak.required' => 'Student ID is required',
            'id_anak.exists' => 'Student not found with the provided ID',
            'student_ids.required' => 'At least one student ID is required',
            'student_ids.*.exists' => 'One or more students not found with the provided IDs',
            'valid_days.integer' => 'Valid days must be a number',
            'valid_days.min' => 'Valid days must be at least 1',
            'valid_days.max' => 'Valid days cannot exceed 365',
            'token.required' => 'QR token is required',
        ];
    }
}