<?php

namespace App\Http\Controllers\API\AdminCabang\MasterData;

use App\Http\Controllers\API\Base\BaseCRUDController;
use App\Http\Controllers\API\Base\BaseService;
use App\Models\Kelas;
use App\Models\Jenjang;
use Illuminate\Http\Request;

class KelasController extends BaseCRUDController
{
    protected $modelClass = Kelas::class;
    protected $relationships = ['jenjang'];
    protected $searchableFields = ['nama_kelas', 'deskripsi', 'tingkat'];
    protected $filterableFields = ['id_jenjang', 'jenis_kelas', 'is_active'];
    protected $requiresKacabScope = false; // Kelas is global
    protected $validationRules = [
        'id_jenjang' => 'required|exists:jenjang,id_jenjang',
        'nama_kelas' => 'required|string|max:100',
        'jenis_kelas' => 'required|in:standard,custom',
        'tingkat' => 'nullable|integer|min:1|max:12',
        'urutan' => 'required|integer|min:1',
        'deskripsi' => 'nullable|string|max:500',
        'is_active' => 'boolean'
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

        if ($request->filled('jenis_kelas')) {
            $query->where('jenis_kelas', $request->jenis_kelas);
        }

        return $query->withCount('materi');
    }

    protected function applyDefaultOrdering($query)
    {
        return $query->orderBy('jenis_kelas')
                    ->orderBy('id_jenjang')
                    ->orderBy('urutan');
    }

    protected function customValidation(Request $request, $operation, $item = null)
    {
        // Validate tingkat for standard classes
        if ($request->jenis_kelas === 'standard' && !$request->tingkat) {
            return response()->json([
                'success' => false,
                'message' => 'Tingkat wajib diisi untuk kelas standard'
            ], 422);
        }

        $jenjangId = $request->id_jenjang ?? ($item ? $item->id_jenjang : null);
        $excludeId = $operation === 'update' ? $item->id_kelas : null;

        // Check duplicate for standard classes
        if ($request->jenis_kelas === 'standard' && $request->filled('tingkat')) {
            $isUnique = $this->service->validateKelasStandardUnique($jenjangId, $request->tingkat, $excludeId);
            if (!$isUnique) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas standard dengan tingkat tersebut sudah ada di jenjang ini'
                ], 422);
            }
        }

        // Check duplicate for custom classes
        if ($request->jenis_kelas === 'custom' && $request->filled('nama_kelas')) {
            $isUnique = $this->service->validateKelasCustomUnique($jenjangId, $request->nama_kelas, $excludeId);
            if (!$isUnique) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas custom dengan nama tersebut sudah ada di jenjang ini'
                ], 422);
            }
        }

        // Check urutan uniqueness within jenjang
        if ($request->filled('urutan')) {
            $isUnique = $this->service->validateKelasUrutanInJenjang($jenjangId, $request->urutan, $excludeId);
            if (!$isUnique) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urutan tersebut sudah digunakan di jenjang ini'
                ], 422);
            }
        }

        return true;
    }

    protected function prepareStoreData(Request $request)
    {
        $data = parent::prepareStoreData($request);
        $data['is_custom'] = $request->jenis_kelas === 'custom';
        
        if ($request->jenis_kelas === 'custom' && !$request->has('tingkat')) {
            $data['tingkat'] = null;
        }

        return $data;
    }

    protected function canBeDeleted($item)
    {
        $dependencies = $this->service->validateKelasDependencies($item->id_kelas, 'delete');
        if (!empty($dependencies)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus kelas yang masih memiliki materi'
            ], 422);
        }
        return true;
    }

    protected function calculateStatistics($query)
    {
        $total = $query->count();
        $active = $query->where('is_active', true)->count();
        
        $byJenis = $query->selectRaw('jenis_kelas, COUNT(*) as total')
            ->groupBy('jenis_kelas')
            ->pluck('total', 'jenis_kelas');
        
        $byJenjang = $query->with('jenjang:id_jenjang,nama_jenjang')
            ->selectRaw('id_jenjang, COUNT(*) as total')
            ->groupBy('id_jenjang')
            ->get();
        
        $withMateri = $query->has('materi')->count();

        return [
            'total_kelas' => $total,
            'total_kelas_aktif' => $active,
            'by_jenis' => $byJenis->toArray(),
            'by_jenjang' => $byJenjang,
            'with_materi' => $withMateri,
            'without_materi' => $total - $withMateri,
            'standard_vs_custom' => [
                'standard' => $byJenis['standard'] ?? 0,
                'custom' => $byJenis['custom'] ?? 0
            ]
        ];
    }

    public function getForDropdown(Request $request)
    {
        try {
            $query = Kelas::active()->with('jenjang:id_jenjang,nama_jenjang,kode_jenjang');
            
            if ($request->filled('id_jenjang')) {
                $query->where('id_jenjang', $request->id_jenjang);
            }
            
            if ($request->filled('jenis_kelas')) {
                $query->where('jenis_kelas', $request->jenis_kelas);
            }
            
            if ($request->filled('with_counts')) {
                $query->withCount('materi');
            }
            
            $kelas = $query->orderBy('jenis_kelas')
                ->orderBy('urutan')
                ->get(['id_kelas', 'nama_kelas', 'jenis_kelas', 'tingkat', 'urutan', 'id_jenjang']);

            return response()->json([
                'success' => true,
                'message' => 'Data dropdown kelas berhasil diambil',
                'data' => $kelas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dropdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByJenjang($jenjangId, Request $request)
    {
        try {
            $jenjang = Jenjang::findOrFail($jenjangId);
            
            $query = Kelas::where('id_jenjang', $jenjangId)->with('jenjang');
            
            if ($request->filled('jenis_kelas')) {
                $query->where('jenis_kelas', $request->jenis_kelas);
            }
            
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            
            if ($request->filled('with_counts')) {
                $query->withCount('materi');
            }
            
            $kelasStandard = (clone $query)->where('jenis_kelas', 'standard')->orderBy('urutan')->get();
            $kelasCustom = (clone $query)->where('jenis_kelas', 'custom')->orderBy('urutan')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diambil',
                'data' => [
                    'jenjang' => $jenjang,
                    'kelas_standard' => $kelasStandard,
                    'kelas_custom' => $kelasCustom,
                    'total_kelas' => $kelasStandard->count() + $kelasCustom->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCascadeData(Request $request)
    {
        try {
            $jenjang = Jenjang::active()->orderBy('urutan')->get(['id_jenjang', 'nama_jenjang', 'kode_jenjang']);
            
            $kelas = Kelas::active()
                ->with('jenjang:id_jenjang,nama_jenjang')
                ->orderBy('id_jenjang')
                ->orderBy('jenis_kelas')
                ->orderBy('urutan')
                ->get()
                ->groupBy('id_jenjang');
            
            $tingkatOptions = collect(range(1, 12))->map(function($num) {
                $romans = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
                          7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
                return ['value' => $num, 'label' => "{$num} ({$romans[$num]})"];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data cascade berhasil diambil',
                'data' => [
                    'jenjang' => $jenjang,
                    'kelas_by_jenjang' => $kelas,
                    'jenis_kelas_list' => ['standard' => 'Standard', 'custom' => 'Custom'],
                    'tingkat_options' => $tingkatOptions
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