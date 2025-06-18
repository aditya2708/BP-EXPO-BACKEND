<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminShelterController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter->load(['kacab', 'wilbin', 'shelter']);
        
        return response()->json([
            'message' => 'Admin Shelter Dashboard',
            'data' => $adminShelter
        ]);
    }

    public function getProfile()
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter->load(['kacab', 'wilbin', 'shelter']);
        
        return response()->json([
            'message' => 'Profile retrieved successfully',
            'data' => $adminShelter
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $adminShelter = $user->adminShelter;

        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'required|string|max:255',
            'alamat_adm' => 'nullable|string',
            'no_hp' => 'nullable|string|max:20',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['nama_lengkap', 'alamat_adm', 'no_hp']);

        if ($request->hasFile('foto')) {
            if ($adminShelter->foto) {
                Storage::delete("public/AdminShelter/{$adminShelter->id_admin_shelter}/{$adminShelter->foto}");
            }
            
            $file = $request->file('foto');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs("public/AdminShelter/{$adminShelter->id_admin_shelter}", $fileName);
            $data['foto'] = $fileName;
        }

        $adminShelter->update($data);
        $adminShelter->load(['kacab', 'wilbin', 'shelter']);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $adminShelter
        ]);
    }
}