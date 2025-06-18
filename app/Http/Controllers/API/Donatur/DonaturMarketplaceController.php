<?php

namespace App\Http\Controllers\Api\Donatur;

use App\Http\Controllers\Controller;
use App\Models\Anak;
use App\Models\Shelter;
use Illuminate\Http\Request;

class DonaturMarketplaceController extends Controller
{
    public function availableChildren(Request $request)
    {
        try {
            $query = Anak::availableForSponsorship()
                ->with(['shelter', 'kelompok', 'prestasi']);

            if ($request->has('gender')) {
                $query->byGender($request->gender);
            }

            if ($request->has('min_age') && $request->has('max_age')) {
                $query->byAgeRange($request->min_age, $request->max_age);
            }

            if ($request->has('hafalan')) {
                $query->byHafalan($request->hafalan);
            }

            if ($request->has('shelter_id')) {
                $query->byRegion($request->shelter_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('nick_name', 'like', "%{$search}%")
                      ->orWhereHas('shelter', function($sq) use ($search) {
                          $sq->where('nama_shelter', 'like', "%{$search}%");
                      });
                });
            }

            $perPage = $request->per_page ?? 20;
            $children = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $children->items(),
                'pagination' => [
                    'total' => $children->total(),
                    'per_page' => $children->perPage(),
                    'current_page' => $children->currentPage(),
                    'last_page' => $children->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available children',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function childProfile($childId)
    {
        try {
            $child = Anak::availableForSponsorship()
                ->with([
                    'shelter', 
                    'kelompok', 
                    'keluarga',
                    'prestasi' => function($query) {
                        $query->orderBy('tgl_upload', 'desc')->limit(5);
                    }
                ])
                ->findOrFail($childId);

            return response()->json([
                'success' => true,
                'data' => $child
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Child not found or not available for sponsorship',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function featuredChildren()
    {
        try {
            $children = Anak::availableForSponsorship()
                ->featuredMarketplace()
                ->with(['shelter', 'kelompok'])
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $children
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve featured children',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getFilters()
    {
        try {
            $shelters = Shelter::whereHas('anak', function($query) {
                $query->availableForSponsorship();
            })->get(['id_shelter', 'nama_shelter']);

            $ageRanges = [
                ['label' => '5-8 tahun', 'min' => 5, 'max' => 8],
                ['label' => '9-12 tahun', 'min' => 9, 'max' => 12],
                ['label' => '13-15 tahun', 'min' => 13, 'max' => 15],
                ['label' => '16-18 tahun', 'min' => 16, 'max' => 18]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'shelters' => $shelters,
                    'age_ranges' => $ageRanges,
                    'genders' => [
                        ['label' => 'Laki-laki', 'value' => 'L'],
                        ['label' => 'Perempuan', 'value' => 'P']
                    ],
                    'hafalan_types' => [
                        ['label' => 'Tahfidz', 'value' => 'Tahfidz'],
                        ['label' => 'Non-Tahfidz', 'value' => 'Non-Tahfidz']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}