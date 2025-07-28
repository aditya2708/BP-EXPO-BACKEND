<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, $role)
    {
        if (!auth()->check() || auth()->user()->level !== $role) {
            return response()->json([
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        return $next($request);
    }
}