<?php

namespace App\Http\Controllers\API\AdminCabang\MasterData;

use App\Http\Controllers\API\Base\BaseCRUDController;
use App\Http\Controllers\API\Base\BaseService;
use App\Models\MataPelajaran;
use App\Models\Jenjang;
use Illuminate\Http\Request;

class MataPelajaranController extends BaseCRUDController
{
    protected $modelClass = MataPelajaran::class;
    protected $relationships = ['jenjang', 'kacab'];
    protected $searchableFields = ['nama_mata_pelajaran', 'kode_mata_pelajaran'];
    protected $filterableFields = ['id_jenjang', 'kategori', 'status'];
    protected $requiresKacabScope = true;
    protected $validationRules = [
        'id_jenjang' => 'nullable|exists:jenjang,id_jenjang',
        'nama_mata_pelajaran' => 'required|string|max:255',
        'kode_mata_pelajaran' => 'required|string|max:50|unique:mata_pelajaran,kode_mata_pelajaran',
        'kategori' => 'required|in:wajib,muatan_lokal,pengembangan_diri,pilihan,ekstrakurikuler',
        'deskripsi' => 'nullable|string'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->service = new BaseService($this->modelClass);
    }

    protected function applyAdditionalScopes($query, Request $request)
    {
        if ($request->filled('id_jenjang')) {
            $query->where('id_jenjang', $request->id_jenjang);
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->withCount(['materi', 'kurikulumMateri']);
    }

    protected function applyDefaultOrdering($query)
    {
        return $query->orderBy('id_jenjang')
                    ->orderBy('kategori')
                    ->orderBy('nama_mata_pelajaran');
    }

    protected function customValidation(Request $request, $operation, $item = null)
    {
        if ($request->filled('id_jenjang')) {
            $jenjang = Jenjang::find($request->id_jenjang);
            if (!$jenjang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenjang tidak ditemukan'
                ], 404);
            }
        }

        return true;
    }

    protected function prepareStoreData(Request $request)
    {
        $data = parent::prepareStoreData($request);
        $data['status'] = 'aktif';
        return $data;
    }

    protected function canBeDeleted($item)
    {
        $dependencies = $this->service->validateMataPelajaranDependencies($item->id_mata_pelajaran, 'delete');
        if (!empty($dependencies)) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak dapat dihapus karena sedang digunakan'
            ], 422);
        }
        return true;
    }

    protected function calculateStatistics($query)
    {
        $adminCabang = auth()->user()->adminCabang;
        $total = $query->count();
        
        if ($total === 0) {
            return [
                'total_mata_pelajaran' => 0,
                'active_mata_pelajaran' => 0,
                'inactive_mata_pelajaran' => 0,
                'by_kategori' => [],
                'by_jenjang' => [],
                'with_materi' => 0,
                'without_materi' => 0
            ];
        }

        $byStatus = $query->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byKategori = $query->selectRaw('kategori, COUNT(*) as total')
            ->groupBy('kategori')
            ->pluck('total', 'kategori');

        $byJenjang = $query->with('jenjang:id_jenjang,nama_jenjang')
            ->selectRaw('id_jenjang, COUNT(*) as total')
            ->groupBy('id_jenjang')
            ->get()
            ->map(function($item) {
                return [
                    'jenjang_name' => $item->jenjang->nama_jenjang ?? 'Semua Jenjang',
                    'total' => $item->total
                ];
            });

        $withMateri = $query->has('materi')->count();

        return [
            'total_mata_pelajaran' => $total,
            'active_mata_pelajaran' => $byStatus->get('aktif', 0),
            'inactive_mata_pelajaran' => $byStatus->get('nonaktif', 0),
            'by_kategori' => $byKategori->toArray(),
            'by_jenjang' => $byJenjang->toArray(),
            'with_materi' => $withMateri,
            'without_materi' => $total - $withMateri
        ];
    }

    public function getForDropdown()
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            
            $mataPelajaran = MataPelajaran::where('id_kacab', $adminCabang->id_kacab)
                ->where('status', 'aktif')
                ->select('id_mata_pelajaran', 'nama_mata_pelajaran', 'kode_mata_pelajaran')
                ->orderBy('nama_mata_pelajaran')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Dropdown mata pelajaran berhasil diambil',
                'data' => $mataPelajaran
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dropdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByJenjang($jenjangId)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $jenjang = Jenjang::findOrFail($jenjangId);

            $mataPelajaran = MataPelajaran::where('id_jenjang', $jenjangId)
                ->where('id_kacab', $adminCabang->id_kacab)
                ->where('status', 'aktif')
                ->select('id_mata_pelajaran', 'nama_mata_pelajaran', 'kode_mata_pelajaran')
                ->orderBy('nama_mata_pelajaran')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran by jenjang retrieved successfully',
                'data' => $mataPelajaran
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data mata pelajaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCascadeData()
    {
        try {
            $jenjangList = Jenjang::active()
                ->select('id_jenjang', 'nama_jenjang')
                ->orderBy('nama_jenjang')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Cascade data retrieved successfully',
                'data' => [
                    'jenjang' => $jenjangList,
                    'kategori_options' => [
                        ['value' => 'wajib', 'label' => 'Mata Pelajaran Wajib'],
                        ['value' => 'muatan_lokal', 'label' => 'Muatan Lokal'],
                        ['value' => 'pengembangan_diri', 'label' => 'Pengembangan Diri'],
                        ['value' => 'pilihan', 'label' => 'Mata Pelajaran Pilihan'],
                        ['value' => 'ekstrakurikuler', 'label' => 'Ekstrakurikuler']
                    ]
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