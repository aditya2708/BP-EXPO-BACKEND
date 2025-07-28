<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseCRUDRequest extends FormRequest
{
    protected $modelName;
    protected $operation; // 'store' or 'update'
    protected $resourceId;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Override in child classes if needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $this->modelName = $this->getModelName();
        $this->operation = $this->isMethod('post') ? 'store' : 'update';
        $this->resourceId = $this->route('id');

        return $this->getModelRules();
    }

    /**
     * Get validation rules based on model
     */
    protected function getModelRules(): array
    {
        return match($this->modelName) {
            'jenjang' => $this->getJenjangRules(),
            'mata_pelajaran' => $this->getMataPelajaranRules(),
            'kelas' => $this->getKelasRules(),
            'materi' => $this->getMateriRules(),
            'kurikulum' => $this->getKurikulumRules(),
            default => []
        };
    }

    /**
     * Get Jenjang validation rules
     */
    protected function getJenjangRules(): array
    {
        $uniqueNama = 'unique:jenjang,nama_jenjang';
        $uniqueKode = 'unique:jenjang,kode_jenjang';
        $uniqueUrutan = 'unique:jenjang,urutan';

        if ($this->operation === 'update') {
            $uniqueNama .= ',' . $this->resourceId . ',id_jenjang';
            $uniqueKode .= ',' . $this->resourceId . ',id_jenjang';
            $uniqueUrutan .= ',' . $this->resourceId . ',id_jenjang';
        }

        $rules = [
            'nama_jenjang' => ['required', 'string', 'max:100', $uniqueNama],
            'kode_jenjang' => ['required', 'string', 'max:10', $uniqueKode],
            'urutan' => ['required', 'integer', 'min:1', $uniqueUrutan],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean']
        ];

        if ($this->operation === 'update') {
            foreach ($rules as $key => $rule) {
                $rules[$key] = array_merge(['sometimes'], is_array($rule) ? $rule : [$rule]);
            }
        }

        return $rules;
    }

    /**
     * Get MataPelajaran validation rules
     */
    protected function getMataPelajaranRules(): array
    {
        $uniqueKode = 'unique:mata_pelajaran,kode_mata_pelajaran';

        if ($this->operation === 'update') {
            $uniqueKode .= ',' . $this->resourceId . ',id_mata_pelajaran';
        }

        $rules = [
            'id_jenjang' => ['nullable', 'exists:jenjang,id_jenjang'],
            'nama_mata_pelajaran' => ['required', 'string', 'max:255'],
            'kode_mata_pelajaran' => ['required', 'string', 'max:50', $uniqueKode],
            'kategori' => ['required', 'in:wajib,muatan_lokal,pengembangan_diri,pilihan,ekstrakurikuler'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:aktif,nonaktif']
        ];

        if ($this->operation === 'update') {
            foreach ($rules as $key => $rule) {
                if ($key !== 'status') {
                    $rules[$key] = array_merge(['sometimes'], is_array($rule) ? $rule : [$rule]);
                }
            }
        }

        return $rules;
    }

    /**
     * Get Kelas validation rules
     */
    protected function getKelasRules(): array
    {
        $rules = [
            'id_jenjang' => ['required', 'exists:jenjang,id_jenjang'],
            'nama_kelas' => ['required', 'string', 'max:100'],
            'jenis_kelas' => ['required', 'in:standard,custom'],
            'tingkat' => ['nullable', 'integer', 'min:1', 'max:12'],
            'urutan' => ['required', 'integer', 'min:1'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean']
        ];

        if ($this->operation === 'update') {
            foreach ($rules as $key => $rule) {
                $rules[$key] = array_merge(['sometimes'], is_array($rule) ? $rule : [$rule]);
            }
        }

        return $rules;
    }

    /**
     * Get Materi validation rules
     */
    protected function getMateriRules(): array
    {
        $rules = [
            'id_mata_pelajaran' => ['required', 'exists:mata_pelajaran,id_mata_pelajaran'],
            'id_kelas' => ['required', 'exists:kelas,id_kelas'],
            'nama_materi' => ['required', 'string', 'max:255']
        ];

        if ($this->operation === 'update') {
            foreach ($rules as $key => $rule) {
                $rules[$key] = array_merge(['sometimes'], is_array($rule) ? $rule : [$rule]);
            }
        }

        return $rules;
    }

    /**
     * Get Kurikulum validation rules
     */
    protected function getKurikulumRules(): array
    {
        $rules = [
            'nama_kurikulum' => ['required', 'string', 'max:255'],
            'kode_kurikulum' => ['required', 'string', 'max:50'],
            'tahun_berlaku' => ['required', 'integer', 'min:2020', 'max:2030'],
            'id_jenjang' => ['required', 'integer', 'exists:jenjang,id_jenjang'],
            'id_mata_pelajaran' => ['required', 'integer', 'exists:mata_pelajaran,id_mata_pelajaran'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'tujuan' => ['nullable', 'string', 'max:1000'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
            'is_active' => ['boolean'],
            'status' => ['sometimes', 'required', 'in:aktif,draft,nonaktif']
        ];

        if ($this->operation === 'update') {
            foreach ($rules as $key => $rule) {
                if ($key !== 'status') {
                    $rules[$key] = array_merge(['sometimes'], is_array($rule) ? $rule : [$rule]);
                }
            }
        }

        return $rules;
    }

    /**
     * Get model name from route or request
     */
    protected function getModelName(): string
    {
        $route = $this->route();
        
        if (!$route) {
            return '';
        }

        $uri = $route->uri();
        
        // Extract model name from route patterns
        if (str_contains($uri, 'jenjang')) return 'jenjang';
        if (str_contains($uri, 'mata-pelajaran')) return 'mata_pelajaran';
        if (str_contains($uri, 'kelas')) return 'kelas';
        if (str_contains($uri, 'materi')) return 'materi';
        if (str_contains($uri, 'kurikulum')) return 'kurikulum';
        
        return '';
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute harus diisi',
            'string' => ':attribute harus berupa teks',
            'max' => ':attribute maksimal :max karakter',
            'min' => ':attribute minimal :min',
            'integer' => ':attribute harus berupa angka',
            'boolean' => ':attribute harus true atau false',
            'exists' => ':attribute tidak ditemukan',
            'unique' => ':attribute sudah digunakan',
            'in' => ':attribute tidak valid',
            'date' => ':attribute harus berupa tanggal yang valid',
            'after' => ':attribute harus setelah :date',
            
            // Model specific messages
            'nama_jenjang.required' => 'Nama jenjang harus diisi',
            'nama_jenjang.unique' => 'Nama jenjang sudah digunakan',
            'kode_jenjang.required' => 'Kode jenjang harus diisi',
            'kode_jenjang.unique' => 'Kode jenjang sudah digunakan',
            'urutan.required' => 'Urutan harus diisi',
            'urutan.unique' => 'Urutan sudah digunakan',
            
            'nama_mata_pelajaran.required' => 'Nama mata pelajaran harus diisi',
            'kode_mata_pelajaran.required' => 'Kode mata pelajaran harus diisi',
            'kode_mata_pelajaran.unique' => 'Kode mata pelajaran sudah digunakan',
            'kategori.required' => 'Kategori harus dipilih',
            'kategori.in' => 'Kategori tidak valid',
            
            'id_jenjang.required' => 'Jenjang harus dipilih',
            'id_jenjang.exists' => 'Jenjang tidak ditemukan',
            'nama_kelas.required' => 'Nama kelas harus diisi',
            'jenis_kelas.required' => 'Jenis kelas harus dipilih',
            'jenis_kelas.in' => 'Jenis kelas harus standard atau custom',
            'tingkat.integer' => 'Tingkat harus berupa angka',
            'tingkat.min' => 'Tingkat minimal 1',
            'tingkat.max' => 'Tingkat maksimal 12',
            
            'id_mata_pelajaran.required' => 'Mata pelajaran harus dipilih',
            'id_mata_pelajaran.exists' => 'Mata pelajaran tidak ditemukan',
            'id_kelas.required' => 'Kelas harus dipilih',
            'id_kelas.exists' => 'Kelas tidak ditemukan',
            'nama_materi.required' => 'Nama materi harus diisi',
            
            'nama_kurikulum.required' => 'Nama kurikulum harus diisi',
            'kode_kurikulum.required' => 'Kode kurikulum harus diisi',
            'tahun_berlaku.required' => 'Tahun berlaku harus diisi',
            'tahun_berlaku.min' => 'Tahun berlaku minimal 2020',
            'tahun_berlaku.max' => 'Tahun berlaku maksimal 2030',
            'tanggal_mulai.required' => 'Tanggal mulai harus diisi',
            'tanggal_selesai.required' => 'Tanggal selesai harus diisi',
            'tanggal_selesai.after' => 'Tanggal selesai harus setelah tanggal mulai'
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'nama_jenjang' => 'nama jenjang',
            'kode_jenjang' => 'kode jenjang',
            'urutan' => 'urutan',
            'deskripsi' => 'deskripsi',
            'is_active' => 'status aktif',
            'id_jenjang' => 'jenjang',
            'nama_mata_pelajaran' => 'nama mata pelajaran',
            'kode_mata_pelajaran' => 'kode mata pelajaran',
            'kategori' => 'kategori',
            'status' => 'status',
            'nama_kelas' => 'nama kelas',
            'jenis_kelas' => 'jenis kelas',
            'tingkat' => 'tingkat',
            'id_mata_pelajaran' => 'mata pelajaran',
            'id_kelas' => 'kelas',
            'nama_materi' => 'nama materi',
            'nama_kurikulum' => 'nama kurikulum',
            'kode_kurikulum' => 'kode kurikulum',
            'tahun_berlaku' => 'tahun berlaku',
            'tujuan' => 'tujuan',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai'
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}