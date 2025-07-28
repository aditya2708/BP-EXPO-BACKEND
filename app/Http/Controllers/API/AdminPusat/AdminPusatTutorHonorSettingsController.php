<?php

namespace App\Http\Controllers\API\AdminPusat;

use App\Http\Controllers\Controller;
use App\Models\TutorHonorSettings;
use App\Helpers\CurrencyHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPusatTutorHonorSettingsController extends Controller
{
    /**
     * Get all tutor honor settings with pagination
     */
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean'
        ]);

        $query = TutorHonorSettings::with(['creator:id_users,email', 'updater:id_users,email'])
                                  ->orderBy('created_at', 'desc');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page', 10);
        $settings = $query->paginate($perPage);

        // Add formatted rates to each setting
        $formattedSettings = $settings->getCollection()->map(function ($setting) {
            $setting->formatted_rates = $setting->formatted_rates;
            return $setting;
        });

        return response()->json([
            'success' => true,
            'data' => $formattedSettings,
            'pagination' => [
                'current_page' => $settings->currentPage(),
                'last_page' => $settings->lastPage(),
                'per_page' => $settings->perPage(),
                'total' => $settings->total()
            ]
        ]);
    }

    /**
     * Get current active setting
     */
    public function getActiveSetting()
    {
        $activeSetting = TutorHonorSettings::getActiveSetting();

        if (!$activeSetting) {
            return response()->json([
                'success' => false,
                'message' => 'No active honor setting found'
            ], 404);
        }

        $activeSetting->formatted_rates = $activeSetting->formatted_rates;

        return response()->json([
            'success' => true,
            'data' => $activeSetting->load(['creator:id_users,email', 'updater:id_users,email'])
        ]);
    }

    /**
     * Store new tutor honor setting
     */
    public function store(Request $request)
    {
        $request->validate(TutorHonorSettings::validationRules());

        DB::beginTransaction();
        
        try {
            $data = [
                'payment_system' => $request->payment_system,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => Auth::id()
            ];

            // Add rates based on payment system
            switch ($request->payment_system) {
                case 'flat_monthly':
                    $data['flat_monthly_rate'] = $request->flat_monthly_rate;
                    break;
                    
                case 'per_session':
                    $data['session_rate'] = $request->session_rate;
                    break;
                    
                case 'per_student_category':
                    $data['cpb_rate'] = $request->cpb_rate;
                    $data['pb_rate'] = $request->pb_rate;
                    $data['npb_rate'] = $request->npb_rate;
                    break;
                    
                case 'session_per_student_category':
                    $data['session_rate'] = $request->session_rate;
                    $data['cpb_rate'] = $request->cpb_rate;
                    $data['pb_rate'] = $request->pb_rate;
                    $data['npb_rate'] = $request->npb_rate;
                    break;
            }

            $setting = TutorHonorSettings::create($data);

            // If this setting is active, deactivate others
            if ($setting->is_active) {
                $setting->setAsActive();
            }

            // Add formatted rates
            $setting->formatted_rates = $setting->formatted_rates;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Honor setting created successfully',
                'data' => $setting->load(['creator:id_users,email'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create honor setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific honor setting
     */
    public function show($id)
    {
        $setting = TutorHonorSettings::with(['creator:id_users,email', 'updater:id_users,email'])
                                    ->findOrFail($id);

        $setting->formatted_rates = $setting->formatted_rates;

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }

    /**
     * Update honor setting
     */
    public function update(Request $request, $id)
    {
        $request->validate(TutorHonorSettings::validationRules());

        $setting = TutorHonorSettings::findOrFail($id);
        $originalIsActive = $setting->is_active;

        DB::beginTransaction();
        
        try {
            $data = [
                'payment_system' => $request->payment_system,
                'is_active' => $request->boolean('is_active', $setting->is_active),
                'updated_by' => Auth::id()
            ];

            // Only reset rates that are not used by the new payment system
            $allRates = ['cpb_rate', 'pb_rate', 'npb_rate', 'flat_monthly_rate', 'session_rate', 'per_student_rate'];
            
            // Add rates based on payment system and reset unused ones
            switch ($request->payment_system) {
                case 'flat_monthly':
                    $data['flat_monthly_rate'] = $request->flat_monthly_rate;
                    $unusedRates = array_diff($allRates, ['flat_monthly_rate']);
                    break;
                    
                case 'per_session':
                    $data['session_rate'] = $request->session_rate;
                    $unusedRates = array_diff($allRates, ['session_rate']);
                    break;
                    
                case 'per_student_category':
                    $data['cpb_rate'] = $request->cpb_rate;
                    $data['pb_rate'] = $request->pb_rate;
                    $data['npb_rate'] = $request->npb_rate;
                    $unusedRates = array_diff($allRates, ['cpb_rate', 'pb_rate', 'npb_rate']);
                    break;
                    
                case 'session_per_student_category':
                    $data['session_rate'] = $request->session_rate;
                    $data['cpb_rate'] = $request->cpb_rate;
                    $data['pb_rate'] = $request->pb_rate;
                    $data['npb_rate'] = $request->npb_rate;
                    $unusedRates = array_diff($allRates, ['session_rate', 'cpb_rate', 'pb_rate', 'npb_rate']);
                    break;
                    
                default:
                    $unusedRates = $allRates;
                    break;
            }

            // Set unused rates to null
            foreach ($unusedRates as $rate) {
                $data[$rate] = null;
            }

            $setting->update($data);

            // If this setting is being activated, deactivate others
            if ($request->boolean('is_active') && !$originalIsActive) {
                $setting->setAsActive();
            }

            // Add formatted rates
            $setting->formatted_rates = $setting->formatted_rates;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Honor setting updated successfully',
                'data' => $setting->load(['creator:id_users,email', 'updater:id_users,email'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update honor setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set setting as active
     */
    public function setActive($id)
    {
        $setting = TutorHonorSettings::findOrFail($id);

        DB::beginTransaction();
        
        try {
            $setting->update(['updated_by' => Auth::id()]);
            $setting->setAsActive();

            // Add formatted rates
            $setting->formatted_rates = $setting->formatted_rates;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Honor setting activated successfully',
                'data' => $setting->load(['creator:id_users,email', 'updater:id_users,email'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate honor setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete honor setting (soft delete by deactivating)
     */
    public function destroy($id)
    {
        $setting = TutorHonorSettings::findOrFail($id);

        if ($setting->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active honor setting'
            ], 422);
        }

        try {
            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Honor setting deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete honor setting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate preview honor for given parameters
     */
    public function calculatePreview(Request $request)
    {
        $request->validate([
            'cpb_count' => 'nullable|integer|min:0',
            'pb_count' => 'nullable|integer|min:0',
            'npb_count' => 'nullable|integer|min:0',
            'session_count' => 'nullable|integer|min:0',
            'setting_id' => 'nullable|exists:tutor_honor_settings,id_setting'
        ]);

        $setting = $request->setting_id 
            ? TutorHonorSettings::findOrFail($request->setting_id)
            : TutorHonorSettings::getActiveSetting();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'No honor setting available'
            ], 404);
        }

        $params = [
            'cpb_count' => $request->input('cpb_count', 0),
            'pb_count' => $request->input('pb_count', 0),
            'npb_count' => $request->input('npb_count', 0),
            'session_count' => $request->input('session_count', 0)
        ];

        $calculation = $setting->calculateHonor($params);

        return response()->json([
            'success' => true,
            'data' => [
                'calculation' => $calculation,
                'setting' => $setting,
                'payment_system' => $setting->payment_system,
                'payment_system_name' => $setting->payment_system_name,
                'formatted_total' => CurrencyHelper::formatRupiah($calculation['total_amount'])
            ]
        ]);
    }

    /**
     * Get statistics about honor settings usage
     */
    public function getStatistics()
    {
        try {
            $currentActiveSetting = TutorHonorSettings::getActiveSetting();
            $paymentSystemsCount = TutorHonorSettings::groupBy('payment_system')
                ->selectRaw('payment_system, count(*) as count')
                ->pluck('count', 'payment_system');

            $stats = [
                'total_settings' => TutorHonorSettings::count(),
                'active_settings' => TutorHonorSettings::active()->count(),
                'current_active_setting' => $currentActiveSetting ? [
                    'id' => $currentActiveSetting->id_setting,
                    'payment_system' => $currentActiveSetting->payment_system,
                    'payment_system_name' => $currentActiveSetting->payment_system_name,
                    'formatted_rates' => $currentActiveSetting->formatted_rates,
                    'created_at' => $currentActiveSetting->created_at
                ] : null,
                'payment_systems_count' => $paymentSystemsCount,
                'latest_update' => TutorHonorSettings::latest('updated_at')->first()?->updated_at?->format('Y-m-d H:i:s')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}