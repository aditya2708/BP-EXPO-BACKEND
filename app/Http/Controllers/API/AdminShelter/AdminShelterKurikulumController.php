<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use Illuminate\Http\Request;

class AdminShelterKurikulumController extends Controller
{
    public function index(Request $request)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $query = Kurikulum::with(['kacab'])
                ->byKacab($adminShelter->shelter->wilbin->id_kacab)
                ->active();
            
            if ($request->has('tahun_berlaku')) {
                $query->byTahun($request->tahun_berlaku);
            }
            
            if ($request->has('search')) {
                $query->where('nama_kurikulum', 'like', '%' . $request->search . '%');
            }
            
            $kurikulum = $query->withCount(['kurikulumMateri', 'mataPelajaran'])
                ->orderBy('tahun_berlaku', 'desc')
                ->orderBy('nama_kurikulum')
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data kurikulum berhasil diambil',
                'data' => $kurikulum
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kurikulum',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $kurikulum = Kurikulum::with(['kacab', 'kurikulumMateri.mataPelajaran', 'kurikulumMateri.materi'])
                ->byKacab($adminShelter->shelter->wilbin->id_kacab)
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Detail kurikulum berhasil diambil',
                'data' => $kurikulum
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kurikulum tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function getPreview($id)
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $kurikulum = Kurikulum::with([
                'kurikulumMateri.mataPelajaran',
                'kurikulumMateri.materi'
            ])->byKacab($adminShelter->shelter->wilbin->id_kacab)
              ->findOrFail($id);

            $preview = [
                'kurikulum' => $kurikulum->only(['id_kurikulum', 'nama_kurikulum', 'deskripsi', 'tahun_berlaku']),
                'total_mata_pelajaran' => $kurikulum->getTotalMataPelajaran(),
                'total_materi' => $kurikulum->getTotalMateri(),
                'mata_pelajaran' => $kurikulum->allMataPelajaran->map(function ($mp) use ($kurikulum) {
                    return [
                        'id_mata_pelajaran' => $mp->id_mata_pelajaran,
                        'nama' => $mp->nama_mata_pelajaran,
                        'kategori' => $mp->kategori,
                        'total_materi' => $kurikulum->kurikulumMateri()
                            ->where('id_mata_pelajaran', $mp->id_mata_pelajaran)
                            ->count()
                    ];
                })
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Preview kurikulum berhasil diambil',
                'data' => $preview
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil preview kurikulum',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getForDropdown()
    {
        try {
            $adminShelter = auth()->user()->adminShelter;
            $kurikulum = Kurikulum::byKacab($adminShelter->shelter->wilbin->id_kacab)
                ->active()
                ->select('id_kurikulum', 'nama_kurikulum', 'tahun_berlaku')
                ->orderBy('tahun_berlaku', 'desc')
                ->orderBy('nama_kurikulum')
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data dropdown kurikulum berhasil diambil',
                'data' => $kurikulum
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dropdown kurikulum',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}