<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiExceptionHandler
{
    /**
     * Render API exception response
     */
    public static function render(Request $request, Throwable $exception): JsonResponse
    {
        // Log the exception
        self::logException($exception, $request);

        // Handle specific exception types
        if ($exception instanceof ValidationException) {
            return self::handleValidationException($exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            return self::handleModelNotFoundException($exception);
        }

        if ($exception instanceof AuthenticationException) {
            return self::handleAuthenticationException($exception);
        }

        if ($exception instanceof AuthorizationException) {
            return self::handleAuthorizationException($exception);
        }

        if ($exception instanceof NotFoundHttpException) {
            return self::handleNotFoundHttpException($exception);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return self::handleMethodNotAllowedHttpException($exception);
        }

        if ($exception instanceof QueryException) {
            return self::handleQueryException($exception);
        }

        if ($exception instanceof HttpException) {
            return self::handleHttpException($exception);
        }

        // Handle general exceptions
        return self::handleGeneralException($exception);
    }

    /**
     * Handle validation exceptions
     */
    private static function handleValidationException(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $exception->errors(),
            'error_code' => 'VALIDATION_ERROR'
        ], 422);
    }

    /**
     * Handle model not found exceptions
     */
    private static function handleModelNotFoundException(ModelNotFoundException $exception): JsonResponse
    {
        $model = class_basename($exception->getModel());
        $modelName = self::translateModelName($model);

        return response()->json([
            'success' => false,
            'message' => "{$modelName} tidak ditemukan",
            'error_code' => 'RESOURCE_NOT_FOUND',
            'resource' => strtolower($model)
        ], 404);
    }

    /**
     * Handle authentication exceptions
     */
    private static function handleAuthenticationException(AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Token tidak valid atau sudah expired',
            'error_code' => 'AUTHENTICATION_ERROR'
        ], 401);
    }

    /**
     * Handle authorization exceptions
     */
    private static function handleAuthorizationException(AuthorizationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Tidak memiliki akses untuk melakukan aksi ini',
            'error_code' => 'AUTHORIZATION_ERROR'
        ], 403);
    }

    /**
     * Handle not found HTTP exceptions
     */
    private static function handleNotFoundHttpException(NotFoundHttpException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Endpoint tidak ditemukan',
            'error_code' => 'ENDPOINT_NOT_FOUND'
        ], 404);
    }

    /**
     * Handle method not allowed exceptions
     */
    private static function handleMethodNotAllowedHttpException(MethodNotAllowedHttpException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Method HTTP tidak diizinkan untuk endpoint ini',
            'error_code' => 'METHOD_NOT_ALLOWED',
            'allowed_methods' => $exception->getHeaders()['Allow'] ?? null
        ], 405);
    }

    /**
     * Handle database query exceptions
     */
    private static function handleQueryException(QueryException $exception): JsonResponse
    {
        $errorInfo = $exception->errorInfo;
        
        // Handle specific database errors
        if (isset($errorInfo[1])) {
            switch ($errorInfo[1]) {
                case 1062: // Duplicate entry
                    return response()->json([
                        'success' => false,
                        'message' => 'Data duplikat. Data dengan nilai tersebut sudah ada',
                        'error_code' => 'DUPLICATE_ENTRY'
                    ], 422);
                    
                case 1451: // Foreign key constraint
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak dapat menghapus data karena masih digunakan oleh data lain',
                        'error_code' => 'FOREIGN_KEY_CONSTRAINT'
                    ], 422);
                    
                case 1452: // Cannot add foreign key constraint
                    return response()->json([
                        'success' => false,
                        'message' => 'Data referensi tidak ditemukan',
                        'error_code' => 'INVALID_FOREIGN_KEY'
                    ], 422);
            }
        }

        // Generic database error
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan pada database',
            'error_code' => 'DATABASE_ERROR'
        ], 500);
    }

    /**
     * Handle HTTP exceptions
     */
    private static function handleHttpException(HttpException $exception): JsonResponse
    {
        $statusCode = $exception->getStatusCode();
        $message = $exception->getMessage() ?: 'Terjadi kesalahan HTTP';

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'HTTP_ERROR',
            'status_code' => $statusCode
        ], $statusCode);
    }

    /**
     * Handle general exceptions
     */
    private static function handleGeneralException(Throwable $exception): JsonResponse
    {
        // Don't expose sensitive information in production
        $message = app()->environment(['local', 'testing']) 
            ? $exception->getMessage() 
            : 'Terjadi kesalahan pada server';

        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => 'SERVER_ERROR'
        ];

        // Add debug info in non-production environments
        if (app()->environment(['local', 'testing'])) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        return response()->json($response, 500);
    }

    /**
     * Log exception with context
     */
    private static function logException(Throwable $exception, Request $request): void
    {
        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'request_data' => $request->except(['password', 'password_confirmation', 'token']),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ];

        // Log based on exception severity
        if ($exception instanceof ValidationException || 
            $exception instanceof ModelNotFoundException ||
            $exception instanceof AuthenticationException ||
            $exception instanceof AuthorizationException) {
            Log::info('API Exception: ' . $exception->getMessage(), $context);
        } else {
            Log::error('API Exception: ' . $exception->getMessage(), $context);
        }
    }

    /**
     * Translate model names to Indonesian
     */
    private static function translateModelName(string $model): string
    {
        $translations = [
            'Jenjang' => 'Jenjang',
            'MataPelajaran' => 'Mata Pelajaran',
            'Kelas' => 'Kelas',
            'Materi' => 'Materi',
            'Kurikulum' => 'Kurikulum',
            'Semester' => 'Semester',
            'User' => 'Pengguna',
            'AdminCabang' => 'Admin Cabang',
            'AdminShelter' => 'Admin Shelter',
            'Anak' => 'Anak',
            'Tutor' => 'Tutor',
            'Aktivitas' => 'Aktivitas',
            'Donatur' => 'Donatur'
        ];

        return $translations[$model] ?? $model;
    }

    /**
     * Check if request expects JSON response
     */
    public static function expectsJson(Request $request): bool
    {
        return $request->expectsJson() || 
               $request->is('api/*') || 
               $request->header('Accept') === 'application/json';
    }

    /**
     * Format error response for specific business rules
     */
    public static function businessRuleError(string $message, string $errorCode = 'BUSINESS_RULE_ERROR', int $statusCode = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode
        ], $statusCode);
    }

    /**
     * Format dependency error response
     */
    public static function dependencyError(string $entity, array $dependencies): JsonResponse
    {
        $dependencyList = implode(', ', array_keys($dependencies));
        
        return response()->json([
            'success' => false,
            'message' => "Tidak dapat menghapus {$entity} karena masih digunakan oleh: {$dependencyList}",
            'error_code' => 'DEPENDENCY_ERROR',
            'dependencies' => $dependencies
        ], 422);
    }

    /**
     * Format unique constraint error
     */
    public static function uniqueConstraintError(string $field, string $value): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "Nilai '{$value}' untuk {$field} sudah digunakan",
            'error_code' => 'UNIQUE_CONSTRAINT_ERROR',
            'field' => $field,
            'value' => $value
        ], 422);
    }

    /**
     * Format permission error
     */
    public static function permissionError(string $action, string $resource): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "Tidak memiliki izin untuk {$action} {$resource}",
            'error_code' => 'PERMISSION_ERROR',
            'action' => $action,
            'resource' => $resource
        ], 403);
    }
}