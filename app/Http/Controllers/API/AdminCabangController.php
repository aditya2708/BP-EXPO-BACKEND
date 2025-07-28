<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminCabangController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $adminCabang = $user->adminCabang->load(['kacab']);
        
        return response()->json([
            'message' => 'Admin Cabang Dashboard',
            'data' => $adminCabang
        ]);
    }

    public function getProfile()
    {
        $user = auth()->user();
        $adminCabang = $user->adminCabang->load(['kacab']);
        
        return response()->json([
            'message' => 'Profile retrieved successfully',
            'data' => $adminCabang
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $adminCabang = $user->adminCabang;

        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'no_hp' => 'nullable|string|max:20',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['nama_lengkap', 'alamat', 'no_hp']);

        if ($request->hasFile('foto')) {
            if ($adminCabang->foto) {
                Storage::delete("public/AdminCabang/{$adminCabang->id_admin_cabang}/{$adminCabang->foto}");
            }
            
            $file = $request->file('foto');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs("public/AdminCabang/{$adminCabang->id_admin_cabang}", $fileName);
            $data['foto'] = $fileName;
        }

        $adminCabang->update($data);
        $adminCabang->load(['kacab']);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $adminCabang
        ]);
    }
}