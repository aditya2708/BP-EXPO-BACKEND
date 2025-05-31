<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provinsi extends Model
{
    use HasFactory;

    protected $table = 'provinsi';
    protected $primaryKey = 'id_prov'; // Tambahkan baris ini
    public $incrementing = false; // Sesuaikan dengan database
    protected $keyType = 'int';
    protected $fillable = ['nama'];
}
