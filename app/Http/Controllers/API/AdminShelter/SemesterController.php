<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Kurikulum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    public function index(Request $request)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $query = Semester::with(['kurikulum', 'shelter'])
                ->byShelter($adminShelter->id_shelter);
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama_semester', 'like', "%{$search}%")
                      ->orWhere('tahun_ajaran', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('tahun_ajaran')) {
                $query->where('tahun_ajaran', $request->tahun_ajaran);
            }
            
            if ($request->has('periode')) {
                $query->where('periode', $request->periode);
            }
            
            $semester = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));
            
            return response()->json([
                'success' => true,
                'message' => 'Data semester berhasil diambil',
                'data' => $semester
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            
            $validator = Validator::make($request->all(), [
                'nama_semester' => 'required|string|max:255',
                'tahun_ajaran' => 'required|string|max:20',
                'periode' => 'required|in:ganjil,genap',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'required|date|after:tanggal_mulai',
                'kurikulum_id' => 'nullable|exists:kurikulum,id_kurikulum',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate kurikulum belongs to same kacab as shelter
            if ($request->kurikulum_id) {
                $kurikulum = Kurikulum::find($request->kurikulum_id);
                if ($kurikulum && $kurikulum->id_kacab !== $adminShelter->shelter->wilbin->id_kacab) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kurikulum tidak valid untuk shelter ini'
                    ], 422);
                }
            }

            // Check for duplicate semester
            $exists = Semester::byShelter($adminShelter->id_shelter)
                ->where('tahun_ajaran', $request->tahun_ajaran)
                ->where('periode', $request->periode)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semester dengan tahun ajaran dan periode yang sama sudah ada'
                ], 422);
            }

            DB::beginTransaction();

            // If setting as active, deactivate others
            if ($request->get('is_active', false)) {
                Semester::byShelter($adminShelter->id_shelter)
                    ->update(['is_active' => false]);
            }

            $semester = Semester::create([
                'nama_semester' => $request->nama_semester,
                'tahun_ajaran' => $request->tahun_ajaran,
                'periode' => $request->periode,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'kurikulum_id' => $request->kurikulum_id,
                'is_active' => $request->get('is_active', false),
                'id_shelter' => $adminShelter->id_shelter
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil dibuat',
                'data' => $semester->load(['kurikulum', 'shelter'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $semester = Semester::with(['kurikulum.kurikulumMateri.materi', 'shelter'])
                ->byShelter($adminShelter->id_shelter)
                ->findOrFail($id);
            
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

    public function update(Request $request, $id)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $semester = Semester::byShelter($adminShelter->id_shelter)
                ->findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'nama_semester' => 'sometimes|required|string|max:255',
                'tahun_ajaran' => 'sometimes|required|string|max:20',
                'periode' => 'sometimes|required|in:ganjil,genap',
                'tanggal_mulai' => 'sometimes|required|date',
                'tanggal_selesai' => 'sometimes|required|date|after:tanggal_mulai',
                'kurikulum_id' => 'nullable|exists:kurikulum,id_kurikulum',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate kurikulum belongs to same kacab as shelter
            if ($request->has('kurikulum_id') && $request->kurikulum_id) {
                $kurikulum = Kurikulum::find($request->kurikulum_id);
                if ($kurikulum && $kurikulum->id_kacab !== $adminShelter->shelter->wilbin->id_kacab) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kurikulum tidak valid untuk shelter ini'
                    ], 422);
                }
            }

            // Check for duplicate if tahun_ajaran or periode being updated
            if ($request->has('tahun_ajaran') || $request->has('periode')) {
                $tahun = $request->tahun_ajaran ?? $semester->tahun_ajaran;
                $periode = $request->periode ?? $semester->periode;
                
                $exists = Semester::byShelter($adminShelter->id_shelter)
                    ->where('tahun_ajaran', $tahun)
                    ->where('periode', $periode)
                    ->where('id_semester', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Semester dengan tahun ajaran dan periode yang sama sudah ada'
                    ], 422);
                }
            }

            DB::beginTransaction();

            // If setting as active, deactivate others
            if ($request->get('is_active', false)) {
                Semester::byShelter($adminShelter->id_shelter)
                    ->where('id_semester', '!=', $id)
                    ->update(['is_active' => false]);
            }

            $semester->update($request->only([
                'nama_semester', 'tahun_ajaran', 'periode', 
                'tanggal_mulai', 'tanggal_selesai', 
                'kurikulum_id', 'is_active'
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil diperbarui',
                'data' => $semester->load(['kurikulum', 'shelter'])
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

    public function destroy($id)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $semester = Semester::byShelter($adminShelter->id_shelter)
                ->findOrFail($id);
            
            // Check if semester is being used
            $hasRelations = $semester->kelas()->exists() || 
                           $semester->jadwal()->exists();
            
            if ($hasRelations) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semester tidak dapat dihapus karena sedang digunakan'
                ], 422);
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

    public function getActive()
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $semester = Semester::with(['kurikulum'])
                ->byShelter($adminShelter->id_shelter)
                ->where('is_active', true)
                ->first();
            
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

    public function setActive($id)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            
            DB::beginTransaction();
            
            // Deactivate all semesters for this shelter
            Semester::byShelter($adminShelter->id_shelter)
                ->update(['is_active' => false]);
            
            // Activate the selected semester
            $semester = Semester::byShelter($adminShelter->id_shelter)
                ->findOrFail($id);
            $semester->update(['is_active' => true]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil diaktifkan',
                'data' => $semester->load(['kurikulum', 'shelter'])
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

    public function getTahunAjaran()
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $tahunAjaran = Semester::byShelter($adminShelter->id_shelter)
                ->distinct()
                ->pluck('tahun_ajaran')
                ->sort()
                ->values();
            
            return response()->json([
                'success' => true,
                'message' => 'Data tahun ajaran berhasil diambil',
                'data' => $tahunAjaran
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tahun ajaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statistics($id)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $semester = Semester::byShelter($adminShelter->id_shelter)
                ->findOrFail($id);
            
            // Temporarily disabled - database relationships not ready
            $stats = [
                'total_kelas' => 0, // $semester->kelas()->count(),
                'total_siswa' => 0, // $semester->kelas()->withCount('siswa')->sum('siswa_count'),
                'total_jadwal' => 0, // $semester->jadwal()->count(),
                'periode_aktif' => now()->between($semester->tanggal_mulai, $semester->tanggal_selesai),
                'note' => 'Statistics temporarily disabled - database relationships being updated'
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Statistik semester berhasil diambil (sementara dinonaktifkan)',
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
}