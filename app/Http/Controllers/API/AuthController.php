<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AdminPusat;
use App\Models\AdminCabang;
use App\Models\AdminShelter;
use App\Models\Donatur;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find user by email
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Load role-specific data
        $userData = null;
        switch ($user->level) {
            case 'admin_pusat':
                $userData = $user->adminPusat;
                break;
            case 'admin_cabang':
                $userData = $user->adminCabang;
                break;
            case 'admin_shelter':
                $userData = $user->adminShelter;
                break;
            case 'donatur':
                $userData = $user->donatur;
                break;
        }

        // Create token with abilities based on role
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id_users,
                'email' => $user->email,
                'level' => $user->level,
                'profile' => $userData
            ],
            'token' => $token
        ]);
    }

    

    // The rest of the controller remains the same
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        
        // Load role-specific profile
        $userData = null;
        switch ($user->level) {
            case 'admin_pusat':
                $userData = $user->adminPusat;
                break;
            case 'admin_cabang':
                $userData = $user->adminCabang;
                break;
            case 'admin_shelter':
                $userData = $user->adminShelter;
                break;
            case 'donatur':
                $userData = $user->donatur;
                break;
        }
        
        return response()->json([
            'user' => [
                'id' => $user->id_users,
                'email' => $user->email,
                'level' => $user->level,
                'profile' => $userData
            ]
        ]);
    }

    // Helper method for file uploads
    private function handleFileUpload($file, $folder, $userId)
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs("public/{$folder}/{$userId}", $fileName);
        return $fileName;
    }
}