<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\ApiExceptionHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiErrorHandler
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            
            // Handle successful responses but ensure JSON format for API routes
            if ($this->isApiRoute($request) && !$response instanceof JsonResponse) {
                return $this->formatSuccessResponse($response);
            }
            
            return $response;
            
        } catch (Throwable $exception) {
            // Only handle API routes with JSON error responses
            if ($this->isApiRoute($request)) {
                return ApiExceptionHandler::render($request, $exception);
            }
            
            // Re-throw for web routes to use default handler
            throw $exception;
        }
    }

    /**
     * Check if the request is for an API route
     */
    private function isApiRoute(Request $request): bool
    {
        return $request->is('api/*') || 
               $request->expectsJson() || 
               $request->header('Accept') === 'application/json' ||
               $request->header('Content-Type') === 'application/json';
    }

    /**
     * Format non-JSON responses to JSON for API routes
     */
    private function formatSuccessResponse($response): JsonResponse
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent();
        
        // If already JSON, return as is
        if (is_string($content) && $this->isJson($content)) {
            return response()->json(json_decode($content, true), $statusCode);
        }
        
        // Format plain text or other responses
        return response()->json([
            'success' => $statusCode >= 200 && $statusCode < 300,
            'message' => $statusCode >= 200 && $statusCode < 300 ? 'Success' : 'Error',
            'data' => $content ?: null
        ], $statusCode);
    }

    /**
     * Check if string is valid JSON
     */
    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}