<?php

namespace App\Http\Controllers\API\Base;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BaseService
{
    protected $model;
    protected $modelClass;

    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
        $this->model = app($modelClass);
    }

    // Master Data Dependency Validation
    public function validateJenjangDependencies($jenjangId, $operation = 'delete')
    {
        $dependencies = [];
        
        if ($operation === 'delete' || $operation === 'deactivate') {
            // Check mata pelajaran
            $mataPelajaranCount = app('App\Models\MataPelajaran')->where('id_jenjang', $jenjangId)->count();
            if ($mataPelajaranCount > 0) {
                $dependencies['mata_pelajaran'] = $mataPelajaranCount;
            }
            
            // Check kelas
            $kelasCount = app('App\Models\Kelas')->where('id_jenjang', $jenjangId)->count();
            if ($kelasCount > 0) {
                $dependencies['kelas'] = $kelasCount;
            }
        }
        
        if ($operation === 'deactivate') {
            // Check active kelas
            $activeKelasCount = app('App\Models\Kelas')
                ->where('id_jenjang', $jenjangId)
                ->where('is_active', true)
                ->count();
            if ($activeKelasCount > 0) {
                $dependencies['active_kelas'] = $activeKelasCount;
            }
        }
        
        return $dependencies;
    }

    public function validateMataPelajaranDependencies($mataPelajaranId, $operation = 'delete')
    {
        $dependencies = [];
        
        if ($operation === 'delete') {
            // Check materi
            $materiCount = app('App\Models\Materi')->where('id_mata_pelajaran', $mataPelajaranId)->count();
            if ($materiCount > 0) {
                $dependencies['materi'] = $materiCount;
            }
            
            // Check kurikulum usage
            $kurikulumCount = app('App\Models\KurikulumMateri')
                ->where('id_mata_pelajaran', $mataPelajaranId)
                ->distinct('id_kurikulum')
                ->count();
            if ($kurikulumCount > 0) {
                $dependencies['kurikulum'] = $kurikulumCount;
            }
        }
        
        return $dependencies;
    }

    public function validateKelasDependencies($kelasId, $operation = 'delete')
    {
        $dependencies = [];
        
        if ($operation === 'delete' || $operation === 'deactivate') {
            // Check materi
            $materiCount = app('App\Models\Materi')->where('id_kelas', $kelasId)->count();
            if ($materiCount > 0) {
                $dependencies['materi'] = $materiCount;
            }
        }
        
        return $dependencies;
    }

    public function validateMateriDependencies($materiId, $operation = 'delete')
    {
        $dependencies = [];
        
        if ($operation === 'delete') {
            // Check kurikulum usage
            $kurikulumCount = app('App\Models\KurikulumMateri')
                ->where('id_materi', $materiId)
                ->distinct('id_kurikulum')
                ->count();
            if ($kurikulumCount > 0) {
                $dependencies['kurikulum'] = $kurikulumCount;
            }
        }
        
        return $dependencies;
    }

    // Cross-Entity Validations
    public function validateJenjangConsistency($mataPelajaranId, $kelasId)
    {
        $mataPelajaran = app('App\Models\MataPelajaran')->find($mataPelajaranId);
        $kelas = app('App\Models\Kelas')->find($kelasId);
        
        if (!$mataPelajaran || !$kelas) {
            return false;
        }
        
        // If mata pelajaran is for all jenjang (id_jenjang = null), it's valid
        if (is_null($mataPelajaran->id_jenjang)) {
            return true;
        }
        
        return $mataPelajaran->id_jenjang === $kelas->id_jenjang;
    }

    public function validateUniqueInScope($field, $value, $additionalConditions = [], $excludeId = null)
    {
        $query = $this->model::where($field, $value);
        
        foreach ($additionalConditions as $conditionField => $conditionValue) {
            $query->where($conditionField, $conditionValue);
        }
        
        if ($excludeId) {
            $query->where($this->model->getKeyName(), '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    // Jenjang Specific Validations
    public function validateJenjangUrutan($urutan, $excludeId = null)
    {
        return $this->validateUniqueInScope('urutan', $urutan, [], $excludeId);
    }

    // Kelas Specific Validations
    public function validateKelasStandardUnique($jenjangId, $tingkat, $excludeId = null)
    {
        return $this->validateUniqueInScope('tingkat', $tingkat, [
            'id_jenjang' => $jenjangId,
            'jenis_kelas' => 'standard'
        ], $excludeId);
    }

    public function validateKelasCustomUnique($jenjangId, $namaKelas, $excludeId = null)
    {
        return $this->validateUniqueInScope('nama_kelas', $namaKelas, [
            'id_jenjang' => $jenjangId,
            'jenis_kelas' => 'custom'
        ], $excludeId);
    }

    public function validateKelasUrutanInJenjang($jenjangId, $urutan, $excludeId = null)
    {
        return $this->validateUniqueInScope('urutan', $urutan, [
            'id_jenjang' => $jenjangId
        ], $excludeId);
    }

    // Materi Specific Validations
    public function validateMateriUnique($mataPelajaranId, $kelasId, $namaMateri, $excludeId = null)
    {
        return $this->validateUniqueInScope('nama_materi', $namaMateri, [
            'id_mata_pelajaran' => $mataPelajaranId,
            'id_kelas' => $kelasId
        ], $excludeId);
    }

    // Kurikulum Specific Operations
    public function getAvailableMateri($kurikulumId, $filters = [])
    {
        $assignedMateriIds = app('App\Models\KurikulumMateri')
            ->where('id_kurikulum', $kurikulumId)
            ->pluck('id_materi');

        $query = app('App\Models\Materi')
            ->with(['mataPelajaran', 'kelas.jenjang'])
            ->whereNotIn('id_materi', $assignedMateriIds);

        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    public function assignMateriToKurikulum($kurikulumId, $materiIds)
    {
        $kurikulum = app('App\Models\Kurikulum')->findOrFail($kurikulumId);
        $existingMateri = app('App\Models\KurikulumMateri')
            ->where('id_kurikulum', $kurikulumId)
            ->whereIn('id_materi', $materiIds)
            ->pluck('id_materi')
            ->toArray();

        $newMateri = array_diff($materiIds, $existingMateri);
        
        if (empty($newMateri)) {
            return [
                'success' => false,
                'message' => 'Semua materi sudah ter-assign ke kurikulum ini'
            ];
        }

        $lastOrder = app('App\Models\KurikulumMateri')
            ->where('id_kurikulum', $kurikulumId)
            ->max('urutan') ?? 0;

        $insertData = [];
        foreach ($newMateri as $materiId) {
            $lastOrder++;
            $materi = app('App\Models\Materi')->find($materiId);
            $insertData[] = [
                'id_kurikulum' => $kurikulumId,
                'id_materi' => $materiId,
                'id_mata_pelajaran' => $materi->id_mata_pelajaran,
                'urutan' => $lastOrder,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        app('App\Models\KurikulumMateri')->insert($insertData);

        return [
            'success' => true,
            'assigned_count' => count($newMateri),
            'message' => 'Materi berhasil di-assign ke kurikulum'
        ];
    }

    public function reorderKurikulumMateri($kurikulumId, $materiOrders)
    {
        foreach ($materiOrders as $order) {
            app('App\Models\KurikulumMateri')
                ->where('id_kurikulum', $kurikulumId)
                ->where('id_materi', $order['id_materi'])
                ->update(['urutan' => $order['urutan']]);
        }

        return true;
    }

    // Statistics Calculations
    public function calculateMasterDataStatistics($kacabId)
    {
        return [
            'jenjang' => [
                'total' => app('App\Models\Jenjang')->count(),
                'active' => app('App\Models\Jenjang')->where('is_active', true)->count()
            ],
            'mata_pelajaran' => [
                'total' => app('App\Models\MataPelajaran')->where('id_kacab', $kacabId)->count(),
                'active' => app('App\Models\MataPelajaran')->where('id_kacab', $kacabId)->where('status', 'aktif')->count()
            ],
            'kelas' => [
                'total' => app('App\Models\Kelas')->count(),
                'active' => app('App\Models\Kelas')->where('is_active', true)->count(),
                'standard' => app('App\Models\Kelas')->where('jenis_kelas', 'standard')->count(),
                'custom' => app('App\Models\Kelas')->where('jenis_kelas', 'custom')->count()
            ],
            'materi' => [
                'total' => app('App\Models\Materi')->whereHas('mataPelajaran', function($q) use ($kacabId) {
                    $q->where('id_kacab', $kacabId);
                })->count(),
                'used_in_kurikulum' => app('App\Models\Materi')->whereHas('mataPelajaran', function($q) use ($kacabId) {
                    $q->where('id_kacab', $kacabId);
                })->has('kurikulumMateri')->count()
            ]
        ];
    }

    public function calculateJenjangStatistics($jenjangId = null)
    {
        $query = app('App\Models\Jenjang')->query();
        
        if ($jenjangId) {
            $query->where('id_jenjang', $jenjangId);
        }

        $jenjang = $query->withCount([
            'kelas',
            'kelasStandard',
            'kelasCustom',
            'mataPelajaran'
        ])->get();

        return $jenjang->map(function($item) {
            return [
                'id_jenjang' => $item->id_jenjang,
                'nama_jenjang' => $item->nama_jenjang,
                'total_kelas' => $item->kelas_count,
                'kelas_standard' => $item->kelas_standard_count,
                'kelas_custom' => $item->kelas_custom_count,
                'total_mata_pelajaran' => $item->mata_pelajaran_count
            ];
        });
    }

    public function calculateKurikulumStatistics($kurikulumId, $kacabId)
    {
        $kurikulum = app('App\Models\Kurikulum')
            ->where('id_kurikulum', $kurikulumId)
            ->where('id_kacab', $kacabId)
            ->firstOrFail();

        $materiCount = app('App\Models\KurikulumMateri')
            ->where('id_kurikulum', $kurikulumId)
            ->count();

        $mataPelajaranCount = app('App\Models\KurikulumMateri')
            ->where('id_kurikulum', $kurikulumId)
            ->distinct('id_mata_pelajaran')
            ->count();

        $semesterCount = app('App\Models\Semester')
            ->where('kurikulum_id', $kurikulumId)
            ->count();

        $activeSemester = app('App\Models\Semester')
            ->where('kurikulum_id', $kurikulumId)
            ->where('is_active', true)
            ->count();

        return [
            'total_materi' => $materiCount,
            'total_mata_pelajaran' => $mataPelajaranCount,
            'total_semester' => $semesterCount,
            'active_semester' => $activeSemester,
            'kurikulum_info' => [
                'nama' => $kurikulum->nama_kurikulum,
                'tahun_berlaku' => $kurikulum->tahun_berlaku,
                'status' => $kurikulum->status,
                'is_active' => $kurikulum->is_active
            ]
        ];
    }

    // Generic helper methods
    public function setActiveStatus($id, $field = 'is_active')
    {
        // Deactivate all others if this model requires single active
        if (method_exists($this->model, 'requiresSingleActive') && $this->model->requiresSingleActive()) {
            $this->model::where($field, true)->update([$field => false]);
        }

        return $this->model::where($this->model->getKeyName(), $id)->update([$field => true]);
    }

    public function getDropdownData($conditions = [], $fields = null)
    {
        $query = $this->model::query();

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        if ($fields) {
            $query->select($fields);
        }

        return $query->get();
    }

    public function searchData($search, $fields = [])
    {
        if (empty($fields)) {
            return collect();
        }

        $query = $this->model::query();
        
        $query->where(function($q) use ($search, $fields) {
            foreach ($fields as $field) {
                $q->orWhere($field, 'like', "%{$search}%");
            }
        });

        return $query->get();
    }
}