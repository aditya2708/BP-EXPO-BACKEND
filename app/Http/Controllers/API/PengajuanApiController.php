<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PengajuanApiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
     public function index()
    {
        $pengajuan = Pengajuan::with('barang')->get();
        return response()->json([
            'success' => true,
            'message' => 'Daftar Data Pengajuan',
            'data' => $pengajuan
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_barang' => 'required|string|max:255',
            'jumlah' => 'required|integer',
            'status' => 'nullable|string',
            'tanggal' => 'required|date',
            'berupa' => 'nullable|string',
            'uang' => 'nullable|numeric',
            'jumlahorang' => 'nullable|integer',
            'pembayaran' => 'nullable|string',
            'tanggal_diberikan' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $pengajuan = Pengajuan::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan berhasil ditambahkan',
            'data' => $pengajuan
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
     public function show($id)
    {
        $pengajuan = Pengajuan::with('barang')->find($id);

        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail Pengajuan',
            'data' => $pengajuan
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_barang' => 'string|max:255',
            'jumlah' => 'integer',
            'status' => 'nullable|string',
            'tanggal' => 'date',
            'berupa' => 'nullable|string',
            'uang' => 'nullable|numeric',
            'jumlahorang' => 'nullable|integer',
            'pembayaran' => 'nullable|string',
            'tanggal_diberikan' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $pengajuan = Pengajuan::find($id);

        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan'
            ], 404);
        }

        $pengajuan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan berhasil diupdate',
            'data' => $pengajuan
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $pengajuan = Pengajuan::find($id);

        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan'
            ], 404);
        }

        $pengajuan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan berhasil dihapus'
        ], 200);
    }
}