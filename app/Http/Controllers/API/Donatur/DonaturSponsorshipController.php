<?php

namespace App\Http\Controllers\Api\Donatur;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Donatur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonaturSponsorshipController extends Controller
{
    public function sponsorChild(Request $request, $childId)
    {
        try {
            $request->validate([
                'agreement_accepted' => 'required|boolean|accepted',
                'sponsorship_date' => 'required|date'
            ]);

            $donatur = Donatur::where('id_users', $request->user()->id_users)->first();
            
            if (!$donatur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donatur profile not found'
                ], 404);
            }

            $child = Anak::availableForSponsorship()->findOrFail($childId);

            DB::beginTransaction();

            $child->update([
                'id_donatur' => $donatur->id_donatur,
                'status_cpb' => Anak::STATUS_CPB_PB,
                'sponsorship_date' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sponsorship successful',
                'data' => [
                    'child' => $child->load(['shelter', 'kelompok']),
                    'donatur' => $donatur,
                    'sponsorship_date' => $child->sponsorship_date
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process sponsorship',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}