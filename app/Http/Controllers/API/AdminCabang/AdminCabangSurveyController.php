<?php

namespace App\Http\Controllers\API\AdminCabang;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\Anak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminCabangSurveyController extends Controller
{
    private function getAdminCabang()
    {
        $admin = auth()->user()->adminCabang;
        if (!$admin) {
            abort(404, 'Admin Cabang not found');
        }
        return $admin;
    }

    public function index(Request $request)
    {
        $admin = $this->getAdminCabang();
        $status = $request->get('status', 'pending');
        
        $query = Survey::byKacab($admin->id_kacab)->with([
            'keluarga' => fn($q) => $q->with([
                'shelter.wilbin', 
                'anak' => fn($anak) => $anak->select('id_anak', 'id_keluarga', 'full_name', 'nick_name', 'status_cpb', 'tanggal_lahir')
            ]),
            'approvedBy'
        ]);

        match($status) {
            'approved', 'layak' => $query->approved(),
            'rejected', 'tidak layak' => $query->rejected(),
            default => $query->pending()
        };

        if ($request->shelter_id) {
            $query->whereHas('keluarga', fn($q) => $q->where('id_shelter', $request->shelter_id));
        }

        if ($search = $request->search) {
            $query->whereHas('keluarga', fn($q) => $q->where('kepala_keluarga', 'LIKE', "%{$search}%")
                ->orWhere('no_kk', 'LIKE', "%{$search}%")
                ->orWhereHas('anak', fn($anak) => $anak->where('full_name', 'LIKE', "%{$search}%")));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('created_at', 'desc')->paginate(10)
        ]);
    }

    public function show($id)
    {
        $admin = $this->getAdminCabang();
        
        $survey = Survey::byKacab($admin->id_kacab)->with([
            'keluarga' => fn($q) => $q->with(['shelter.wilbin', 'kacab', 'bank', 'anak', 'ayah', 'ibu', 'wali']),
            'approvedBy'
        ])->find($id);

        if (!$survey) {
            return response()->json(['message' => 'Survey not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $survey]);
    }

    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $admin = $this->getAdminCabang();
        $survey = Survey::pending()->byKacab($admin->id_kacab)->find($id);

        if (!$survey) {
            return response()->json(['message' => 'Survey not found or already processed'], 404);
        }

        return DB::transaction(function () use ($survey, $admin, $request) {
            $survey->update([
                'hasil_survey' => 'layak',
                'approved_by' => $admin->id_admin_cabang,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes
            ]);

            Anak::where('id_keluarga', $survey->id_keluarga)
                ->where('status_cpb', 'BCPB')
                ->update(['status_cpb' => 'CPB']);

            return response()->json([
                'success' => true,
                'message' => 'Survey approved successfully',
                'data' => $survey->fresh(['keluarga.anak'])
            ]);
        });
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
            'approval_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $admin = $this->getAdminCabang();
        $survey = Survey::pending()->byKacab($admin->id_kacab)->find($id);

        if (!$survey) {
            return response()->json(['message' => 'Survey not found or already processed'], 404);
        }

        return DB::transaction(function () use ($survey, $admin, $request) {
            $survey->update([
                'hasil_survey' => 'tidak layak',
                'approved_by' => $admin->id_admin_cabang,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
                'approval_notes' => $request->approval_notes
            ]);

            Anak::where('id_keluarga', $survey->id_keluarga)
                ->where('status_cpb', 'BCPB')
                ->update(['status_cpb' => 'NPB']);

            return response()->json([
                'success' => true,
                'message' => 'Survey rejected successfully',
                'data' => $survey->fresh(['keluarga.anak'])
            ]);
        });
    }

    public function getStats()
    {
        $admin = $this->getAdminCabang();
        
        $stats = [
            'pending' => Survey::pending()->byKacab($admin->id_kacab)->count(),
            'approved' => Survey::approved()->byKacab($admin->id_kacab)->count(),
            'rejected' => Survey::rejected()->byKacab($admin->id_kacab)->count(),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }
}