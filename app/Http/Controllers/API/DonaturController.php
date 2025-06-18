<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DonaturController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $donatur = $user->donatur->load(['bank', 'shelter', 'wilbin', 'kacab', 'anak']);
        
        return response()->json([
            'message' => 'Donatur Dashboard',
            'data' => $donatur
        ]);
    }

    public function getProfile()
    {
        $user = auth()->user();
        $donatur = $user->donatur->load(['bank', 'shelter', 'wilbin', 'kacab', 'anak']);
        
        return response()->json([
            'message' => 'Profile retrieved successfully',
            'data' => $donatur
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $donatur = $user->donatur;

        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'no_hp' => 'nullable|string|max:20',
            'no_rekening' => 'nullable|string|max:50',
            'diperuntukan' => 'nullable|string',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['nama_lengkap', 'alamat', 'no_hp', 'no_rekening', 'diperuntukan']);

        if ($request->hasFile('foto')) {
            if ($donatur->foto) {
                Storage::delete("public/Donatur/{$donatur->id_donatur}/{$donatur->foto}");
            }
            
            $file = $request->file('foto');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs("public/Donatur/{$donatur->id_donatur}", $fileName);
            $data['foto'] = $fileName;
        }

        $donatur->update($data);
        $donatur->load(['bank', 'shelter', 'wilbin', 'kacab', 'anak']);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $donatur
        ]);
    }
}