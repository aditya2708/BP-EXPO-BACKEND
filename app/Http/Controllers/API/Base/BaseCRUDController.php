<?php

namespace App\Http\Controllers\API\Base;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseCRUDController extends Controller
{
    protected $model;
    protected $modelClass;
    protected $validationRules = [];
    protected $relationships = [];
    protected $searchableFields = [];
    protected $filterableFields = [];
    protected $requiresKacabScope = true;
    protected $perPage = 15;

    public function __construct()
    {
        if (!$this->modelClass) {
            throw new \Exception('Model class must be defined in controller');
        }
        $this->model = app($this->modelClass);
    }

    public function index(Request $request)
    {
        try {
            $query = $this->model::query();
            
            // Apply admin cabang scope if required
            if ($this->requiresKacabScope && auth()->user()->adminCabang) {
                $kacabId = auth()->user()->adminCabang->id_kacab;
                $query = $this->applyCabangScope($query, $kacabId);
            }
            
            // Apply relationships
            if (!empty($this->relationships)) {
                $query->with($this->relationships);
            }
            
            // Apply search
            if ($request->filled('search') && !empty($this->searchableFields)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    foreach ($this->searchableFields as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            }
            
            // Apply filters
            foreach ($this->filterableFields as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->$field);
                }
            }
            
            // Apply additional scopes
            $query = $this->applyAdditionalScopes($query, $request);
            
            // Apply default ordering
            $query = $this->applyDefaultOrdering($query);
            
            $results = $query->paginate($request->get('per_page', $this->perPage));
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diambil',
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in ' . class_basename($this) . ' index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $query = $this->model::query();
            
            if ($this->requiresKacabScope && auth()->user()->adminCabang) {
                $kacabId = auth()->user()->adminCabang->id_kacab;
                $query = $this->applyCabangScope($query, $kacabId);
            }
            
            if (!empty($this->relationships)) {
                $query->with($this->relationships);
            }
            
            $item = $query->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Detail data berhasil diambil',
                'data' => $item
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in ' . class_basename($this) . ' show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->getValidationRules('store'));
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Additional custom validation
            $customValidation = $this->customValidation($request, 'store');
            if ($customValidation !== true) {
                return $customValidation;
            }
            
            DB::beginTransaction();
            
            $data = $this->prepareStoreData($request);
            
            // Add admin cabang scope if required
            if ($this->requiresKacabScope && auth()->user()->adminCabang && !isset($data['id_kacab'])) {
                $data['id_kacab'] = auth()->user()->adminCabang->id_kacab;
            }
            
            $item = $this->model::create($data);
            
            // Post-creation hook
            $this->afterStore($item, $request);
            
            DB::commit();
            
            // Load relationships for response
            if (!empty($this->relationships)) {
                $item->load($this->relationships);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dibuat',
                'data' => $item
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in ' . class_basename($this) . ' store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $query = $this->model::query();
            
            if ($this->requiresKacabScope && auth()->user()->adminCabang) {
                $kacabId = auth()->user()->adminCabang->id_kacab;
                $query = $this->applyCabangScope($query, $kacabId);
            }
            
            $item = $query->findOrFail($id);
            
            $validator = Validator::make($request->all(), $this->getValidationRules('update', $id));
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Additional custom validation
            $customValidation = $this->customValidation($request, 'update', $item);
            if ($customValidation !== true) {
                return $customValidation;
            }
            
            DB::beginTransaction();
            
            $data = $this->prepareUpdateData($request, $item);
            $item->update($data);
            
            // Post-update hook
            $this->afterUpdate($item, $request);
            
            DB::commit();
            
            // Load relationships for response
            if (!empty($this->relationships)) {
                $item->load($this->relationships);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diperbarui',
                'data' => $item
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in ' . class_basename($this) . ' update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $query = $this->model::query();
            
            if ($this->requiresKacabScope && auth()->user()->adminCabang) {
                $kacabId = auth()->user()->adminCabang->id_kacab;
                $query = $this->applyCabangScope($query, $kacabId);
            }
            
            $item = $query->findOrFail($id);
            
            // Check if can be deleted
            $canDelete = $this->canBeDeleted($item);
            if ($canDelete !== true) {
                return $canDelete;
            }
            
            DB::beginTransaction();
            
            // Pre-deletion hook
            $this->beforeDelete($item);
            
            $item->delete();
            
            // Post-deletion hook
            $this->afterDelete($item);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in ' . class_basename($this) . ' destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStatistics()
    {
        try {
            $query = $this->model::query();
            
            if ($this->requiresKacabScope && auth()->user()->adminCabang) {
                $kacabId = auth()->user()->adminCabang->id_kacab;
                $query = $this->applyCabangScope($query, $kacabId);
            }
            
            $stats = $this->calculateStatistics($query);
            
            return response()->json([
                'success' => true,
                'message' => 'Statistik berhasil diambil',
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in ' . class_basename($this) . ' getStatistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Abstract/Override methods for customization
    protected function applyCabangScope($query, $kacabId)
    {
        // Default implementation for models with id_kacab
        if (in_array('id_kacab', $this->model->getFillable())) {
            return $query->where('id_kacab', $kacabId);
        }
        return $query;
    }

    protected function applyAdditionalScopes($query, Request $request)
    {
        return $query; // Override in child classes
    }

    protected function applyDefaultOrdering($query)
    {
        return $query->latest(); // Override in child classes
    }

    protected function getValidationRules($operation, $id = null)
    {
        $rules = $this->validationRules[$operation] ?? $this->validationRules;
        
        // Handle unique validation rules for updates
        if ($operation === 'update' && $id) {
            foreach ($rules as $field => $rule) {
                if (is_string($rule) && strpos($rule, 'unique:') !== false) {
                    $rules[$field] = $rule . ',' . $id . ',' . $this->model->getKeyName();
                }
            }
        }
        
        return $rules;
    }

    protected function customValidation(Request $request, $operation, $item = null)
    {
        return true; // Override in child classes
    }

    protected function prepareStoreData(Request $request)
    {
        return $request->only($this->model->getFillable());
    }

    protected function prepareUpdateData(Request $request, $item)
    {
        return $request->only($this->model->getFillable());
    }

    protected function canBeDeleted($item)
    {
        return true; // Override in child classes
    }

    protected function beforeDelete($item)
    {
        // Override in child classes
    }

    protected function afterStore($item, Request $request)
    {
        // Override in child classes
    }

    protected function afterUpdate($item, Request $request)
    {
        // Override in child classes
    }

    protected function afterDelete($item)
    {
        // Override in child classes
    }

    protected function calculateStatistics($query)
    {
        return [
            'total' => $query->count(),
            'active' => method_exists($this->model, 'scopeActive') ? $query->active()->count() : null
        ];
    }
}