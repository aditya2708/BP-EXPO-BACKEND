<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'company'; 
    protected $primaryKey = 'id_com'; 

    // Kolom yang dapat diisi secara massal
    protected $fillable = [
        'name_company',     // Nama perusahaan
        'name_direktur',    // Nama direktur
        'sk',               // Surat keputusan
        'npwp',             // Nomor NPWP
        'no_hp',            // Nomor telepon
        'berdiri_tahun',    // Tahun berdiri (date)
        'logo',             // Logo perusahaan
        'gambarbglogin',    // Gambar background login
        'icon',             // Ikon perusahaan
        'nama_web',         // Nama website
        'email',            // Email perusahaan
        'keywords',         // Kata kunci SEO
        'metatext',         // Meta teks untuk SEO
        'alamat',           // Alamat perusahaan
        'peta',             // Peta lokasi
        'background_admin', // Background halaman admin
        'footer_web',       // Footer website
        'footer_web2',      // Footer tambahan
        'nama_web_admin',   // Nama website admin
    ];
    
}
