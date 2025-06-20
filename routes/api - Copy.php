<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// Public authentication endpoints
Route::post('/auth/login', [App\Http\Controllers\API\AuthController::class, 'login']);
// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Shared authentication endpoints
    Route::post('/auth/logout', [App\Http\Controllers\API\AuthController::class, 'logout']);
    Route::get('/auth/user', [App\Http\Controllers\API\AuthController::class, 'user']);
    
    // Admin Pusat routes
    Route::middleware('role:admin_pusat')->prefix('admin-pusat')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\API\AdminPusatController::class, 'dashboard']);
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
Route::get('/keluarga', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'index']);
Route::post('/keluarga', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'store']);
Route::get('/keluarga/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'show']);
Route::post('/keluarga/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'update']);
Route::delete('/keluarga/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'destroy']);
Route::get('/keluarga-dropdown', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'getDropdownData']);
Route::get('/keluarga-wilbin/{id_kacab}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'getWilbinByKacab']);
Route::get('/keluarga-shelter/{id_wilbin}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'getShelterByWilbin']);
    
});
    
    // Admin Cabang routes
Route::middleware('role:admin_cabang')->prefix('admin-cabang')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\API\AdminCabangController::class, 'dashboard']);
    
    Route::get('/survey-approval', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'index']);
    Route::get('/survey-approval/stats', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'getStats']);
    Route::get('/survey-approval/{id}', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'show']);
    Route::post('/survey-approval/{id}/approve', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'approve']);
    Route::post('/survey-approval/{id}/reject', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'reject']);
});
    
    // Admin Shelter routes
    Route::middleware('role:admin_shelter')->prefix('admin-shelter')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\API\AdminShelterController::class, 'dashboard']);
        
    
        Route::get('/anak', [App\Http\Controllers\API\AdminShelter\AdminShelterAnakController::class, 'index']);
        Route::post('/anak', [App\Http\Controllers\API\AdminShelter\AdminShelterAnakController::class, 'store']);
        Route::get('/anak/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterAnakController::class, 'show']);
        Route::post('/anak/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterAnakController::class, 'update']);
        Route::delete('/anak/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterAnakController::class, 'destroy']);
        Route::post('/anak/{id}/toggle-status', [App\Http\Controllers\API\AdminShelter\AdminShelterAnakController::class, 'toggleStatus']);
    
        Route::get('/anak/{childId}/raport', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportController::class, 'index']);
        Route::post('/anak/{childId}/raport/create', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportController::class, 'store']);
        Route::get('/anak/{childId}/raport/{raportId}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportController::class, 'show']);
        Route::post('/anak/{childId}/raport/{raportId}/update', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportController::class, 'update']);
        Route::delete('/anak/{childId}/raport/{raportId}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportController::class, 'destroy']);

        Route::get('/anak/{anakId}/prestasi', [App\Http\Controllers\API\AdminShelter\AdminShelterPrestasiController::class, 'index']);
        Route::post('/anak/{anakId}/prestasi', [App\Http\Controllers\API\AdminShelter\AdminShelterPrestasiController::class, 'store']);
        Route::get('/anak/{anakId}/prestasi/{prestasiId}', [App\Http\Controllers\API\AdminShelter\AdminShelterPrestasiController::class, 'show']);
        Route::post('/anak/{anakId}/prestasi/{prestasiId}', [App\Http\Controllers\API\AdminShelter\AdminShelterPrestasiController::class, 'update']);
        Route::delete('/anak/{anakId}/prestasi/{prestasiId}', [App\Http\Controllers\API\AdminShelter\AdminShelterPrestasiController::class, 'destroy']);
   
        Route::get('/anak/{anakId}/riwayat', [App\Http\Controllers\API\AdminShelter\AdminShelterRiwayatController::class, 'index']);
        Route::post('/anak/{anakId}/riwayat', [App\Http\Controllers\API\AdminShelter\AdminShelterRiwayatController::class, 'store']);
        Route::get('/anak/{anakId}/riwayat/{riwayatId}', [App\Http\Controllers\API\AdminShelter\AdminShelterRiwayatController::class, 'show']);
        Route::post('/anak/{anakId}/riwayat/{riwayatId}', [App\Http\Controllers\API\AdminShelter\AdminShelterRiwayatController::class, 'update']);
        Route::delete('/anak/{anakId}/riwayat/{riwayatId}', [App\Http\Controllers\API\AdminShelter\AdminShelterRiwayatController::class, 'destroy']);

        Route::get('/anak/{childId}/surat', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'index']);
        Route::post('/anak/{childId}/surat', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'store']);
        Route::get('/anak/{childId}/surat/{suratId}', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'show']);
        Route::post('/anak/{childId}/surat/{suratId}', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'update']);
        Route::delete('/anak/{childId}/surat/{suratId}', [App\Http\Controllers\API\AdminShelter\AdminShelterSuratController::class, 'destroy']);
        
        
        Route::get('/tutor', [App\Http\Controllers\API\AdminShelter\AdminShelterTutorController::class, 'index']);
        Route::post('/tutor', [App\Http\Controllers\API\AdminShelter\AdminShelterTutorController::class, 'store']);
        Route::get('/tutor/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterTutorController::class, 'show']);
        Route::post('/tutor/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterTutorController::class, 'update']);
        Route::delete('/tutor/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterTutorController::class, 'destroy']);

        // Kelompok (Group) Management Routes
        Route::get('/kelompok', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'index']);
        Route::post('/kelompok', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'store']);
        Route::get('/kelompok/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'show']);
        Route::post('/kelompok/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'update']);
        Route::delete('/kelompok/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'destroy']);
        Route::get('/kelompok-levels', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'getLevels']);
        Route::get('/kelompok/available-children/{id_shelter}', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'getAvailableChildren']);
        Route::get('/kelompok/{id_kelompok}/children', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'getGroupChildren']);
        Route::post('/kelompok/{id_kelompok}/add-child', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'addChildToGroup']);
        Route::delete('/kelompok/{id_kelompok}/remove-child/{id_anak}', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'removeChildFromGroup']);
        Route::post('/move-child/{id_anak}', [App\Http\Controllers\API\AdminShelter\AdminShelterKelompokController::class, 'moveChildToShelter']);

        // Keluarga (Family) Management Routes
        Route::get('/keluarga', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'index']);
        Route::post('/keluarga', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'store']);
        Route::get('/keluarga/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'show']);
        Route::post('/keluarga/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'update']);
        Route::delete('/keluarga/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'destroy']);
        Route::get('/keluarga-dropdown', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'getDropdownData']);
        Route::get('/keluarga-wilbin/{id_kacab}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'getWilbinByKacab']);
        Route::get('/keluarga-shelter/{id_wilbin}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'getShelterByWilbin']);
        // Pengajuan Anak (Child Application) Routes
        Route::get('/pengajuan-anak/search-keluarga', [App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController::class, 'searchKeluarga']);
        Route::post('/pengajuan-anak/validate-kk', [App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController::class, 'validateKK']);
        Route::post('/pengajuan-anak/submit', [App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController::class, 'submitAnak']);
        // Add Survey routes here
       
    
        Route::get('/aktivitas', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'index']);
Route::post('/aktivitas', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'store']);
Route::get('/aktivitas/{id}', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'show']);
Route::post('/aktivitas/{id}', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'update']);
Route::delete('/aktivitas/{id}', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'destroy']);
    
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
    Route::post('/generate-token', [App\Http\Controllers\API\TutorAttendanceController::class, 'generateTutorToken']);
    Route::post('/record-by-qr', [App\Http\Controllers\API\TutorAttendanceController::class, 'recordTutorAttendanceByQr']);
    Route::post('/record-manual', [App\Http\Controllers\API\TutorAttendanceController::class, 'recordTutorAttendanceManually']);
    Route::get('/activity/{id_aktivitas}', [App\Http\Controllers\API\TutorAttendanceController::class, 'getTutorAttendanceByActivity']);
    Route::get('/tutor/{id_tutor}', [App\Http\Controllers\API\TutorAttendanceController::class, 'getTutorAttendanceHistory']);
    Route::post('/validate-tutor-token', [App\Http\Controllers\API\TutorAttendanceController::class, 'validateTutorToken']);
});
    
    // Attendance Report routes
    Route::prefix('attendance-reports')->group(function () {
        Route::post('/statistics', [App\Http\Controllers\API\AttendanceReportController::class, 'generateStats']);
        Route::post('/tutor-payment', [App\Http\Controllers\API\AttendanceReportController::class, 'generateTutorPaymentReport']);
        Route::post('/export', [App\Http\Controllers\API\AttendanceReportController::class, 'exportAttendanceData']);
    });

Route::get('/materi/by-level', [App\Http\Controllers\API\AdminShelter\MateriController::class, 'getByLevel']);

// Route spesifik dulu
Route::get('/semester/active', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'getActive']);
Route::get('/semester/tahun-ajaran', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'getTahunAjaran']);
Route::get('/semester/{id}/statistics', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'statistics']);
Route::post('/semester/{id}/set-active', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'setActive']);
Route::get('/semester', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'index']);
Route::post('/semester', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'store']);
Route::get('/semester/{id}', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'show']);
Route::put('/semester/{id}', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'update']);
Route::delete('/semester/{id}', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'destroy']);

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


  // Raport Formal routes
    Route::get('anak/{anakId}/raport-formal', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'index']);
    Route::post('anak/{anakId}/raport-formal', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'store']);
    Route::get('anak/{anakId}/raport-formal/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'show']);
    Route::post('anak/{anakId}/raport-formal/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'update']);
    Route::delete('anak/{anakId}/raport-formal/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'destroy']);
});
    
    // Donatur routes
    Route::middleware('role:donatur')
    ->prefix('donatur')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [App\Http\Controllers\API\DonaturController::class, 'dashboard']);

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
  
         // Berita (News)
        Route::get('/berita', [App\Http\Controllers\API\Donatur\DonaturBeritaController::class, 'index']);
        Route::get('/berita/{id}', [App\Http\Controllers\API\Donatur\DonaturBeritaController::class, 'show']);
        Route::put('/berita/{id}/increment-view', [App\Http\Controllers\API\Donatur\DonaturBeritaController::class, 'incrementView']);
    });
});
