<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AdminShelterSurveyValidasiController extends Controller
{
    /**
     * Get all surveys that need validation for current admin's shelter
     */
    public function index(Request $request)
    {
        try {
            // Get the authenticated user and check if they're an admin shelter
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || !$adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }
            
            // Get the admin's shelter ID
            $id_shelter = $adminShelter->id_shelter;
            
            // Get pagination parameters
            $per_page = $request->input('per_page', 20);
            $search = $request->input('search', '');
            $status = $request->input('status', ''); // Filter by validation status
            
            // Build query to get surveys for families in THIS admin's shelter only
            $surveysQuery = Survey::with(['keluarga.kacab', 'keluarga.wilbin', 'keluarga.shelter'])
                ->whereHas('keluarga', function($q) use ($id_shelter) {
                    $q->where('id_shelter', $id_shelter);
                });
            
            // Add search filter if provided
            if ($search) {
                $surveysQuery->whereHas('keluarga', function($q) use ($search) {
                    $q->where('no_kk', 'LIKE', "%{$search}%")
                      ->orWhere('kepala_keluarga', 'LIKE', "%{$search}%");
                });
            }
            
            // Add status filter if provided
            if ($status) {
                $surveysQuery->where('hasil_survey', $status);
            }
            
            // Paginate the results
            $data = $surveysQuery->paginate($per_page);
            
            return response()->json([
                'success' => true,
                'message' => 'Data survey berhasil diambil',
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total()
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit validation for a survey
     */
    public function validateSurvey(Request $request, $id_survey)
    {
        try {
            // Get the authenticated user and check if they're an admin shelter
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || !$adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }
            
            // Get the admin's shelter ID
            $id_shelter = $adminShelter->id_shelter;
            
            // Validate request data
            $validator = Validator::make($request->all(), [
                'hasil_survey' => 'required|string|in:Layak,Tidak Layak',
                'keterangan_hasil' => 'nullable|string|max:255'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Find the survey and include keluarga relation
            $survey = Survey::with('keluarga')->findOrFail($id_survey);
            
            // Check if this admin has permission to validate this survey
            // by confirming the shelter matches
            if (!$survey->keluarga || $survey->keluarga->id_shelter != $id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk memvalidasi survey ini'
                ], 403);
            }
            
            // Update survey validation data
            $survey->hasil_survey = $request->hasil_survey;
            $survey->keterangan_hasil = $request->keterangan_hasil;
            $survey->save();
            
            // If hasil_survey is "Layak", update anak status_cpb to "CPB"
            if ($survey->hasil_survey === 'Layak') {
                Anak::where('id_keluarga', $survey->id_keluarga)
                    ->update(['status_cpb' => Anak::STATUS_CPB_CPB]);
            }
            
            // Get the updated survey with relationships
            $updatedSurvey = Survey::with(['keluarga.kacab', 'keluarga.wilbin', 'keluarga.shelter'])
                ->find($id_survey);
            
            return response()->json([
                'success' => true,
                'message' => 'Validasi survey berhasil disimpan',
                'data' => $updatedSurvey
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memvalidasi survey: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending surveys count for dashboard
     */
    public function getValidationSummary(Request $request)
    {
        try {
            // Get the authenticated user and check if they're an admin shelter
            $user = $request->user();
            $adminShelter = $user->adminShelter;
            
            if (!$adminShelter || !$adminShelter->id_shelter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin shelter tidak ditemukan atau tidak terkait dengan shelter'
                ], 403);
            }
            
            // Get the admin's shelter ID
            $id_shelter = $adminShelter->id_shelter;
            
            // Count total surveys for this shelter
            $totalSurveys = Survey::whereHas('keluarga', function($q) use ($id_shelter) {
                $q->where('id_shelter', $id_shelter);
            })->count();
            
            // Count pending (no validation result) surveys
            $pendingSurveys = Survey::whereHas('keluarga', function($q) use ($id_shelter) {
                $q->where('id_shelter', $id_shelter);
            })->whereNull('hasil_survey')->count();
            
            // Count "Layak" surveys
            $layakSurveys = Survey::whereHas('keluarga', function($q) use ($id_shelter) {
                $q->where('id_shelter', $id_shelter);
            })->where('hasil_survey', 'Layak')->count();
            
            // Count "Tidak Layak" surveys
            $tidakLayakSurveys = Survey::whereHas('keluarga', function($q) use ($id_shelter) {
                $q->where('id_shelter', $id_shelter);
            })->where('hasil_survey', 'Tidak Layak')->count();
            
            // Count "Tambah Kelayakan" surveys
            $tambahKelayakanSurveys = Survey::whereHas('keluarga', function($q) use ($id_shelter) {
                $q->where('id_shelter', $id_shelter);
            })->where('hasil_survey', 'Tambah Kelayakan')->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $totalSurveys,
                    'pending' => $pendingSurveys,
                    'layak' => $layakSurveys,
                    'tidak_layak' => $tidakLayakSurveys,
                    'tambah_kelayakan' => $tambahKelayakanSurveys
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil ringkasan validasi: ' . $e->getMessage()
            ], 500);
        }
    }
}