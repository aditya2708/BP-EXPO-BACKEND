<?php

namespace App\Http\Controllers\API\AdminCabang\MasterData;

use App\Http\Controllers\API\Base\BaseCRUDController;
use App\Http\Controllers\API\Base\BaseService;
use App\Models\Materi;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Jenjang;
use Illuminate\Http\Request;

class MateriController extends BaseCRUDController
{
    protected $modelClass = Materi::class;
    protected $relationships = ['mataPelajaran.jenjang', 'kelas.jenjang'];
    protected $searchableFields = ['nama_materi'];
    protected $filterableFields = ['id_kelas', 'id_mata_pelajaran'];
    protected $requiresKacabScope = false; // Handled via mataPelajaran relationship
    protected $validationRules = [
        'id_mata_pelajaran' => 'required|exists:mata_pelajaran,id_mata_pelajaran',
        'id_kelas' => 'required|exists:kelas,id_kelas',
        'nama_materi' => 'required|string|max:255'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->service = new BaseService($this->modelClass);
    }

    protected function applyCabangScope($query, $kacabId)
    {
        return $query->whereHas('mataPelajaran', function($q) use ($kacabId) {
            $q->where('id_kacab', $kacabId);
        });
    }

    protected function applyAdditionalScopes($query, Request $request)
    {
        if ($request->filled('id_kelas')) {
            $query->where('id_kelas', $request->id_kelas);
        }
        
        if ($request->filled('id_jenjang')) {
            $query->whereHas('kelas', function($q) use ($request) {
                $q->where('id_jenjang', $request->id_jenjang);
            });
        }
        
        if ($request->filled('id_mata_pelajaran')) {
            $query->where('id_mata_pelajaran', $request->id_mata_pelajaran);
        }
        
        return $query->withCount('kurikulumMateri');
    }

    protected function applyDefaultOrdering($query)
    {
        return $query->orderBy('id_mata_pelajaran')
                    ->orderBy('id_kelas')
                    ->orderBy('nama_materi');
    }

    protected function customValidation(Request $request, $operation, $item = null)
    {
        $adminCabang = auth()->user()->adminCabang;
        
        // Validate mata pelajaran belongs to admin cabang
        $mataPelajaran = MataPelajaran::where('id_kacab', $adminCabang->id_kacab)
            ->find($request->id_mata_pelajaran);
        
        if (!$mataPelajaran) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak ditemukan atau tidak dapat diakses'
            ], 404);
        }

        // Validate kelas exists
        $kelas = Kelas::with('jenjang')->find($request->id_kelas);
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        // Ensure mata pelajaran and kelas belong to same jenjang (if mata pelajaran has specific jenjang)
        if ($mataPelajaran->id_jenjang && $mataPelajaran->id_jenjang !== $kelas->id_jenjang) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran dan kelas harus berada dalam jenjang yang sama'
            ], 422);
        }

        // Check for duplicate materi
        $excludeId = $operation === 'update' ? $item->id_materi : null;
        $isUnique = $this->service->validateMateriUnique(
            $request->id_mata_pelajaran, 
            $request->id_kelas, 
            $request->nama_materi, 
            $excludeId
        );

        if (!$isUnique) {
            return response()->json([
                'success' => false,
                'message' => 'Materi dengan nama tersebut sudah ada untuk kombinasi mata pelajaran dan kelas ini'
            ], 422);
        }

        return true;
    }

    protected function canBeDeleted($item)
    {
        $dependencies = $this->service->validateMateriDependencies($item->id_materi, 'delete');
        if (!empty($dependencies)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus materi yang masih digunakan dalam kurikulum'
            ], 422);
        }
        return true;
    }

    protected function calculateStatistics($query)
    {
        $adminCabang = auth()->user()->adminCabang;
        $total = $query->count();
        
        $byMataPelajaran = $query->with('mataPelajaran')
            ->get()
            ->groupBy('mataPelajaran.nama_mata_pelajaran')
            ->map->count();
        
        $byKelas = $query->with('kelas')
            ->get()
            ->groupBy('kelas.display_name')
            ->map->count();
        
        $usedInKurikulum = $query->has('kurikulumMateri')->count();

        return [
            'total_materi' => $total,
            'by_mata_pelajaran' => $byMataPelajaran,
            'by_kelas' => $byKelas,
            'used_in_kurikulum' => $usedInKurikulum,
            'not_used_in_kurikulum' => $total - $usedInKurikulum,
            'usage_percentage' => $total > 0 ? round(($usedInKurikulum / $total) * 100, 2) : 0
        ];
    }

    public function getForDropdown(Request $request)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $query = Materi::with(['mataPelajaran:id_mata_pelajaran,nama_mata_pelajaran', 'kelas:id_kelas,nama_kelas'])
                ->whereHas('mataPelajaran', function($q) use ($adminCabang) {
                    $q->where('id_kacab', $adminCabang->id_kacab);
                });
            
            if ($request->filled('id_mata_pelajaran')) {
                $query->where('id_mata_pelajaran', $request->id_mata_pelajaran);
            }
            
            if ($request->filled('id_kelas')) {
                $query->where('id_kelas', $request->id_kelas);
            }
            
            if ($request->filled('id_jenjang')) {
                $query->whereHas('kelas', function($q) use ($request) {
                    $q->where('id_jenjang', $request->id_jenjang);
                });
            }
            
            $materi = $query->orderBy('nama_materi')
                ->get(['id_materi', 'nama_materi', 'id_mata_pelajaran', 'id_kelas']);

            return response()->json([
                'success' => true,
                'message' => 'Data dropdown materi berhasil diambil',
                'data' => $materi
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dropdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByKelas($kelasId, Request $request)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $kelas = Kelas::with('jenjang')->findOrFail($kelasId);
            
            $query = Materi::where('id_kelas', $kelasId)
                ->with(['mataPelajaran.jenjang'])
                ->whereHas('mataPelajaran', function($q) use ($adminCabang) {
                    $q->where('id_kacab', $adminCabang->id_kacab);
                });
            
            if ($request->filled('id_mata_pelajaran')) {
                $query->where('id_mata_pelajaran', $request->id_mata_pelajaran);
            }
            
            if ($request->filled('with_counts')) {
                $query->withCount('kurikulumMateri');
            }
            
            $materi = $query->orderBy('id_mata_pelajaran')->orderBy('nama_materi')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data materi berhasil diambil',
                'data' => [
                    'kelas' => $kelas,
                    'materi' => $materi,
                    'total_materi' => $materi->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByMataPelajaran(Request $request)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            
            $request->validate([
                'id_mata_pelajaran' => 'required|exists:mata_pelajaran,id_mata_pelajaran'
            ]);

            $mataPelajaran = MataPelajaran::with('jenjang')
                ->where('id_kacab', $adminCabang->id_kacab)
                ->findOrFail($request->id_mata_pelajaran);
            
            $query = Materi::where('id_mata_pelajaran', $request->id_mata_pelajaran)
                ->with(['kelas.jenjang']);
            
            if ($request->filled('id_kelas')) {
                $query->where('id_kelas', $request->id_kelas);
            }
            
            if ($request->filled('with_counts')) {
                $query->withCount('kurikulumMateri');
            }
            
            $materi = $query->orderBy('id_kelas')->orderBy('nama_materi')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data materi berhasil diambil',
                'data' => [
                    'mata_pelajaran' => $mataPelajaran,
                    'materi' => $materi,
                    'materi_by_kelas' => $materi->groupBy('kelas.nama_kelas'),
                    'total_materi' => $materi->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCascadeData(Request $request)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            
            $jenjang = Jenjang::active()->orderBy('urutan')->get(['id_jenjang', 'nama_jenjang', 'kode_jenjang']);
            
            $mataPelajaran = MataPelajaran::where('id_kacab', $adminCabang->id_kacab)
                ->with('jenjang:id_jenjang,nama_jenjang')
                ->get()
                ->groupBy('id_jenjang');
            
            $kelas = Kelas::active()
                ->with('jenjang:id_jenjang,nama_jenjang')
                ->get()
                ->groupBy('id_jenjang');

            return response()->json([
                'success' => true,
                'message' => 'Data cascade berhasil diambil',
                'data' => [
                    'jenjang' => $jenjang,
                    'mata_pelajaran_by_jenjang' => $mataPelajaran,
                    'kelas_by_jenjang' => $kelas
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data cascade',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}