<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminShelter\StoreKeluargaRequest;
use App\Http\Requests\AdminShelter\UpdateKeluargaRequest;
use App\Http\Resources\AdminShelter\KeluargaCollection;
use App\Http\Resources\AdminShelter\KeluargaResource;
use App\Services\AdminShelter\KeluargaService;
use App\Services\AdminShelter\KeluargaValidationService;
use App\Models\AnakPendidikan;
use App\Models\Anak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminShelterKeluargaController extends Controller
{
    protected KeluargaService $keluargaService;
    protected KeluargaValidationService $validationService;

    public function __construct(
        KeluargaService $keluargaService,
        KeluargaValidationService $validationService
    ) {
        $this->keluargaService = $keluargaService;
        $this->validationService = $validationService;
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->validationService->validateAuthAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $result = $this->keluargaService->getKeluargaList($request);
            
            return response()->json([
                'success' => true,
                'message' => 'Daftar Keluarga',
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching keluarga list: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        if (!$this->validationService->validateAuthAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $data = $this->keluargaService->getKeluargaDetail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Detail Keluarga',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching keluarga detail: ' . $e->getMessage()
            ], 404);
        }
    }

    public function store(StoreKeluargaRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->keluargaService->createKeluarga($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Keluarga dan Anak berhasil ditambahkan',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateKeluargaRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $data = $request->validated();
            $keluarga = $this->keluargaService->updateKeluarga($id, $data);
            
            // Handle education data update if present
            if ($this->hasEducationData($request)) {
                $this->updateEducationData($keluarga, $request);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Data keluarga berhasil diperbarui',
                'data' => new KeluargaResource($keluarga)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        if (!$this->validationService->validateAuthAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $responseData = $this->keluargaService->deleteKeluarga($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Keluarga berhasil dihapus',
                'data' => $responseData
            ], 200);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'HAS_ACTIVE_CHILDREN') {
                return response()->json([
                    'success' => false,
                    'message' => 'Keluarga memiliki anak aktif yang terdaftar',
                    'code' => 'HAS_ACTIVE_CHILDREN'
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus keluarga: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDestroy($id): JsonResponse
    {
        if (!$this->validationService->validateAuthAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $result = $this->keluargaService->forceDeleteKeluarga($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Keluarga berhasil dihapus dan ' . $result['affected_children'] . ' anak diubah statusnya menjadi tanpa keluarga',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus keluarga: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getDropdownData(): JsonResponse
    {
        try {
            $data = $this->validationService->getDropdownData();
            
            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dropdown data: ' . $e->getMessage()
            ], 500);
        } 
    }
    
    public function getWilbinByKacab(Request $request, $id_kacab): JsonResponse
    {
        try {
            $wilbin = $this->validationService->getWilbinByKacab($id_kacab);
            
            return response()->json([
                'success' => true,
                'data' => $wilbin
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching wilbin data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getShelterByWilbin(Request $request, $id_wilbin): JsonResponse
    {
        if (!$this->validationService->validateAuthAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        try {
            $shelter = $this->validationService->getShelterByWilbin($id_wilbin);
            
            return response()->json([
                'success' => true,
                'data' => $shelter
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching shelter data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function hasEducationData(Request $request): bool
    {
        $educationFields = ['jenjang', 'kelas', 'nama_sekolah', 'alamat_sekolah', 'jurusan', 'semester', 'nama_pt', 'alamat_pt'];
        
        return collect($educationFields)->some(function ($field) use ($request) {
            return $request->has($field) && !empty($request->input($field));
        });
    }

    private function updateEducationData($keluarga, Request $request): void
    {
        // Get the first child from the family
        $anak = Anak::where('id_keluarga', $keluarga->id_keluarga)->first();
        
        if (!$anak) {
            throw new \Exception('Child data not found for this family');
        }

        $educationData = [
            'id_keluarga' => $keluarga->id_keluarga,
            'jenjang' => $request->input('jenjang'),
            'kelas' => $request->input('kelas'),
            'nama_sekolah' => $request->input('nama_sekolah'),
            'alamat_sekolah' => $request->input('alamat_sekolah'),
            'jurusan' => $request->input('jurusan'),
            'semester' => $request->input('semester') ? (int) $request->input('semester') : null,
            'nama_pt' => $request->input('nama_pt'),
            'alamat_pt' => $request->input('alamat_pt'),
        ];

        // Remove null values
        $educationData = array_filter($educationData, function ($value) {
            return $value !== null && $value !== '';
        });

        // Update or create education record
        AnakPendidikan::updateOrCreate(
            ['id_keluarga' => $keluarga->id_keluarga],
            $educationData
        );
    }
}