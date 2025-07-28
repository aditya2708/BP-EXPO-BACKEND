<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminPusatController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $adminPusat = $user->adminPusat;
        
        return response()->json([
            'message' => 'Admin Pusat Dashboard',
            'data' => [
                'admin_pusat' => $adminPusat
                // Add more admin pusat specific data here
            ]
        ]);
    }
    
    // Add more admin pusat specific methods here
}