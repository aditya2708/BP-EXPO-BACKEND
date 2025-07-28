<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AktivitasRequest extends FormRequest
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
        $rules = [
            'jenis_kegiatan' => 'required|string|max:255',
            'level' => 'nullable|string|max:255',
            'nama_kelompok' => 'nullable|string|max:255',
            'materi' => 'nullable|string',
            'id_materi' => 'nullable|exists:materi,id_materi',
            'id_tutor' => 'required|exists:tutor,id_tutor',
            'tanggal' => 'required|date',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after:start_time',
            'late_threshold' => 'nullable|date_format:H:i:s|after:start_time',
            'late_minutes_threshold' => 'nullable|integer|min:1|max:120',
            'foto_1' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_2' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_3' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
        
        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // For Bimbel activities, ensure either id_materi or materi is provided
            if ($this->jenis_kegiatan === 'Bimbel') {
                if (empty($this->id_materi) && empty($this->materi)) {
                    $validator->errors()->add('materi', 'Either select materi from dropdown or provide custom materi text for Bimbel activities.');
                }
            }
            
            // For Kegiatan activities, ensure materi is provided
            if ($this->jenis_kegiatan === 'Kegiatan') {
                if (empty($this->materi)) {
                    $validator->errors()->add('materi', 'Materi is required for Kegiatan activities.');
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'jenis_kegiatan.required' => 'The activity type is required.',
            'tanggal.required' => 'The date is required.',
            'tanggal.date' => 'The date must be a valid date format.',
            'start_time.date_format' => 'The start time must be in HH:MM:SS format.',
            'end_time.date_format' => 'The end time must be in HH:MM:SS format.',
            'end_time.after' => 'The end time must be after the start time.',
            'late_threshold.date_format' => 'The late threshold must be in HH:MM:SS format.',
            'late_threshold.after' => 'The late threshold must be after the start time.',
            'late_minutes_threshold.integer' => 'The late minutes threshold must be a number.',
            'late_minutes_threshold.min' => 'The late minutes threshold must be at least 1 minute.',
            'late_minutes_threshold.max' => 'The late minutes threshold must not exceed 120 minutes.',
            'id_materi.exists' => 'The selected materi is invalid.',
            'id_tutor.required' => 'Tutor selection is required.',
            'id_tutor.exists' => 'The selected tutor is invalid.',
            'foto_1.image' => 'The photo must be an image.',
            'foto_1.mimes' => 'The photo must be a file of type: jpeg, png, jpg.',
            'foto_1.max' => 'The photo may not be greater than 2 MB.',
            'foto_2.image' => 'The photo must be an image.',
            'foto_2.mimes' => 'The photo must be a file of type: jpeg, png, jpg.',
            'foto_2.max' => 'The photo may not be greater than 2 MB.',
            'foto_3.image' => 'The photo must be an image.',
            'foto_3.mimes' => 'The photo must be a file of type: jpeg, png, jpg.',
            'foto_3.max' => 'The photo may not be greater than 2 MB.',
        ];
    }
}