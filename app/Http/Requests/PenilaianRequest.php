<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenilaianRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'id_anak' => [
                'required',
                'exists:anak,id_anak',
                Rule::exists('anak', 'id_anak')->where(function ($query) {
                    $query->whereIn('status_validasi', ['aktif', 'Aktif']);
                })
            ],
            'id_aktivitas' => 'required|exists:aktivitas,id_aktivitas',
            'id_materi' => 'nullable|exists:materi,id_materi',
            'id_jenis_penilaian' => 'required|exists:jenis_penilaian,id_jenis_penilaian',
            'id_semester' => [
                'required',
                'exists:semester,id_semester',
                Rule::exists('semester', 'id_semester')->where(function ($query) {
                    $query->where('is_active', true);
                })
            ],
            'nilai' => 'required|numeric|min:0|max:100',
            'deskripsi_tugas' => 'nullable|string|max:500',
            'tanggal_penilaian' => 'required|date|before_or_equal:today',
            'catatan' => 'nullable|string|max:1000'
        ];

        // Additional rules for update
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = array_map(function ($rule) {
                if (is_array($rule)) {
                    array_unshift($rule, 'sometimes');
                    return $rule;
                }
                return 'sometimes|' . $rule;
            }, $rules);
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'id_anak.required' => 'Anak wajib dipilih',
            'id_anak.exists' => 'Anak tidak ditemukan atau tidak aktif',
            'id_aktivitas.required' => 'Aktivitas wajib dipilih',
            'id_aktivitas.exists' => 'Aktivitas tidak ditemukan',
            'id_materi.exists' => 'Materi tidak ditemukan',
            'id_jenis_penilaian.required' => 'Jenis penilaian wajib dipilih',
            'id_jenis_penilaian.exists' => 'Jenis penilaian tidak ditemukan',
            'id_semester.required' => 'Semester wajib dipilih',
            'id_semester.exists' => 'Semester tidak ditemukan atau tidak aktif',
            'nilai.required' => 'Nilai wajib diisi',
            'nilai.numeric' => 'Nilai harus berupa angka',
            'nilai.min' => 'Nilai minimal adalah 0',
            'nilai.max' => 'Nilai maksimal adalah 100',
            'deskripsi_tugas.max' => 'Deskripsi tugas maksimal 500 karakter',
            'tanggal_penilaian.required' => 'Tanggal penilaian wajib diisi',
            'tanggal_penilaian.date' => 'Format tanggal penilaian tidak valid',
            'tanggal_penilaian.before_or_equal' => 'Tanggal penilaian tidak boleh lebih dari hari ini',
            'catatan.max' => 'Catatan maksimal 1000 karakter'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'id_anak' => 'anak',
            'id_aktivitas' => 'aktivitas',
            'id_materi' => 'materi',
            'id_jenis_penilaian' => 'jenis penilaian',
            'id_semester' => 'semester',
            'nilai' => 'nilai',
            'deskripsi_tugas' => 'deskripsi tugas',
            'tanggal_penilaian' => 'tanggal penilaian',
            'catatan' => 'catatan'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Convert nilai to float if it's a string
        if ($this->has('nilai') && is_string($this->nilai)) {
            $this->merge([
                'nilai' => floatval($this->nilai)
            ]);
        }

        // Ensure tanggal_penilaian is in correct format
        if ($this->has('tanggal_penilaian')) {
            $this->merge([
                'tanggal_penilaian' => date('Y-m-d', strtotime($this->tanggal_penilaian))
            ]);
        }
    }
}

class BulkPenilaianRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'penilaian' => 'required|array|min:1',
            'penilaian.*.id_anak' => [
                'required',
                'exists:anak,id_anak',
                Rule::exists('anak', 'id_anak')->where(function ($query) {
                    $query->whereIn('status_validasi', ['aktif', 'Aktif']);
                })
            ],
            'penilaian.*.id_aktivitas' => 'required|exists:aktivitas,id_aktivitas',
            'penilaian.*.id_materi' => 'nullable|exists:materi,id_materi',
            'penilaian.*.id_jenis_penilaian' => 'required|exists:jenis_penilaian,id_jenis_penilaian',
            'penilaian.*.id_semester' => [
                'required',
                'exists:semester,id_semester',
                Rule::exists('semester', 'id_semester')->where(function ($query) {
                    $query->where('is_active', true);
                })
            ],
            'penilaian.*.nilai' => 'required|numeric|min:0|max:100',
            'penilaian.*.deskripsi_tugas' => 'nullable|string|max:500',
            'penilaian.*.tanggal_penilaian' => 'required|date|before_or_equal:today',
            'penilaian.*.catatan' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'penilaian.required' => 'Data penilaian wajib diisi',
            'penilaian.array' => 'Format data penilaian tidak valid',
            'penilaian.min' => 'Minimal harus ada 1 data penilaian',
            'penilaian.*.id_anak.required' => 'Anak wajib dipilih untuk setiap penilaian',
            'penilaian.*.id_anak.exists' => 'Anak tidak ditemukan atau tidak aktif',
            'penilaian.*.id_aktivitas.required' => 'Aktivitas wajib dipilih untuk setiap penilaian',
            'penilaian.*.id_aktivitas.exists' => 'Aktivitas tidak ditemukan',
            'penilaian.*.id_jenis_penilaian.required' => 'Jenis penilaian wajib dipilih',
            'penilaian.*.id_semester.required' => 'Semester wajib dipilih',
            'penilaian.*.nilai.required' => 'Nilai wajib diisi',
            'penilaian.*.nilai.numeric' => 'Nilai harus berupa angka',
            'penilaian.*.nilai.min' => 'Nilai minimal adalah 0',
            'penilaian.*.nilai.max' => 'Nilai maksimal adalah 100',
            'penilaian.*.tanggal_penilaian.required' => 'Tanggal penilaian wajib diisi',
            'penilaian.*.tanggal_penilaian.date' => 'Format tanggal tidak valid',
            'penilaian.*.tanggal_penilaian.before_or_equal' => 'Tanggal penilaian tidak boleh lebih dari hari ini'
        ];
    }
}