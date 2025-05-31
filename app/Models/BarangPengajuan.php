<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class BarangPengajuan extends Model
{
    use HasFactory;
    protected $table ="kilauindonesia_pendidikan.barang_pengajuan";
	protected $primaryKey = 'id_barang';
    protected $fillable = [
     'id_barang','nama_barang'
     
 ];
 
 public function pengajuan()
    {
        return $this->hasMany(Pengajuan::class, 'id_barang', 'id_barang');
    }
 public $timestamps = false;
}