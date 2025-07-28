<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Raport;
use App\Models\RaportDetail;
use App\Models\Anak;
use App\Models\Semester;
use App\Models\Penilaian;
use App\Models\NilaiSikap;
use App\Models\Absen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RaportController extends Controller
{
    /**
     * Display a listing of raport
     */
    public function index(Request $request)
    {
        try {
            $query = Raport::with(['anak', 'semester']);
            
            // Filter by anak
            if ($request->has('id_anak')) {
                $query->where('id_anak', $request->id_anak);
            }
            
            // Filter by semester
            if ($request->has('id_semester')) {
                $query->where('id_semester', $request->id_semester);
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            $raport = $query->orderBy('tanggal_terbit', 'desc')->paginate(20);
            
            return response()->json([
                'success' => true,
                'message' => 'Data raport berhasil diambil',
                'data' => $raport
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate raport for anak and semester
     */
    public function generate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_anak' => 'required|exists:anak,id_anak',
                'id_semester' => 'required|exists:semester,id_semester',
                'catatan_wali_kelas' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Check if raport already exists
            $existingRaport = Raport::where('id_anak', $request->id_anak)
                ->where('id_semester', $request->id_semester)
                ->first();

            if ($existingRaport) {
                return response()->json([
                    'success' => false,
                    'message' => 'Raport untuk anak dan semester ini sudah ada',
                    'data' => $existingRaport
                ], 409);
            }

            // Calculate attendance
            $semester = Semester::findOrFail($request->id_semester);
            $totalActivities = Absen::whereHas('aktivitas', function($query) use ($semester) {
                    $query->whereBetween('tanggal', [$semester->tanggal_mulai, $semester->tanggal_selesai]);
                })
                ->whereHas('absenUser', function($query) use ($request) {
                    $query->where('id_anak', $request->id_anak);
                })
                ->count();

            $presentCount = Absen::whereHas('aktivitas', function($query) use ($semester) {
                    $query->whereBetween('tanggal', [$semester->tanggal_mulai, $semester->tanggal_selesai]);
                })
                ->whereHas('absenUser', function($query) use ($request) {
                    $query->where('id_anak', $request->id_anak);
                })
                ->whereIn('absen', ['Ya', 'Terlambat'])
                ->count();

            $persentaseKehadiran = $totalActivities > 0 ? ($presentCount / $totalActivities) * 100 : 0;

            // Create raport
            $raport = Raport::create([
                'id_anak' => $request->id_anak,
                'id_semester' => $request->id_semester,
                'total_kehadiran' => $presentCount,
                'persentase_kehadiran' => $persentaseKehadiran,
                'catatan_wali_kelas' => $request->catatan_wali_kelas,
                'tanggal_terbit' => now(),
                'status' => 'draft'
            ]);

            // Generate raport details from penilaian
            $this->generateRaportDetails($raport);

            // Calculate ranking
            $this->calculateRanking($request->id_semester);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Raport berhasil dibuat',
                'data' => $raport->load(['anak', 'semester', 'raportDetail'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified raport
     */
    public function show($id)
    {
        try {
            $raport = Raport::with(['anak', 'semester', 'raportDetail'])
                ->findOrFail($id);
            
            // Get nilai sikap
            $nilaiSikap = NilaiSikap::where('id_anak', $raport->id_anak)
                ->where('id_semester', $raport->id_semester)
                ->first();
            
            $raport->nilai_sikap = $nilaiSikap;
            
            return response()->json([
                'success' => true,
                'message' => 'Detail raport berhasil diambil',
                'data' => $raport
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Raport tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update raport
     */
    public function update(Request $request, $id)
    {
        try {
            $raport = Raport::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'catatan_wali_kelas' => 'nullable|string',
                'status' => 'sometimes|in:draft,published,archived'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $raport->update($request->only(['catatan_wali_kelas', 'status']));
            
            return response()->json([
                'success' => true,
                'message' => 'Raport berhasil diperbarui',
                'data' => $raport->load(['anak', 'semester', 'raportDetail'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish raport
     */
    public function publish($id)
    {
        try {
            $raport = Raport::findOrFail($id);
            
            // Check if all required data is complete
            if ($raport->raportDetail->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Detail raport belum lengkap'
                ], 422);
            }
            
            $raport->publish();
            
            return response()->json([
                'success' => true,
                'message' => 'Raport berhasil dipublikasi',
                'data' => $raport->load(['anak', 'semester', 'raportDetail'])
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mempublikasi raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive raport
     */
    public function archive($id)
    {
        try {
            $raport = Raport::findOrFail($id);
            $raport->archive();
            
            return response()->json([
                'success' => true,
                'message' => 'Raport berhasil diarsipkan',
                'data' => $raport
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengarsipkan raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete raport
     */
    public function destroy($id)
    {
        try {
            $raport = Raport::findOrFail($id);
            
            if ($raport->status === 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'Raport yang sudah dipublikasi tidak dapat dihapus'
                ], 403);
            }
            
            $raport->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Raport berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get raport by anak
     */
    public function getByAnak($idAnak)
    {
        try {
            $raport = Raport::with(['semester', 'raportDetail'])
                ->where('id_anak', $idAnak)
                ->orderBy('tanggal_terbit', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data raport berhasil diambil',
                'data' => $raport
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update raport detail
     */
    public function updateDetail(Request $request, $idRaport, $idDetail)
    {
        try {
            $detail = RaportDetail::where('id_raport', $idRaport)
                ->where('id_raport_detail', $idDetail)
                ->firstOrFail();
            
            $validator = Validator::make($request->all(), [
                'nilai_akhir' => 'sometimes|numeric|min:0|max:100',
                'nilai_huruf' => 'sometimes|in:A,B,C,D,E',
                'kkm' => 'sometimes|numeric|min:0|max:100',
                'keterangan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $detail->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Detail raport berhasil diperbarui',
                'data' => $detail
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui detail raport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate raport details from penilaian
     */
    private function generateRaportDetails($raport)
    {
        $penilaianData = Penilaian::where('id_anak', $raport->id_anak)
            ->where('id_semester', $raport->id_semester)
            ->with(['materi', 'jenisPenilaian'])
            ->get();

        $groupedByMapel = [];
        
        foreach ($penilaianData as $penilaian) {
            $mapel = $penilaian->materi->mata_pelajaran ?? 'Unknown';
            
            if (!isset($groupedByMapel[$mapel])) {
                $groupedByMapel[$mapel] = [];
            }
            
            $groupedByMapel[$mapel][] = $penilaian;
        }

        foreach ($groupedByMapel as $mapel => $penilaianGroup) {
            $nilaiAkhir = 0;
            
            foreach ($penilaianGroup as $penilaian) {
                $bobot = $penilaian->jenisPenilaian->bobot_persen / 100;
                $nilaiAkhir += $penilaian->nilai * $bobot;
            }
            
            RaportDetail::create([
                'id_raport' => $raport->id_raport,
                'mata_pelajaran' => $mapel,
                'nilai_akhir' => $nilaiAkhir,
                'nilai_huruf' => $this->convertToHuruf($nilaiAkhir),
                'kkm' => 70,
                'keterangan' => $nilaiAkhir >= 70 ? 'Tuntas' : 'Belum Tuntas'
            ]);
        }
    }

    /**
     * Calculate ranking for semester
     */
    private function calculateRanking($idSemester)
    {
        $raports = Raport::where('id_semester', $idSemester)
            ->with('raportDetail')
            ->get();
        
        $rankings = [];
        foreach ($raports as $raport) {
            $avgNilai = $raport->raportDetail->avg('nilai_akhir') ?? 0;
            $rankings[] = [
                'id_raport' => $raport->id_raport,
                'avg_nilai' => $avgNilai
            ];
        }
        
        // Sort by average nilai descending
        usort($rankings, function($a, $b) {
            return $b['avg_nilai'] <=> $a['avg_nilai'];
        });
        
        // Update ranking
        foreach ($rankings as $index => $ranking) {
            Raport::where('id_raport', $ranking['id_raport'])
                ->update(['ranking' => $index + 1]);
        }
    }

    private function convertToHuruf($nilai)
    {
        if ($nilai >= 90) return 'A';
        if ($nilai >= 80) return 'B';
        if ($nilai >= 70) return 'C';
        if ($nilai >= 60) return 'D';
        return 'E';
    }
    /**
     * Check if raport exists for anak and semester
     */
    public function checkExistingRaport($idAnak, $idSemester)
    {
        try {
            $raport = Raport::where('id_anak', $idAnak)
                ->where('id_semester', $idSemester)
                ->first();
            
            return response()->json([
                'success' => true,
                'exists' => !!$raport,
                'raport' => $raport
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek raport yang ada',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get preview data for raport generation
     */
   public function getPreviewData($idAnak, $idSemester)
{
    try {
        $anak = Anak::findOrFail($idAnak);
        $semester = Semester::findOrFail($idSemester);
        
        // Calculate attendance
        $totalActivities = Absen::whereHas('aktivitas', function($query) use ($semester) {
                $query->whereBetween('tanggal', [$semester->tanggal_mulai, $semester->tanggal_selesai]);
            })
            ->whereHas('absenUser', function($query) use ($idAnak) {
                $query->where('id_anak', $idAnak);
            })
            ->count();

        $presentCount = Absen::whereHas('aktivitas', function($query) use ($semester) {
                $query->whereBetween('tanggal', [$semester->tanggal_mulai, $semester->tanggal_selesai]);
            })
            ->whereHas('absenUser', function($query) use ($idAnak) {
                $query->where('id_anak', $idAnak);
            })
            ->whereIn('absen', ['Ya', 'Terlambat'])
            ->count();

        $attendancePercentage = $totalActivities > 0 ? ($presentCount / $totalActivities) * 100 : 0;

        // Get detailed academic grades
        $penilaianData = Penilaian::where('id_anak', $idAnak)
            ->where('id_semester', $idSemester)
            ->with(['materi', 'jenisPenilaian', 'aktivitas'])
            ->get();

        // Group by mata_pelajaran and get detailed breakdown
        $academicDetails = [];
        $overallAverage = 0;
        $totalSubjects = 0;
        
        $groupedByMapel = $penilaianData->groupBy('materi.mata_pelajaran');
        
        foreach ($groupedByMapel as $mataPelajaran => $penilaianGroup) {
            if (!$mataPelajaran) continue;
            
            $materiList = [];
            $subjectAverage = 0;
            $totalAssessments = $penilaianGroup->count();
            
            // Group by materi within this mata_pelajaran
            $groupedByMateri = $penilaianGroup->groupBy('materi.nama_materi');
            
            foreach ($groupedByMateri as $namaMateri => $materiPenilaian) {
                $materiAverage = $materiPenilaian->avg('nilai');
                $assessmentTypes = $materiPenilaian->map(function($p) {
                    return [
                        'jenis' => $p->jenisPenilaian->nama_jenis,
                        'nilai' => $p->nilai,
                        'tanggal' => $p->tanggal_penilaian->format('d/m/Y')
                    ];
                });
                
                $materiList[] = [
                    'nama_materi' => $namaMateri ?: 'Materi Umum',
                    'rata_rata' => round($materiAverage, 1),
                    'total_penilaian' => $materiPenilaian->count(),
                    'assessments' => $assessmentTypes
                ];
            }
            
            $subjectAverage = $penilaianGroup->avg('nilai');
            $overallAverage += $subjectAverage;
            $totalSubjects++;
            
            $academicDetails[] = [
                'mata_pelajaran' => $mataPelajaran,
                'rata_rata' => round($subjectAverage, 1),
                'total_penilaian' => $totalAssessments,
                'materi_list' => $materiList,
                'completeness' => $this->calculateSubjectCompleteness($totalAssessments)
            ];
        }
        
        $overallAverage = $totalSubjects > 0 ? $overallAverage / $totalSubjects : 0;
        
        // Calculate overall completeness
        $expectedAssessments = $totalSubjects * 3; // Assuming minimum 3 assessments per subject
        $actualAssessments = $penilaianData->count();
        $completeness = $expectedAssessments > 0 ? min(($actualAssessments / $expectedAssessments) * 100, 100) : 0;

        // Check nilai sikap
        $nilaiSikap = NilaiSikap::where('id_anak', $idAnak)
            ->where('id_semester', $idSemester)
            ->first();

        // Generate warnings
        $warnings = [];
        if ($totalSubjects == 0) {
            $warnings[] = 'Belum ada nilai akademik yang diinput';
        }
        if ($completeness < 80) {
            $warnings[] = 'Data penilaian belum lengkap (' . round($completeness) . '%)';
        }
        if (!$nilaiSikap) {
            $warnings[] = 'Nilai sikap belum diinput';
        }
        if ($attendancePercentage < 75) {
            $warnings[] = 'Persentase kehadiran rendah (' . round($attendancePercentage) . '%)';
        }

        return response()->json([
            'success' => true,
            'message' => 'Preview data berhasil diambil',
            'data' => [
                'attendance' => [
                    'total' => $presentCount,
                    'percentage' => round($attendancePercentage, 1)
                ],
                'grades' => [
                    'total_subjects' => $totalSubjects,
                    'overall_average' => round($overallAverage, 2),
                    'completeness' => round($completeness, 1),
                    'total_assessments' => $actualAssessments,
                    'academic_details' => $academicDetails
                ],
                'nilaiSikap' => [
                    'exists' => !!$nilaiSikap,
                    'data' => $nilaiSikap ? [
                        'kedisiplinan' => $nilaiSikap->kedisiplinan,
                        'kerjasama' => $nilaiSikap->kerjasama,
                        'tanggung_jawab' => $nilaiSikap->tanggung_jawab,
                        'sopan_santun' => $nilaiSikap->sopan_santun,
                        'rata_rata' => $nilaiSikap->rata_rata
                    ] : null
                ],
                'warnings' => $warnings
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil preview data',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function calculateSubjectCompleteness($totalAssessments)
{
    $expectedMin = 3; // Minimum expected assessments per subject
    return min(($totalAssessments / $expectedMin) * 100, 100);
}
}