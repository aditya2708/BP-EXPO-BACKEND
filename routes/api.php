<?php
use App\Http\Controllers\API\BeritaApiController;
use App\Http\Controllers\API\KategoriBeritaController;
use App\Http\Controllers\API\KomentarController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdminPusatController;
use App\Http\Controllers\API\AdminCabangController;
use App\Http\Controllers\API\AdminShelterController;
use App\Http\Controllers\API\DonaturController;
use App\Http\Controllers\API\AdminShelter\AdminShelterAnakController;
use App\Http\Controllers\API\AdminShelter\AdminShelterRaportController;
use App\Http\Controllers\API\AdminShelter\AdminShelterPrestasiController;
use App\Http\Controllers\API\AdminShelter\AdminShelterRiwayatController;
use App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController;
use App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController;
use App\Http\Controllers\API\AdminShelter\AdminShelterTutorController;
use App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController;
use App\Http\Controllers\API\AdminShelter\AdminShelterSurveyController;
use App\Http\Controllers\API\AdminShelter\AdminShelterSurveyValidasiController;
use App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController;
use App\Http\Controllers\API\AdminShelter\AktivitasController;
use App\Http\Controllers\API\AdminShelter\SemesterController;
use App\Http\Controllers\API\TutorAttendanceController;

Route::get('/test', function () {
    return response()->json(['message' => 'API test route is working!']);
});

// Public authentication endpoints
Route::post('/auth/login', [AuthController::class, 'login']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Shared authentication endpoints
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Admin Pusat routes
    Route::middleware('role:admin_pusat')->prefix('admin-pusat')->group(function () {
        Route::get('/dashboard', [AdminPusatController::class, 'dashboard']);
        // Add more admin pusat routes here

    // Admin Pusat Anak Management Routes
    Route::get('/anak', [AdminPusatAnakController::class, 'index']);
    Route::get('/anak/{id}', [AdminPusatAnakController::class, 'show']);
    Route::post('/anak/{id}', [AdminPusatAnakController::class, 'update']);
    Route::post('/anak/{id}/toggle-status', [AdminPusatAnakController::class, 'toggleStatus']);
    Route::get('/anak-summary', [AdminPusatAnakController::class, 'getSummary']);

     // Raport routes
    Route::get('/anak/{childId}/raport', [AdminPusatRaportController::class, 'index']);
    Route::get('/anak/{childId}/raport/{raportId}', [AdminPusatRaportController::class, 'show']);
    
    // Prestasi routes
    Route::get('/anak/{anakId}/prestasi', [AdminPusatPrestasiController::class, 'index']);
    Route::get('/anak/{anakId}/prestasi/{prestasiId}', [AdminPusatPrestasiController::class, 'show']);
    
    // Riwayat routes
    Route::get('/anak/{anakId}/riwayat', [AdminPusatRiwayatController::class, 'index']);
    Route::get('/anak/{anakId}/riwayat/{riwayatId}', [AdminPusatRiwayatController::class, 'show']);
    
    // Admin Pusat Keluarga Management Routes
Route::get('/keluarga', [AdminPusatKeluargaController::class, 'index']);
Route::post('/keluarga', [AdminPusatKeluargaController::class, 'store']);
Route::get('/keluarga/{id}', [AdminPusatKeluargaController::class, 'show']);
Route::post('/keluarga/{id}', [AdminPusatKeluargaController::class, 'update']);
Route::delete('/keluarga/{id}', [AdminPusatKeluargaController::class, 'destroy']);
Route::get('/keluarga-dropdown', [AdminPusatKeluargaController::class, 'getDropdownData']);
Route::get('/keluarga-wilbin/{id_kacab}', [AdminPusatKeluargaController::class, 'getWilbinByKacab']);
Route::get('/keluarga-shelter/{id_wilbin}', [AdminPusatKeluargaController::class, 'getShelterByWilbin']);
    
});
    
    // Admin Cabang routes
    Route::middleware('role:admin_cabang')->prefix('admin-cabang')->group(function () {
        Route::get('/dashboard', [AdminCabangController::class, 'dashboard']);
        // Add more admin cabang routes here
    });
    
    // Admin Shelter routes
    Route::middleware('role:admin_shelter')->prefix('admin-shelter')->group(function () {
        Route::get('/dashboard', [AdminShelterController::class, 'dashboard']);
        
    
        Route::get('/anak', [AdminShelterAnakController::class, 'index']);
        Route::post('/anak', [AdminShelterAnakController::class, 'store']);
        Route::get('/anak/{id}', [AdminShelterAnakController::class, 'show']);
        Route::post('/anak/{id}', [AdminShelterAnakController::class, 'update']);
        Route::delete('/anak/{id}', [AdminShelterAnakController::class, 'destroy']);
        Route::post('/anak/{id}/toggle-status', [AdminShelterAnakController::class, 'toggleStatus']);
    
        Route::get('/anak/{childId}/raport', [AdminShelterRaportController::class, 'index']);
        Route::post('/anak/{childId}/raport/create', [AdminShelterRaportController::class, 'store']);
        Route::get('/anak/{childId}/raport/{raportId}', [AdminShelterRaportController::class, 'show']);
        Route::post('/anak/{childId}/raport/{raportId}/update', [AdminShelterRaportController::class, 'update']);
        Route::delete('/anak/{childId}/raport/{raportId}', [AdminShelterRaportController::class, 'destroy']);

        Route::get('/anak/{anakId}/prestasi', [AdminShelterPrestasiController::class, 'index']);
        Route::post('/anak/{anakId}/prestasi', [AdminShelterPrestasiController::class, 'store']);
        Route::get('/anak/{anakId}/prestasi/{prestasiId}', [AdminShelterPrestasiController::class, 'show']);
        Route::post('/anak/{anakId}/prestasi/{prestasiId}', [AdminShelterPrestasiController::class, 'update']);
        Route::delete('/anak/{anakId}/prestasi/{prestasiId}', [AdminShelterPrestasiController::class, 'destroy']);
   
        Route::get('/anak/{anakId}/riwayat', [AdminShelterRiwayatController::class, 'index']);
        Route::post('/anak/{anakId}/riwayat', [AdminShelterRiwayatController::class, 'store']);
        Route::get('/anak/{anakId}/riwayat/{riwayatId}', [AdminShelterRiwayatController::class, 'show']);
        Route::post('/anak/{anakId}/riwayat/{riwayatId}', [AdminShelterRiwayatController::class, 'update']);
        Route::delete('/anak/{anakId}/riwayat/{riwayatId}', [AdminShelterRiwayatController::class, 'destroy']);

        Route::get('/anak/{childId}/surat', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'index']);
        Route::post('/anak/{childId}/surat', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'store']);
        Route::get('/anak/{childId}/surat/{suratId}', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'show']);
        Route::post('/anak/{childId}/surat/{suratId}', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'update']);
        Route::delete('/anak/{childId}/surat/{suratId}', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'destroy']);
        Route::put('/anak/{childId}/surat/{suratId}/read', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'markAsRead']);
        
        Route::get('/tutor', [AdminShelterTutorController::class, 'index']);
        Route::post('/tutor', [AdminShelterTutorController::class, 'store']);
        Route::get('/tutor/{id}', [AdminShelterTutorController::class, 'show']);
        Route::post('/tutor/{id}', [AdminShelterTutorController::class, 'update']);
        Route::delete('/tutor/{id}', [AdminShelterTutorController::class, 'destroy']);

        // Kelompok (Group) Management Routes
        Route::get('/kelompok', [AdminShelterKelompokController::class, 'index']);
        Route::post('/kelompok', [AdminShelterKelompokController::class, 'store']);
        Route::get('/kelompok/{id}', [AdminShelterKelompokController::class, 'show']);
        Route::post('/kelompok/{id}', [AdminShelterKelompokController::class, 'update']);
        Route::delete('/kelompok/{id}', [AdminShelterKelompokController::class, 'destroy']);
        Route::get('/kelompok-levels', [AdminShelterKelompokController::class, 'getLevels']);
        Route::get('/kelompok/available-children/{id_shelter}', [AdminShelterKelompokController::class, 'getAvailableChildren']);
        Route::get('/kelompok/{id_kelompok}/children', [AdminShelterKelompokController::class, 'getGroupChildren']);
        Route::post('/kelompok/{id_kelompok}/add-child', [AdminShelterKelompokController::class, 'addChildToGroup']);
        Route::delete('/kelompok/{id_kelompok}/remove-child/{id_anak}', [AdminShelterKelompokController::class, 'removeChildFromGroup']);
        Route::post('/move-child/{id_anak}', [AdminShelterKelompokController::class, 'moveChildToShelter']);

        // Keluarga (Family) Management Routes
        Route::get('/keluarga', [AdminShelterKeluargaController::class, 'index']);
        Route::post('/keluarga', [AdminShelterKeluargaController::class, 'store']);
        Route::get('/keluarga/{id}', [AdminShelterKeluargaController::class, 'show']);
        Route::post('/keluarga/{id}', [AdminShelterKeluargaController::class, 'update']);
        Route::delete('/keluarga/{id}', [AdminShelterKeluargaController::class, 'destroy']);
        Route::get('/keluarga-dropdown', [AdminShelterKeluargaController::class, 'getDropdownData']);
        Route::get('/keluarga-wilbin/{id_kacab}', [AdminShelterKeluargaController::class, 'getWilbinByKacab']);
        Route::get('/keluarga-shelter/{id_wilbin}', [AdminShelterKeluargaController::class, 'getShelterByWilbin']);
        // Pengajuan Anak (Child Application) Routes
        Route::get('/pengajuan-anak/search-keluarga', [AdminShelterPengajuanAnakController::class, 'searchKeluarga']);
        Route::post('/pengajuan-anak/validate-kk', [AdminShelterPengajuanAnakController::class, 'validateKK']);
        Route::post('/pengajuan-anak/submit', [AdminShelterPengajuanAnakController::class, 'submitAnak']);
        // Add Survey routes here
        Route::get('/survey', [AdminShelterSurveyController::class, 'index']);
        Route::get('/survey/{id_keluarga}', [AdminShelterSurveyController::class, 'show']);
        Route::post('/survey/{id_keluarga}', [AdminShelterSurveyController::class, 'store']);
        Route::delete('/survey/{id_keluarga}', [AdminShelterSurveyController::class, 'destroy']);
        // Add Survey Validation routes here
        Route::get('/survey-validation', [AdminShelterSurveyValidasiController::class, 'index']);
        Route::post('/survey-validation/{id_survey}', [AdminShelterSurveyValidasiController::class, 'validateSurvey']);
        Route::get('/survey-validation/summary', [AdminShelterSurveyValidasiController::class, 'getValidationSummary']);
    
        Route::get('/aktivitas', [AktivitasController::class, 'index']);
Route::post('/aktivitas', [AktivitasController::class, 'store']);
Route::get('/aktivitas/{id}', [AktivitasController::class, 'show']);
Route::post('/aktivitas/{id}', [AktivitasController::class, 'update']);
Route::delete('/aktivitas/{id}', [AktivitasController::class, 'destroy']);
    
        // QR Token routes
    Route::prefix('qr-tokens')->group(function () {
        Route::post('/generate', [App\Http\Controllers\API\QrTokenController::class, 'generate']);
        Route::post('/generate-batch', [App\Http\Controllers\API\QrTokenController::class, 'generateBatch']);
        Route::post('/validate-token', [App\Http\Controllers\API\QrTokenController::class, 'validateToken']);
        Route::get('/student/{id_anak}', [App\Http\Controllers\API\QrTokenController::class, 'getActiveToken']);
        Route::post('/invalidate', [App\Http\Controllers\API\QrTokenController::class, 'invalidate']);
    });
    
    // Attendance routes
    Route::prefix('attendance')->group(function () {
        Route::post('/record-by-qr', [App\Http\Controllers\API\AttendanceController::class, 'recordAttendanceByQr']);
        Route::post('/record-manual', [App\Http\Controllers\API\AttendanceController::class, 'recordAttendanceManually']);
        Route::get('/activity/{id_aktivitas}', [App\Http\Controllers\API\AttendanceController::class, 'getByActivity']);
        Route::get('/student/{id_anak}', [App\Http\Controllers\API\AttendanceController::class, 'getByStudent']);
        Route::post('/{id_absen}/verify', [App\Http\Controllers\API\AttendanceController::class, 'manualVerify']);
        Route::post('/{id_absen}/reject', [App\Http\Controllers\API\AttendanceController::class, 'rejectVerification']);
        Route::get('/{id_absen}/verification-history', [App\Http\Controllers\API\AttendanceController::class, 'getVerificationHistory']);
    });

       // Tutor attendance routes
Route::prefix('tutor-attendance')->group(function () {
    Route::post('/generate-token', [TutorAttendanceController::class, 'generateTutorToken']);
    Route::post('/record-by-qr', [TutorAttendanceController::class, 'recordTutorAttendanceByQr']);
    Route::post('/record-manual', [TutorAttendanceController::class, 'recordTutorAttendanceManually']);
    Route::get('/activity/{id_aktivitas}', [TutorAttendanceController::class, 'getTutorAttendanceByActivity']);
    Route::get('/tutor/{id_tutor}', [TutorAttendanceController::class, 'getTutorAttendanceHistory']);
    Route::post('/validate-tutor-token', [TutorAttendanceController::class, 'validateTutorToken']);
});
    
    // Attendance Report routes
    Route::prefix('attendance-reports')->group(function () {
        Route::post('/statistics', [App\Http\Controllers\API\AttendanceReportController::class, 'generateStats']);
        Route::post('/tutor-payment', [App\Http\Controllers\API\AttendanceReportController::class, 'generateTutorPaymentReport']);
        Route::post('/export', [App\Http\Controllers\API\AttendanceReportController::class, 'exportAttendanceData']);
    });

Route::get('/materi/by-level', [App\Http\Controllers\API\AdminShelter\MateriController::class, 'getByLevel']);

// Route spesifik dulu
Route::get('/semester/active', [SemesterController::class, 'getActive']);
Route::get('/semester/tahun-ajaran', [SemesterController::class, 'getTahunAjaran']);
Route::get('/semester/{id}/statistics', [SemesterController::class, 'statistics']);
Route::post('/semester/{id}/set-active', [SemesterController::class, 'setActive']);

// Route umum terakhir
Route::get('/semester', [SemesterController::class, 'index']);
Route::post('/semester', [SemesterController::class, 'store']);
Route::get('/semester/{id}', [SemesterController::class, 'show']);
Route::put('/semester/{id}', [SemesterController::class, 'update']);
Route::delete('/semester/{id}', [SemesterController::class, 'destroy']);

// Jenis Penilaian route
Route::get('/jenis-penilaian', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'getJenisPenilaian']);

// Penilaian routes
Route::get('/penilaian', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'index']);
Route::post('/penilaian', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'store']);
Route::get('/penilaian/{id}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'show']);
Route::put('/penilaian/{id}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'update']);
Route::delete('/penilaian/{id}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'destroy']);
Route::get('/penilaian/anak/{idAnak}/semester/{idSemester}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'getByAnakSemester']);
Route::post('/penilaian/bulk', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'bulkStore']);
Route::post('/penilaian/calculate-nilai-akhir', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'calculateNilaiAkhir']);

// Nilai Sikap routes
Route::get('/nilai-sikap/{idAnak}/{idSemester}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'getNilaiSikap']);
Route::post('/nilai-sikap', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'storeNilaiSikap']);
Route::put('/nilai-sikap/{id}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'updateNilaiSikap']);

// Raport routes
Route::get('/raport', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'index']);
Route::post('/raport/generate', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'generate']);
Route::get('/raport/{id}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'show']);
Route::put('/raport/{id}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'update']);
Route::delete('/raport/{id}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'destroy']);
Route::get('/raport/anak/{idAnak}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'getByAnak']);
Route::post('/raport/{id}/publish', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'publish']);
Route::post('/raport/{id}/archive', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'archive']);
Route::get('/raport/preview/{idAnak}/{idSemester}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'getPreviewData']);
Route::put('/raport/{idRaport}/detail/{idDetail}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'updateDetail']);
Route::get('/raport/check-existing/{idAnak}/{idSemester}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'checkExistingRaport']);
});
    
    // Donatur routes
    Route::middleware('role:donatur')
    ->prefix('donatur')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [DonaturController::class, 'dashboard']);

        // Donatur Anak (Children)
        Route::get('/children', [App\Http\Controllers\Api\Donatur\DonaturAnakController::class, 'index']);
        Route::get('/children/{childId}', [App\Http\Controllers\Api\Donatur\DonaturAnakController::class, 'show']);

        // Surat (Messages)
        Route::get('/children/{childId}/surat', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'index']);
        Route::post('/children/{childId}/surat', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'store']);
        Route::get('/children/{childId}/surat/{suratId}', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'show']);
        Route::put('/children/{childId}/surat/{suratId}/read', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'markAsRead']);

        // Prestasi (Achievements)
        Route::get('/children/{childId}/prestasi', [App\Http\Controllers\Api\Donatur\DonaturPrestasiController::class, 'index']);
        Route::get('/children/{childId}/prestasi/{prestasiId}', [App\Http\Controllers\Api\Donatur\DonaturPrestasiController::class, 'show']);
        Route::put('/children/{childId}/prestasi/{prestasiId}/read', [App\Http\Controllers\Api\Donatur\DonaturPrestasiController::class, 'markAsRead']);

        // Raport (Report Cards)
        Route::get('/children/{childId}/raport', [App\Http\Controllers\Api\Donatur\DonaturRaportController::class, 'index']);
        Route::get('/children/{childId}/raport/{raportId}', [App\Http\Controllers\Api\Donatur\DonaturRaportController::class, 'show']);
        Route::get('/children/{childId}/raport-summary', [App\Http\Controllers\Api\Donatur\DonaturRaportController::class, 'summary']);

        // Aktivitas (Activities)
        Route::get('/children/{childId}/aktivitas', [App\Http\Controllers\Api\Donatur\DonaturAktivitasController::class, 'index']);
        Route::get('/children/{childId}/aktivitas/{aktivitasId}', [App\Http\Controllers\Api\Donatur\DonaturAktivitasController::class, 'show']);
        Route::get('/children/{childId}/attendance-summary', [App\Http\Controllers\Api\Donatur\DonaturAktivitasController::class, 'attendanceSummary']);
    });

    


});
