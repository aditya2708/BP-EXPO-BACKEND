<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $table = 'bank';

    // Ubah primary key menjadi id_quran
    protected $primaryKey = 'id_bank';

    protected $fillable = [
        'nama_bank',
    ];
}
