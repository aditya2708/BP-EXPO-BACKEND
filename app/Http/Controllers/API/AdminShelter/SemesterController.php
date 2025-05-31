<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    /**
     * Display a listing of semester
     */
    public function index(Request $request)
    {
        try {
            $query = Semester::query();
            
            // Filter by tahun ajaran
            if ($request->has('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            
            // Filter by periode
            if ($request->has('periode')) {
                $query->where('periode', $request->periode);
            }
            
            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            
            $semesters = $query->orderBy('tahun_ajaran', 'desc')
                ->orderBy('periode', 'desc')
                ->paginate(20);
            
            return response()->json([
                'success' => true,
                'message' => 'Data semester berhasil diambil',
                'data' => $semesters
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created semester
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama_semester' => 'required|string|max:255',
                'tahun_ajaran' => 'required|string|max:20',
                'periode' => 'required|in:ganjil,genap',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'required|date|after:tanggal_mulai',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            // If setting as active, deactivate others
            if ($request->is_active) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }

            $semester = Semester::create($request->all());
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil ditambahkan',
                'data' => $semester
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified semester
     */
    public function show($id)
    {
        try {
            $semester = Semester::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Detail semester berhasil diambil',
                'data' => $semester
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Semester tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified semester
     */
    public function update(Request $request, $id)
    {
        try {
            $semester = Semester::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'nama_semester' => 'sometimes|required|string|max:255',
                'tahun_ajaran' => 'sometimes|required|string|max:20',
                'periode' => 'sometimes|required|in:ganjil,genap',
                'tanggal_mulai' => 'sometimes|required|date',
                'tanggal_selesai' => 'sometimes|required|date|after:tanggal_mulai',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            // If setting as active, deactivate others
            if ($request->has('is_active') && $request->is_active) {
                Semester::where('is_active', true)
                    ->where('id_semester', '!=', $id)
                    ->update(['is_active' => false]);
            }

            $semester->update($request->all());
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil diperbarui',
                'data' => $semester
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified semester
     */
    public function destroy($id)
    {
        try {
            $semester = Semester::findOrFail($id);
            
            // Check if semester has related data
            if ($semester->penilaian()->exists() || 
                $semester->nilaiSikap()->exists() || 
                $semester->raport()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semester tidak dapat dihapus karena memiliki data terkait'
                ], 403);
            }
            
            $semester->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active semester
     */
    public function getActive()
    {
        try {
            $semester = Semester::where('is_active', true)->first();
            
            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada semester aktif'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Semester aktif berhasil diambil',
                'data' => $semester
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil semester aktif',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set semester as active
     */
    public function setActive($id)
    {
        try {
            $semester = Semester::findOrFail($id);
            
            DB::beginTransaction();
            
            // Deactivate all other semesters
            Semester::where('is_active', true)->update(['is_active' => false]);
            
            // Activate this semester
            $semester->update(['is_active' => true]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil diaktifkan',
                'data' => $semester
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get semester statistics
     */
    public function statistics($id)
    {
        try {
            $semester = Semester::findOrFail($id);
            
            $stats = [
                'total_anak_with_raport' => $semester->raport()->count(),
                'total_anak_with_nilai_sikap' => $semester->nilaiSikap()->count(),
                'total_penilaian' => $semester->penilaian()->count(),
                'published_raport' => $semester->raport()->where('status', 'published')->count(),
                'draft_raport' => $semester->raport()->where('status', 'draft')->count(),
                'average_attendance' => $semester->raport()->avg('persentase_kehadiran') ?? 0
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Statistik semester berhasil diambil',
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all years for dropdown
     */
    public function getTahunAjaran()
    {
        try {
            $years = Semester::select('tahun_ajaran')
                ->distinct()
                ->orderBy('tahun_ajaran', 'desc')
                ->pluck('tahun_ajaran');
            
            return response()->json([
                'success' => true,
                'message' => 'Data tahun ajaran berhasil diambil',
                'data' => $years
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tahun ajaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}