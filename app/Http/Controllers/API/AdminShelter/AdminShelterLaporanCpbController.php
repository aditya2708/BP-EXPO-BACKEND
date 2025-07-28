<?php

namespace App\Http\Controllers\API\AdminShelter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Anak;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminShelterLaporanCpbController extends Controller
{
    public function getCpbReport(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        // Base query for children in this shelter
        $baseQuery = Anak::where('id_shelter', $shelterId)
            ->whereIn('status_validasi', Anak::STATUS_AKTIF);

        // Get counts by CPB status
        $cpbCounts = $baseQuery->select('status_cpb', DB::raw('count(*) as total'))
            ->groupBy('status_cpb')
            ->pluck('total', 'status_cpb')
            ->toArray();

        // Ensure all statuses are present
        $summary = [
            'BCPB' => $cpbCounts['BCPB'] ?? 0,
            'CPB' => $cpbCounts['CPB'] ?? 0,
            'NPB' => $cpbCounts['NPB'] ?? 0,
            'PB' => $cpbCounts['PB'] ?? 0,
            'total' => array_sum($cpbCounts)
        ];

        return response()->json([
            'message' => 'CPB report retrieved successfully',
            'data' => [
                'summary' => $summary
            ]
        ]);
    }

    public function getCpbByStatus(Request $request, $status)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $search = $request->get('search', null);

        // Validate status
        $validStatuses = ['BCPB', 'CPB', 'NPB', 'PB'];
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'message' => 'Invalid CPB status'
            ], 400);
        }

        // Build query
        $query = Anak::where('id_shelter', $shelterId)
            ->where('status_cpb', $status)
            ->whereIn('status_validasi', Anak::STATUS_AKTIF)
            ->with(['kelompok', 'keluarga']);

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nick_name', 'like', "%{$search}%");
            });
        }

        $children = $query->orderBy('full_name', 'asc')->get();

        // Format data
        $formattedChildren = $children->map(function($child) {
            return [
                'id_anak' => $child->id_anak,
                'full_name' => $child->full_name,
                'nick_name' => $child->nick_name,
                'jenis_kelamin' => $child->jenis_kelamin,
                'umur' => $child->umur,
                'foto_url' => $child->foto_url,
                'kelas' => $child->kelompok ? $child->kelompok->kelas : null,
                'level' => $child->kelompok ? $child->kelompok->level : null,
                'status_orang_tua' => $child->keluarga ? $child->keluarga->status_ortu : null,
                'status_cpb' => $child->status_cpb,
                'created_at' => $child->created_at ? $child->created_at->format('Y-m-d') : '',
                'sponsorship_date' => $child->sponsorship_date ? $child->sponsorship_date->format('Y-m-d') : null
            ];
        });

        return response()->json([
            'message' => "Children with {$status} status retrieved successfully",
            'data' => [
                'status' => $status,
                'total' => $formattedChildren->count(),
                'children' => $formattedChildren,
                'applied_filters' => [
                    'search' => $search
                ]
            ]
        ]);
    }

    public function exportCpbData(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        $shelterId = $adminShelter->id_shelter;

        $status = $request->get('status', null);
        $format = $request->get('format', 'json'); // Default to JSON for backward compatibility

        // Debug line - remove after testing
        \Log::info('Export format requested: ' . $format, ['all_params' => $request->all()]);

        try {
            // Build query
            $query = Anak::where('id_shelter', $shelterId)
                ->whereIn('status_validasi', Anak::STATUS_AKTIF)
                ->with(['kelompok', 'keluarga', 'shelter']);

            if ($status) {
                $query->where('status_cpb', $status);
            }

            $children = $query->orderBy('status_cpb')->orderBy('full_name')->get();

            // Get summary data
            $summary = $this->getCpbSummary($shelterId);

            $exportData = [
                'export_date' => now()->format('Y-m-d H:i:s'),
                'shelter' => $adminShelter->shelter->nama_shelter ?? 'Unknown',
                'shelter_coordinator' => $adminShelter->shelter->nama_koordinator ?? '',
                'filters' => [
                    'status' => $status
                ],
                'summary' => $summary,
                'total_records' => $children->count(),
                'children' => []
            ];

            // Process each child
            foreach ($children as $child) {
                $exportData['children'][] = [
                    'id_anak' => $child->id_anak,
                    'nama_lengkap' => $child->full_name ?? '',
                    'nama_panggilan' => $child->nick_name ?? '',
                    'jenis_kelamin' => $child->jenis_kelamin ?? '',
                    'umur' => $child->umur ?? '',
                    'kelas' => $child->kelompok ? $child->kelompok->kelas : '',
                    'level' => $child->kelompok ? $child->kelompok->level : '',
                    'status_orang_tua' => $child->keluarga ? $child->keluarga->status_ortu : '',
                    'status_cpb' => $child->status_cpb ?? '',
                    'tanggal_daftar' => $child->created_at ? $child->created_at->format('Y-m-d') : '',
                    'tanggal_sponsorship' => $child->sponsorship_date ? $child->sponsorship_date->format('Y-m-d') : ''
                ];
            }

            // Return based on format
            if ($format === 'pdf') {
                \Log::info('Generating PDF format');
                return $this->generatePDF($exportData);
            }

            // Default JSON response
            \Log::info('Returning JSON format');
            return response()->json([
                'message' => 'Export data prepared successfully',
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            \Log::error("Export CPB data failed", [
                'shelter_id' => $shelterId,
                'format' => $format,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getCpbSummary($shelterId)
    {
        $baseQuery = Anak::where('id_shelter', $shelterId)
            ->whereIn('status_validasi', Anak::STATUS_AKTIF);

        $cpbCounts = $baseQuery->select('status_cpb', DB::raw('count(*) as total'))
            ->groupBy('status_cpb')
            ->pluck('total', 'status_cpb')
            ->toArray();

        return [
            'BCPB' => $cpbCounts['BCPB'] ?? 0,
            'CPB' => $cpbCounts['CPB'] ?? 0,
            'NPB' => $cpbCounts['NPB'] ?? 0,
            'PB' => $cpbCounts['PB'] ?? 0,
            'total' => array_sum($cpbCounts)
        ];
    }

    private function generatePDF($data)
    {
        try {
            $pdf = PDF::loadView('reports.cpb-report', compact('data'))
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true
                ]);

            $filename = 'laporan-cpb-' . ($data['filters']['status'] ? strtolower($data['filters']['status']) . '-' : '') . date('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            \Log::error("PDF generation failed", [
                'error' => $e->getMessage(),
                'data_count' => count($data['children'])
            ]);

            return response()->json([
                'message' => 'PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}