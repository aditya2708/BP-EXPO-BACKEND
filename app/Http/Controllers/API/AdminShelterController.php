<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminShelterController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;
        
        return response()->json([
            'message' => 'Admin Shelter Dashboard',
            'data' => [
                'admin_shelter' => $adminShelter,
                'kacab' => $adminShelter->kacab,
                'wilbin' => $adminShelter->wilbin,
                'shelter' => $adminShelter->shelter
                // Add more admin shelter specific data here
            ]
        ]);
    }
    
    // Add more admin shelter specific methods here
}