<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pengajuan extends Model
{
    use HasFactory;
    protected $table ="kilauindonesia_pendidikan.Pengajuan";
	protected $primaryKey = 'id_pengajuan';
    protected $fillable = [
    'id_pengajuan','nama_barang','jumlah','status','tanggal','berupa','uang','jumlahorang','pembayaran','tanggal_diberikan'];
    
    public function BarangPengajuan()
    {
        return $this->belongsTo(BarangPengajuan::class, 'id_barang', 'id_barang');
    }
    
     public $timestamps = false;
}