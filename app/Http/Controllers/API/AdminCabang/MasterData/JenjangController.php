<?php

namespace App\Http\Controllers\API\AdminCabang\MasterData;

use App\Http\Controllers\API\Base\BaseCRUDController;
use App\Http\Controllers\API\Base\BaseService;
use App\Models\Jenjang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JenjangController extends BaseCRUDController
{
    protected $modelClass = Jenjang::class;
    protected $relationships = ['kelas', 'mataPelajaran'];
    protected $searchableFields = ['nama_jenjang', 'kode_jenjang', 'deskripsi'];
    protected $filterableFields = ['is_active'];
    protected $requiresKacabScope = false; // Jenjang is global
    protected $validationRules = [
        'nama_jenjang' => 'required|string|max:100|unique:jenjang,nama_jenjang',
        'kode_jenjang' => 'required|string|max:10|unique:jenjang,kode_jenjang',
        'urutan' => 'required|integer|min:1|unique:jenjang,urutan',
        'deskripsi' => 'nullable|string|max:500',
        'is_active' => 'boolean'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->service = new BaseService($this->modelClass);
    }

    protected function applyDefaultOrdering($query)
    {
        return $query->orderBy('urutan');
    }

    protected function customValidation(Request $request, $operation, $item = null)
    {
        if ($request->has('is_active') && !$request->boolean('is_active') && $operation === 'update') {
            $dependencies = $this->service->validateJenjangDependencies($item->id_jenjang, 'deactivate');
            if (!empty($dependencies)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menonaktifkan jenjang yang masih memiliki kelas aktif'
                ], 422);
            }
        }

        return true;
    }

    protected function prepareStoreData(Request $request)
    {
        $data = parent::prepareStoreData($request);
        if (isset($data['kode_jenjang'])) {
            $data['kode_jenjang'] = strtoupper($data['kode_jenjang']);
        }
        return $data;
    }

    protected function prepareUpdateData(Request $request, $item)
    {
        $data = parent::prepareUpdateData($request, $item);
        if (isset($data['kode_jenjang'])) {
            $data['kode_jenjang'] = strtoupper($data['kode_jenjang']);
        }
        return $data;
    }

    protected function canBeDeleted($item)
    {
        $dependencies = $this->service->validateJenjangDependencies($item->id_jenjang, 'delete');
        if (!empty($dependencies)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus jenjang yang masih memiliki kelas atau mata pelajaran'
            ], 422);
        }
        return true;
    }

    protected function calculateStatistics($query)
    {
        $total = $query->count();
        $active = $query->where('is_active', true)->count();
        
        $withKelas = $query->has('kelas')->count();
        $withMataPelajaran = $query->has('mataPelajaran')->count();
        
        $mostUsedJenjang = $query->withCount('kelas')
            ->orderBy('kelas_count', 'desc')
            ->first(['nama_jenjang', 'kelas_count']);

        return [
            'total_jenjang' => $total,
            'total_jenjang_aktif' => $active,
            'total_with_kelas' => $withKelas,
            'total_with_mata_pelajaran' => $withMataPelajaran,
            'most_used_jenjang' => $mostUsedJenjang ? [
                'nama' => $mostUsedJenjang->nama_jenjang,
                'kelas_count' => $mostUsedJenjang->kelas_count
            ] : null
        ];
    }

    public function getForDropdown(Request $request)
    {
        try {
            $query = Jenjang::active()->orderBy('urutan');
            
            if ($request->filled('with_counts')) {
                $query->withCount(['kelas', 'mataPelajaran']);
            }
            
            $jenjang = $query->get(['id_jenjang', 'nama_jenjang', 'kode_jenjang', 'urutan']);

            return response()->json([
                'success' => true,
                'message' => 'Data dropdown jenjang berhasil diambil',
                'data' => $jenjang
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dropdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkUrutanAvailability(Request $request)
    {
        try {
            $urutan = $request->get('urutan');
            $excludeId = $request->get('exclude_id');

            if (!$urutan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urutan harus diisi'
                ], 400);
            }

            $available = $this->service->validateJenjangUrutan($urutan, $excludeId);
            
            return response()->json([
                'success' => true,
                'message' => 'Status urutan berhasil dicek',
                'data' => [
                    'urutan' => (int) $urutan,
                    'available' => $available,
                    'exists' => !$available
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek urutan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getExistingUrutan()
    {
        try {
            $existingUrutan = Jenjang::pluck('urutan')->toArray();
            
            return response()->json([
                'success' => true,
                'message' => 'Urutan yang sudah ada berhasil diambil',
                'data' => $existingUrutan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data urutan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}