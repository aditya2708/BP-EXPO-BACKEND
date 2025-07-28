<?php

namespace App\Http\Controllers\API\AdminCabang;

use App\Http\Controllers\API\Base\BaseCRUDController;
use App\Http\Controllers\API\Base\BaseService;
use App\Models\Kurikulum;
use App\Models\KurikulumMateri;
use App\Models\Materi;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AkademikKurikulumController extends BaseCRUDController
{
    protected $modelClass = Kurikulum::class;
    protected $relationships = ['kacab', 'jenjang', 'mataPelajaran', 'kurikulumMateri.materi.mataPelajaran', 'kurikulumMateri.materi.kelas.jenjang', 'semester'];
    protected $searchableFields = ['nama_kurikulum', 'deskripsi'];
    protected $filterableFields = ['tahun_berlaku', 'is_active', 'status'];
    protected $requiresKacabScope = true;
    protected $validationRules = [
        'nama_kurikulum' => 'required|string|max:255',
        'kode_kurikulum' => 'required|string|max:50',
        'tahun_berlaku' => 'required|integer|min:2020|max:2030',
        'id_jenjang' => 'required|integer|exists:jenjang,id_jenjang',
        'id_mata_pelajaran' => 'required|integer|exists:mata_pelajaran,id_mata_pelajaran',
        'deskripsi' => 'nullable|string|max:1000',
        'tujuan' => 'nullable|string|max:1000',
        'tanggal_mulai' => 'required|date',
        'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        'is_active' => 'boolean'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->service = new BaseService($this->modelClass);
    }

    protected function applyAdditionalScopes($query, Request $request)
    {
        return $query->withCount(['kurikulumMateri', 'semester', 'kurikulumMateri as total_mata_pelajaran' => function($q) {
            $q->selectRaw('COUNT(DISTINCT id_mata_pelajaran)');
        }]);
    }

    protected function applyDefaultOrdering($query)
    {
        return $query->orderBy('tahun_berlaku', 'desc')
                    ->orderBy('is_active', 'desc')
                    ->orderBy('nama_kurikulum');
    }

    protected function customValidation(Request $request, $operation, $item = null)
    {
        $adminCabang = auth()->user()->adminCabang;
        $excludeId = $operation === 'update' ? $item->id_kurikulum : null;

        // Check unique nama_kurikulum per tahun_berlaku
        $nama = $request->nama_kurikulum ?? ($item ? $item->nama_kurikulum : null);
        $tahun = $request->tahun_berlaku ?? ($item ? $item->tahun_berlaku : null);
        
        if ($nama && $tahun) {
            $exists = Kurikulum::where('id_kacab', $adminCabang->id_kacab)
                ->where('nama_kurikulum', $nama)
                ->where('tahun_berlaku', $tahun)
                ->when($excludeId, function($q) use ($excludeId) {
                    $q->where('id_kurikulum', '!=', $excludeId);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum dengan nama tersebut sudah ada untuk tahun yang sama'
                ], 422);
            }
        }

        // Check unique kode_kurikulum
        if ($request->filled('kode_kurikulum')) {
            $exists = Kurikulum::where('id_kacab', $adminCabang->id_kacab)
                ->where('kode_kurikulum', $request->kode_kurikulum)
                ->when($excludeId, function($q) use ($excludeId) {
                    $q->where('id_kurikulum', '!=', $excludeId);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode kurikulum sudah digunakan'
                ], 422);
            }
        }

        return true;
    }

    protected function prepareStoreData(Request $request)
    {
        $data = parent::prepareStoreData($request);
        $data['status'] = $request->get('is_active', false) ? 'aktif' : 'draft';
        return $data;
    }

    protected function prepareUpdateData(Request $request, $item)
    {
        $data = parent::prepareUpdateData($request, $item);
        
        if ($request->has('status')) {
            $data['is_active'] = $request->status === 'aktif';
        } elseif ($request->has('is_active')) {
            $data['status'] = $request->boolean('is_active') ? 'aktif' : 'nonaktif';
        }
        
        return $data;
    }

    protected function canBeDeleted($item)
    {
        if ($item->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Kurikulum aktif tidak dapat dihapus'
            ], 422);
        }

        if ($item->semester()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kurikulum tidak dapat dihapus karena sedang digunakan oleh semester'
            ], 422);
        }

        return true;
    }

    protected function afterDelete($item)
    {
        $item->kurikulumMateri()->delete();
    }

    protected function calculateStatistics($query)
    {
        $adminCabang = auth()->user()->adminCabang;
        $total = $query->count();
        $assignedMateri = KurikulumMateri::whereHas('kurikulum', function($q) use ($adminCabang) {
            $q->where('id_kacab', $adminCabang->id_kacab);
        })->count();
        
        $activeSemester = Semester::whereHas('kurikulum', function($q) use ($adminCabang) {
            $q->where('id_kacab', $adminCabang->id_kacab);
        })->where('is_active', true)->count();

        return [
            'kurikulum' => $total,
            'assigned_materi' => $assignedMateri,
            'active_semester' => $activeSemester,
            'total_pembelajaran' => $assignedMateri
        ];
    }

    public function getGeneralStatistics(Request $request)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $stats = $this->calculateStatistics(Kurikulum::where('id_kacab', $adminCabang->id_kacab));
            
            $recentKurikulum = Kurikulum::with(['kacab', 'jenjang', 'mataPelajaran'])
                ->where('id_kacab', $adminCabang->id_kacab)
                ->withCount(['kurikulumMateri as materi_count', 'semester'])
                ->latest('created_at')
                ->limit(5)
                ->get();

            $stats['recent_kurikulum'] = $recentKurikulum;

            return response()->json([
                'success' => true,
                'message' => 'Statistik akademik berhasil diambil',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik akademik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function assignMateri(Request $request, $id)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $kurikulum = Kurikulum::where('id_kacab', $adminCabang->id_kacab)->findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'materi_ids' => 'required|array|min:1',
                'materi_ids.*' => 'required|integer|exists:materi,id_materi'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            $result = $this->service->assignMateriToKurikulum($id, $request->materi_ids);
            DB::commit();

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            $kurikulum->load($this->relationships);
            $kurikulum->loadCount(['kurikulumMateri', 'semester', 'kurikulumMateri as total_mata_pelajaran' => function($q) {
                $q->selectRaw('COUNT(DISTINCT id_mata_pelajaran)');
            }]);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $kurikulum,
                'meta' => ['assigned_count' => $result['assigned_count'], 'total_assigned' => $kurikulum->kurikulumMateri()->count()]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal assign materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeMateri($id, $materiId)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $kurikulum = Kurikulum::where('id_kacab', $adminCabang->id_kacab)->findOrFail($id);
            $kurikulumMateri = $kurikulum->kurikulumMateri()->where('id_materi', $materiId)->first();

            if (!$kurikulumMateri) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan dalam kurikulum ini'
                ], 404);
            }

            DB::beginTransaction();
            $kurikulumMateri->delete();
            $kurikulum->kurikulumMateri()->where('urutan', '>', $kurikulumMateri->urutan)->decrement('urutan');
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Materi berhasil dihapus dari kurikulum'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reorderMateri(Request $request, $id)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $kurikulum = Kurikulum::where('id_kacab', $adminCabang->id_kacab)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'materi_orders' => 'required|array',
                'materi_orders.*.id_materi' => 'required|integer',
                'materi_orders.*.urutan' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            $this->service->reorderKurikulumMateri($id, $request->materi_orders);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Urutan materi berhasil diperbarui'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah urutan materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAvailableMateri(Request $request, $id)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $kurikulum = Kurikulum::where('id_kacab', $adminCabang->id_kacab)->findOrFail($id);

            $filters = [
                'id_mata_pelajaran' => $request->mata_pelajaran_id,
                'id_kelas' => $request->kelas_id
            ];

            $query = $this->service->getAvailableMateri($id, $filters)
                ->when($request->filled('search'), function($q) use ($request) {
                    $q->where('nama_materi', 'like', "%{$request->search}%");
                });

            $materi = $query->orderBy('nama_materi')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Data materi tersedia berhasil diambil',
                'data' => $materi
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function setActive($id)
    {
        try {
            $adminCabang = auth()->user()->adminCabang;
            $kurikulum = Kurikulum::where('id_kacab', $adminCabang->id_kacab)->findOrFail($id);

            if ($kurikulum->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum sudah aktif'
                ], 422);
            }

            DB::beginTransaction();
            Kurikulum::where('id_kacab', $adminCabang->id_kacab)->update(['is_active' => false, 'status' => 'nonaktif']);
            $kurikulum->update(['is_active' => true, 'status' => 'aktif']);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil diaktifkan',
                'data' => $kurikulum->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan kurikulum',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}