<?php

namespace App\Constants\AdminShelter;

class KeluargaConstants
{
    // Parent status options
    const STATUS_ORTU_YATIM = 'yatim';
    const STATUS_ORTU_PIATU = 'piatu';
    const STATUS_ORTU_YATIM_PIATU = 'yatim piatu';
    const STATUS_ORTU_DHUAFA = 'dhuafa';
    const STATUS_ORTU_NON_DHUAFA = 'non dhuafa';

    const STATUS_ORTU_OPTIONS = [
        self::STATUS_ORTU_YATIM,
        self::STATUS_ORTU_PIATU,
        self::STATUS_ORTU_YATIM_PIATU,
        self::STATUS_ORTU_DHUAFA,
        self::STATUS_ORTU_NON_DHUAFA,
    ];

    // Religion options
    const AGAMA_ISLAM = 'Islam';
    const AGAMA_KRISTEN = 'Kristen';
    const AGAMA_KATOLIK = 'Katolik';
    const AGAMA_BUDDHA = 'Buddha';
    const AGAMA_HINDU = 'Hindu';
    const AGAMA_KONGHUCU = 'Konghucu';

    const AGAMA_OPTIONS = [
        self::AGAMA_ISLAM,
        self::AGAMA_KRISTEN,
        self::AGAMA_KATOLIK,
        self::AGAMA_BUDDHA,
        self::AGAMA_HINDU,
        self::AGAMA_KONGHUCU,
    ];

    // Gender options
    const JENIS_KELAMIN_LAKI = 'Laki-laki';
    const JENIS_KELAMIN_PEREMPUAN = 'Perempuan';

    const JENIS_KELAMIN_OPTIONS = [
        self::JENIS_KELAMIN_LAKI,
        self::JENIS_KELAMIN_PEREMPUAN,
    ];

    // Living arrangement options
    const TINGGAL_AYAH = 'Ayah';
    const TINGGAL_IBU = 'Ibu';
    const TINGGAL_AYAH_IBU = 'Ayah dan Ibu';
    const TINGGAL_WALI = 'Wali';

    const TINGGAL_BERSAMA_OPTIONS = [
        self::TINGGAL_AYAH,
        self::TINGGAL_IBU,
        self::TINGGAL_AYAH_IBU,
        self::TINGGAL_WALI,
    ];

    // Memorization program options
    const HAFALAN_TAHFIDZ = 'Tahfidz';
    const HAFALAN_NON_TAHFIDZ = 'Non-Tahfidz';

    const HAFALAN_OPTIONS = [
        self::HAFALAN_TAHFIDZ,
        self::HAFALAN_NON_TAHFIDZ,
    ];

    // Education level options
    const JENJANG_BELUM_SD = 'belum_sd';
    const JENJANG_SD = 'sd';
    const JENJANG_SMP = 'smp';
    const JENJANG_SMA = 'sma';
    const JENJANG_PERGURUAN_TINGGI = 'perguruan_tinggi';

    const JENJANG_OPTIONS = [
        self::JENJANG_BELUM_SD,
        self::JENJANG_SD,
        self::JENJANG_SMP,
        self::JENJANG_SMA,
        self::JENJANG_PERGURUAN_TINGGI,
    ];

    // Yes/No options
    const YA = 'Ya';
    const TIDAK = 'Tidak';

    const YES_NO_OPTIONS = [
        self::YA,
        self::TIDAK,
    ];

    // Ada/Tidak Ada options
    const ADA = 'Ada';
    const TIDAK_ADA = 'Tidak Ada';

    const ADA_TIDAK_ADA_OPTIONS = [
        self::ADA,
        self::TIDAK_ADA,
    ];

    // Frequency options
    const SELALU = 'Selalu';
    const KADANG_KADANG = 'Kadang-kadang';
    const TIDAK_PERNAH = 'Tidak Pernah';

    const FREQUENCY_OPTIONS = [
        self::SELALU,
        self::KADANG_KADANG,
        self::TIDAK_PERNAH,
    ];

    // Property ownership options
    const MILIK_SENDIRI = 'Milik Sendiri';
    const KONTRAK = 'Kontrak';
    const MENUMPANG = 'Menumpang';
    const LAINNYA = 'Lainnya';

    const KEPEMILIKAN_OPTIONS = [
        self::MILIK_SENDIRI,
        self::KONTRAK,
        self::MENUMPANG,
        self::LAINNYA,
    ];

    // Religious activity options
    const AKTIF = 'Aktif';
    const TIDAK_AKTIF = 'Tidak Aktif';

    const AKTIVITAS_OPTIONS = [
        self::AKTIF,
        self::TIDAK_AKTIF,
    ];

    // Physical condition options
    const KONDISI_NORMAL = 'Normal';
    const KONDISI_DISABILITAS = 'Disabilitas';

    const KONDISI_FISIK_OPTIONS = [
        self::KONDISI_NORMAL,
        self::KONDISI_DISABILITAS,
    ];

    // Child status options
    const STATUS_VALIDASI_AKTIF = 'aktif';
    const STATUS_VALIDASI_TIDAK_AKTIF = 'tidak_aktif';
    const STATUS_VALIDASI_PENDING = 'pending';

    const STATUS_VALIDASI_OPTIONS = [
        self::STATUS_VALIDASI_AKTIF,
        self::STATUS_VALIDASI_TIDAK_AKTIF,
        self::STATUS_VALIDASI_PENDING,
    ];

    // CPB Status options
    const STATUS_CPB_BCPB = 'BCPB';
    const STATUS_CPB_CPB = 'CPB';

    const STATUS_CPB_OPTIONS = [
        self::STATUS_CPB_BCPB,
        self::STATUS_CPB_CPB,
    ];

    // Survey result options
    const HASIL_SURVEY_PENDING = 'pending';
    const HASIL_SURVEY_APPROVED = 'approved';
    const HASIL_SURVEY_REJECTED = 'rejected';

    const HASIL_SURVEY_OPTIONS = [
        self::HASIL_SURVEY_PENDING,
        self::HASIL_SURVEY_APPROVED,
        self::HASIL_SURVEY_REJECTED,
    ];

    // Field validation rules
    const FIELD_RULES = [
        // Required fields
        'required_fields' => [
            'no_kk',
            'kepala_keluarga',
            'status_ortu',
            'nama_ayah',
            'nama_ibu',
            'nick_name',
            'full_name',
            'nik_anak',
            'anak_ke',
            'dari_bersaudara',
            'agama',
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_kelamin',
            'tinggal_bersama',
            'hafalan',
            'pelajaran_favorit',
            'hobi',
            'prestasi',
            'jarak_rumah',
            'transportasi',
            'jenjang',
        ],

        // NIK fields (16 digits)
        'nik_fields' => [
            'no_kk',
            'nik_anak',
            'nik_ayah',
            'nik_ibu',
            'nik_wali',
        ],

        // Numeric fields
        'numeric_fields' => [
            'anak_ke',
            'dari_bersaudara',
            'jarak_rumah',
            'semester',
            'jumlah_tanggungan',
            'jumlah_makan',
            'biaya_pendidikan_perbulan',
            'bantuan_lembaga_formal_lain_sebesar',
        ],

        // Date fields
        'date_fields' => [
            'tanggal_lahir',
            'tanggal_lahir_ayah',
            'tanggal_lahir_ibu',
            'tanggal_lahir_wali',
            'tanggal_kematian_ayah',
            'tanggal_kematian_ibu',
        ],

        // File fields
        'file_fields' => [
            'foto',
        ],
    ];

    // Parent requirements based on status
    const PARENT_REQUIREMENTS = [
        self::STATUS_ORTU_YATIM => [
            'father_deceased' => true,
            'mother_alive' => true,
            'guardian_required' => false,
            'required_father_fields' => ['tanggal_kematian_ayah', 'penyebab_kematian_ayah'],
            'required_mother_fields' => [
                'nik_ibu', 'agama_ibu', 'tempat_lahir_ibu', 'tanggal_lahir_ibu',
                'alamat_ibu', 'penghasilan_ibu'
            ],
        ],
        
        self::STATUS_ORTU_PIATU => [
            'father_deceased' => false,
            'mother_alive' => false,
            'guardian_required' => false,
            'required_father_fields' => [
                'nik_ayah', 'agama_ayah', 'tempat_lahir_ayah', 'tanggal_lahir_ayah',
                'alamat_ayah', 'penghasilan_ayah'
            ],
            'required_mother_fields' => ['tanggal_kematian_ibu', 'penyebab_kematian_ibu'],
        ],
        
        self::STATUS_ORTU_YATIM_PIATU => [
            'father_deceased' => true,
            'mother_alive' => false,
            'guardian_required' => true,
            'required_father_fields' => ['tanggal_kematian_ayah', 'penyebab_kematian_ayah'],
            'required_mother_fields' => ['tanggal_kematian_ibu', 'penyebab_kematian_ibu'],
            'required_guardian_fields' => [
                'nik_wali', 'nama_wali', 'agama_wali', 'tempat_lahir_wali',
                'tanggal_lahir_wali', 'alamat_wali', 'penghasilan_wali', 'hub_kerabat_wali'
            ],
        ],
        
        self::STATUS_ORTU_DHUAFA => [
            'father_deceased' => false,
            'mother_alive' => true,
            'guardian_required' => false,
            'required_father_fields' => [
                'nik_ayah', 'agama_ayah', 'tempat_lahir_ayah', 'tanggal_lahir_ayah',
                'alamat_ayah', 'penghasilan_ayah'
            ],
            'required_mother_fields' => [
                'nik_ibu', 'agama_ibu', 'tempat_lahir_ibu', 'tanggal_lahir_ibu',
                'alamat_ibu', 'penghasilan_ibu'
            ],
        ],
        
        self::STATUS_ORTU_NON_DHUAFA => [
            'father_deceased' => false,
            'mother_alive' => true,
            'guardian_required' => false,
            'required_father_fields' => [
                'nik_ayah', 'agama_ayah', 'tempat_lahir_ayah', 'tanggal_lahir_ayah',
                'alamat_ayah', 'penghasilan_ayah'
            ],
            'required_mother_fields' => [
                'nik_ibu', 'agama_ibu', 'tempat_lahir_ibu', 'tanggal_lahir_ibu',
                'alamat_ibu', 'penghasilan_ibu'
            ],
        ],
    ];

    // Education requirements based on level
    const EDUCATION_REQUIREMENTS = [
        self::JENJANG_BELUM_SD => [],
        self::JENJANG_SD => ['kelas', 'nama_sekolah', 'alamat_sekolah'],
        self::JENJANG_SMP => ['kelas', 'nama_sekolah', 'alamat_sekolah'],
        self::JENJANG_SMA => ['kelas', 'nama_sekolah', 'alamat_sekolah', 'jurusan'],
        self::JENJANG_PERGURUAN_TINGGI => ['semester', 'jurusan', 'nama_pt', 'alamat_pt'],
    ];

    // Conditional field requirements
    const CONDITIONAL_REQUIREMENTS = [
        'kondisi_fisik_anak' => [
            self::KONDISI_DISABILITAS => ['keterangan_disabilitas'],
        ],
        'bantuan_lembaga_formal_lain' => [
            self::YA => ['bantuan_lembaga_formal_lain_sebesar'],
        ],
        'pengurus_organisasi' => [
            self::YA => ['pengurus_organisasi_sebagai'],
        ],
        'bank_choice' => [
            'yes' => ['id_bank', 'no_rek', 'an_rek'],
        ],
        'telp_choice' => [
            'yes' => ['no_tlp', 'an_tlp'],
        ],
    ];

    // File upload constraints
    const FILE_CONSTRAINTS = [
        'foto' => [
            'max_size' => 2048, // KB
            'allowed_types' => ['image/jpeg', 'image/jpg', 'image/png'],
            'required' => false,
        ],
    ];

    // Database table names
    const TABLE_KELUARGA = 'keluarga';
    const TABLE_AYAH = 'ayah';
    const TABLE_IBU = 'ibu';
    const TABLE_WALI = 'wali';
    const TABLE_ANAK = 'anak';
    const TABLE_ANAK_PENDIDIKAN = 'anak_pendidikan';
    const TABLE_SURVEY = 'survey';

    // Response messages
    const MESSAGES = [
        'success' => [
            'created' => 'Keluarga dan Anak berhasil ditambahkan',
            'updated' => 'Data keluarga berhasil diperbarui',
            'deleted' => 'Keluarga berhasil dihapus',
        ],
        'error' => [
            'unauthorized' => 'Unauthorized access',
            'validation' => 'Validation Error',
            'not_found' => 'Data keluarga tidak ditemukan',
            'has_active_children' => 'Keluarga memiliki anak aktif yang terdaftar',
            'create_failed' => 'Gagal menambahkan data',
            'update_failed' => 'Gagal memperbarui data',
            'delete_failed' => 'Gagal menghapus keluarga',
        ],
    ];

    // Helper methods
    public static function isValidStatusOrtu(string $status): bool
    {
        return in_array($status, self::STATUS_ORTU_OPTIONS);
    }

    public static function isValidAgama(string $agama): bool
    {
        return in_array($agama, self::AGAMA_OPTIONS);
    }

    public static function isValidJenisKelamin(string $jenisKelamin): bool
    {
        return in_array($jenisKelamin, self::JENIS_KELAMIN_OPTIONS);
    }

    public static function isValidJenjang(string $jenjang): bool
    {
        return in_array($jenjang, self::JENJANG_OPTIONS);
    }

    public static function getParentRequirements(string $statusOrtu): array
    {
        return self::PARENT_REQUIREMENTS[$statusOrtu] ?? [];
    }

    public static function getEducationRequirements(string $jenjang): array
    {
        return self::EDUCATION_REQUIREMENTS[$jenjang] ?? [];
    }

    public static function getConditionalRequirements(string $field, string $value): array
    {
        return self::CONDITIONAL_REQUIREMENTS[$field][$value] ?? [];
    }

    public static function isGuardianRequired(string $statusOrtu): bool
    {
        $requirements = self::getParentRequirements($statusOrtu);
        return $requirements['guardian_required'] ?? false;
    }

    public static function getFatherRequiredFields(string $statusOrtu): array
    {
        $requirements = self::getParentRequirements($statusOrtu);
        return $requirements['required_father_fields'] ?? [];
    }

    public static function getMotherRequiredFields(string $statusOrtu): array
    {
        $requirements = self::getParentRequirements($statusOrtu);
        return $requirements['required_mother_fields'] ?? [];
    }

    public static function getGuardianRequiredFields(string $statusOrtu): array
    {
        $requirements = self::getParentRequirements($statusOrtu);
        return $requirements['required_guardian_fields'] ?? [];
    }
}