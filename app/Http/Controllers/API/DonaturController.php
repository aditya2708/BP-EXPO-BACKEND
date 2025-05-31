<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DonaturController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $donatur = $user->donatur;
        
        return response()->json([
            'message' => 'Donatur Dashboard',
            'data' => [
                'donatur' => $donatur,
                'bank' => $donatur->bank,
                'shelter' => $donatur->shelter,
                'wilbin' => $donatur->wilbin,
                'kacab' => $donatur->kacab,
                'anak' => $donatur->anak
                // Add more donatur specific data here
            ]
        ]);
    }
    
    // Add more donatur specific methods here
}