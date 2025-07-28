<?php

namespace App\Services\AdminShelter;

use App\Models\Bank;
use App\Models\Kacab;
use App\Models\Shelter;
use App\Models\Wilbin;
use Illuminate\Support\Facades\Auth;

class KeluargaValidationService
{
    public function validateAuthAccess(): bool
    {
        $user = Auth::user();
        return $user && $user->adminShelter && $user->adminShelter->shelter;
    }

    public function getShelterId(): int
    {
        $user = Auth::user();
        return $user->adminShelter->id_shelter;
    }

    public function getUserShelterData(): array
    {
        $user = Auth::user();
        return [
            'id_shelter' => $user->adminShelter->id_shelter,
            'id_kacab' => $user->adminShelter->id_kacab,
            'id_wilbin' => $user->adminShelter->id_wilbin,
            'user_name' => $user->name
        ];
    }

    public function getDropdownData(): array
    {
        return [
            'kacab' => Kacab::all(['id_kacab', 'nama_kacab']),
            'bank' => Bank::all(['id_bank', 'nama_bank']),
        ];
    }

    public function getWilbinByKacab(string $idKacab): array
    {
        return Wilbin::where('id_kacab', $idKacab)
                     ->get(['id_wilbin', 'nama_wilbin'])
                     ->toArray();
    }

    public function getShelterByWilbin(string $idWilbin): array
    {
        $user = Auth::user();
        $shelterId = $user->adminShelter->id_shelter;
        
        return Shelter::where('id_wilbin', $idWilbin)
                      ->where('id_shelter', $shelterId)
                      ->get(['id_shelter', 'nama_shelter'])
                      ->toArray();
    }

    public function validateStatusOrtu(string $statusOrtu): bool
    {
        $validStatuses = ['yatim', 'piatu', 'yatim piatu', 'dhuafa', 'non dhuafa'];
        return in_array($statusOrtu, $validStatuses);
    }

    public function validateJenjang(string $jenjang): bool
    {
        $validJenjang = ['belum_sd', 'sd', 'smp', 'sma', 'perguruan_tinggi'];
        return in_array($jenjang, $validJenjang);
    }

    public function validateAgama(string $agama): bool
    {
        $validAgama = ['Islam', 'Kristen', 'Katolik', 'Buddha', 'Hindu', 'Konghucu'];
        return in_array($agama, $validAgama);
    }

    public function validateJenisKelamin(string $jenisKelamin): bool
    {
        $validJenisKelamin = ['Laki-laki', 'Perempuan'];
        return in_array($jenisKelamin, $validJenisKelamin);
    }

    public function validateTinggalBersama(string $tinggalBersama): bool
    {
        $validTinggalBersama = ['Ayah', 'Ibu', 'Ayah dan Ibu', 'Wali'];
        return in_array($tinggalBersama, $validTinggalBersama);
    }

    public function validateHafalan(string $hafalan): bool
    {
        $validHafalan = ['Tahfidz', 'Non-Tahfidz'];
        return in_array($hafalan, $validHafalan);
    }

    public function validateYesNoField(string $value): bool
    {
        $validValues = ['Ya', 'Tidak'];
        return in_array($value, $validValues);
    }

    public function validateAdaTidakAdaField(string $value): bool
    {
        $validValues = ['Ada', 'Tidak Ada'];
        return in_array($value, $validValues);
    }

    public function validateFrequencyField(string $value): bool
    {
        $validValues = ['Selalu', 'Kadang-kadang', 'Tidak Pernah'];
        return in_array($value, $validValues);
    }

    public function validateKepemilikanRumahTanah(string $value): bool
    {
        $validValues = ['Milik Sendiri', 'Kontrak', 'Menumpang', 'Lainnya'];
        return in_array($value, $validValues);
    }

    public function validateMajelisTaklim(string $value): bool
    {
        $validValues = ['Aktif', 'Tidak Aktif'];
        return in_array($value, $validValues);
    }

    public function validateKondisiFisik(string $value): bool
    {
        $validValues = ['Normal', 'Disabilitas'];
        return in_array($value, $validValues);
    }

    public function validateNIK(string $nik): bool
    {
        return preg_match('/^[0-9]{16}$/', $nik);
    }

    public function validateNoKK(string $noKK): bool
    {
        return preg_match('/^[0-9]{16}$/', $noKK);
    }

    public function isParentAlive(string $statusOrtu, string $parentType): bool
    {
        switch ($parentType) {
            case 'ayah':
                return in_array($statusOrtu, ['piatu', 'dhuafa', 'non dhuafa']);
            case 'ibu':
                return in_array($statusOrtu, ['yatim', 'dhuafa', 'non dhuafa']);
            default:
                return false;
        }
    }

    public function isGuardianRequired(string $statusOrtu): bool
    {
        return $statusOrtu === 'yatim piatu';
    }

    public function getEducationRequiredFields(string $jenjang): array
    {
        switch ($jenjang) {
            case 'sd':
            case 'smp':
                return ['kelas', 'nama_sekolah', 'alamat_sekolah'];
            case 'sma':
                return ['kelas', 'nama_sekolah', 'alamat_sekolah', 'jurusan'];
            case 'perguruan_tinggi':
                return ['semester', 'jurusan', 'nama_pt', 'alamat_pt'];
            default:
                return [];
        }
    }

    public function validateImageFile($file): bool
    {
        if (!$file) return true; // File is optional
        
        $allowedMimes = ['jpg', 'jpeg', 'png'];
        $maxSize = 2048; // 2MB in KB
        
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize() / 1024; // Convert to KB
        
        return in_array($extension, $allowedMimes) && $size <= $maxSize;
    }
}