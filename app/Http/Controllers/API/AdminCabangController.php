<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminCabangController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $adminCabang = $user->adminCabang;
        
        return response()->json([
            'message' => 'Admin Cabang Dashboard',
            'data' => [
                'admin_cabang' => $adminCabang,
                'kacab' => $adminCabang->kacab
                // Add more admin cabang specific data here
            ]
        ]);
    }
    
    // Add more admin cabang specific methods here
}