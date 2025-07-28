<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/auth/login', [App\Http\Controllers\API\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [App\Http\Controllers\API\AuthController::class, 'logout']);
    Route::get('/auth/user', [App\Http\Controllers\API\AuthController::class, 'user']);
    
    Route::middleware('role:admin_pusat')->prefix('admin-pusat')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\API\AdminPusatController::class, 'dashboard']);

    Route::get('/anak', [AdminPusatAnakController::class, 'index']);
    Route::get('/anak/{id}', [AdminPusatAnakController::class, 'show']);
    Route::post('/anak/{id}', [AdminPusatAnakController::class, 'update']);
    Route::post('/anak/{id}/toggle-status', [AdminPusatAnakController::class, 'toggleStatus']);
    Route::get('/anak-summary', [AdminPusatAnakController::class, 'getSummary']);

     Route::get('/anak/{childId}/raport', [AdminPusatRaportController::class, 'index']);
    Route::get('/anak/{childId}/raport/{raportId}', [AdminPusatRaportController::class, 'show']);
    
    Route::get('/anak/{anakId}/prestasi', [AdminPusatPrestasiController::class, 'index']);
    Route::get('/anak/{anakId}/prestasi/{prestasiId}', [AdminPusatPrestasiController::class, 'show']);
    
    Route::get('/anak/{anakId}/riwayat', [AdminPusatRiwayatController::class, 'index']);
    Route::get('/anak/{anakId}/riwayat/{riwayatId}', [AdminPusatRiwayatController::class, 'show']);
    
Route::get('/keluarga', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'index']);
Route::post('/keluarga', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'store']);
Route::get('/keluarga/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'show']);
Route::post('/keluarga/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'update']);
Route::delete('/keluarga/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'destroy']);
Route::get('/keluarga-dropdown', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'getDropdownData']);
Route::get('/keluarga-wilbin/{id_kacab}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'getWilbinByKacab']);
Route::get('/keluarga-shelter/{id_wilbin}', [App\Http\Controllers\API\AdminPusat\AdminPusatKeluargaController::class, 'getShelterByWilbin']);
    
 Route::get('/tutor-honor-settings', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'index']);
    Route::get('/tutor-honor-settings/active', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'getActiveSetting']);
    Route::post('/tutor-honor-settings', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'store']);
    Route::get('/tutor-honor-settings/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'show']);
    Route::put('/tutor-honor-settings/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'update']);
    Route::post('/tutor-honor-settings/{id}/set-active', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'setActive']);
    Route::delete('/tutor-honor-settings/{id}', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'destroy']);
    Route::post('/tutor-honor-settings/calculate-preview', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'calculatePreview']);
    Route::get('/tutor-honor-settings-statistics', [App\Http\Controllers\API\AdminPusat\AdminPusatTutorHonorSettingsController::class, 'getStatistics']);
});
    
// REFACTORED: Admin Cabang routes with cleaner patterns
    Route::middleware('role:admin_cabang')->prefix('admin-cabang')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\API\AdminCabangController::class, 'dashboard']);
        Route::get('/profile', [App\Http\Controllers\API\AdminCabangController::class, 'getProfile']);
        Route::post('/profile', [App\Http\Controllers\API\AdminCabangController::class, 'updateProfile']);
        
        // Survey Approval
        Route::prefix('survey-approval')->group(function () {
            Route::get('/', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'getStats']);
            Route::get('/{id}', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'show']);
            Route::post('/{id}/approve', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'approve']);
            Route::post('/{id}/reject', [App\Http\Controllers\API\AdminCabang\AdminCabangSurveyController::class, 'reject']);
        });
        
        // Donatur CRUD
        Route::apiResource('donatur', App\Http\Controllers\API\AdminCabang\AdminCabangDonaturController::class);
        Route::get('/donatur-stats', [App\Http\Controllers\API\AdminCabang\AdminCabangDonaturController::class, 'getStats']);
        Route::get('/donatur-dropdown', [App\Http\Controllers\API\AdminCabang\AdminCabangDonaturController::class, 'getDropdownData']);

        // REFACTORED: Master Data routes with resource pattern
        Route::prefix('master-data')->group(function () {
            // Jenjang routes - REFACTORED
            Route::apiResource('jenjang', App\Http\Controllers\API\AdminCabang\MasterData\JenjangController::class);
            Route::get('/jenjang-dropdown', [App\Http\Controllers\API\AdminCabang\MasterData\JenjangController::class, 'getForDropdown']);
            Route::get('/jenjang-statistics', [App\Http\Controllers\API\AdminCabang\MasterData\JenjangController::class, 'getStatistics']);
            Route::get('/jenjang-check-urutan', [App\Http\Controllers\API\AdminCabang\MasterData\JenjangController::class, 'checkUrutanAvailability']);
            Route::get('/jenjang-existing-urutan', [App\Http\Controllers\API\AdminCabang\MasterData\JenjangController::class, 'getExistingUrutan']);

            // Mata Pelajaran routes - REFACTORED
            Route::apiResource('mata-pelajaran', App\Http\Controllers\API\AdminCabang\MasterData\MataPelajaranController::class);
            Route::get('/mata-pelajaran-dropdown', [App\Http\Controllers\API\AdminCabang\MasterData\MataPelajaranController::class, 'getForDropdown']);
            Route::get('/mata-pelajaran-cascade-data', [App\Http\Controllers\API\AdminCabang\MasterData\MataPelajaranController::class, 'getCascadeData']);
            Route::get('/mata-pelajaran-jenjang/{jenjangId}', [App\Http\Controllers\API\AdminCabang\MasterData\MataPelajaranController::class, 'getByJenjang']);
            Route::get('/mata-pelajaran-statistics', [App\Http\Controllers\API\AdminCabang\MasterData\MataPelajaranController::class, 'getStatistics']);

            // Kelas routes - REFACTORED
            Route::apiResource('kelas', App\Http\Controllers\API\AdminCabang\MasterData\KelasController::class);
            Route::get('/kelas-dropdown', [App\Http\Controllers\API\AdminCabang\MasterData\KelasController::class, 'getForDropdown']);
            Route::get('/kelas-cascade-data', [App\Http\Controllers\API\AdminCabang\MasterData\KelasController::class, 'getCascadeData']);
            Route::get('/kelas-statistics', [App\Http\Controllers\API\AdminCabang\MasterData\KelasController::class, 'getStatistics']);
            Route::get('/kelas-jenjang/{jenjangId}', [App\Http\Controllers\API\AdminCabang\MasterData\KelasController::class, 'getByJenjang']);

            // Materi routes - REFACTORED
            Route::apiResource('materi', App\Http\Controllers\API\AdminCabang\MasterData\MateriController::class);
            Route::get('/materi-dropdown', [App\Http\Controllers\API\AdminCabang\MasterData\MateriController::class, 'getForDropdown']);
            Route::get('/materi-cascade-data', [App\Http\Controllers\API\AdminCabang\MasterData\MateriController::class, 'getCascadeData']);
            Route::get('/materi-statistics', [App\Http\Controllers\API\AdminCabang\MasterData\MateriController::class, 'getStatistics']);
            Route::get('/materi-mata-pelajaran', [App\Http\Controllers\API\AdminCabang\MasterData\MateriController::class, 'getByMataPelajaran']);
            Route::get('/materi-kelas/{kelasId}', [App\Http\Controllers\API\AdminCabang\MasterData\MateriController::class, 'getByKelas']);
        });

        // REFACTORED: Akademik Routes
        Route::prefix('akademik')->group(function () {
            Route::get('/statistics', [App\Http\Controllers\API\AdminCabang\AkademikKurikulumController::class, 'getGeneralStatistics']);
            
            // Kurikulum routes - REFACTORED
            Route::apiResource('kurikulum', App\Http\Controllers\API\AdminCabang\AkademikKurikulumController::class);
            Route::post('/kurikulum/{id}/assign-materi', [App\Http\Controllers\API\AdminCabang\AkademikKurikulumController::class, 'assignMateri']);
            Route::delete('/kurikulum/{id}/remove-materi/{materiId}', [App\Http\Controllers\API\AdminCabang\AkademikKurikulumController::class, 'removeMateri']);
            Route::post('/kurikulum/{id}/reorder-materi', [App\Http\Controllers\API\AdminCabang\AkademikKurikulumController::class, 'reorderMateri']);
            Route::get('/kurikulum/{id}/available-materi', [App\Http\Controllers\API\AdminCabang\AkademikKurikulumController::class, 'getAvailableMateri']);
            Route::post('/kurikulum/{id}/set-active', [App\Http\Controllers\API\AdminCabang\AkademikKurikulumController::class, 'setActive']);
        });
    });
    
 Route::middleware('role:admin_shelter')->prefix('admin-shelter')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\API\AdminShelterController::class, 'dashboard']);
    Route::get('/profile', [App\Http\Controllers\API\AdminShelterController::class, 'getProfile']);
    Route::post('/profile', [App\Http\Controllers\API\AdminShelterController::class, 'updateProfile']);
    
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

        Route::get('/jenis-kompetensi', [App\Http\Controllers\API\AdminShelter\TutorCompetencyController::class, 'getJenisKompetensi']);

        Route::get('/tutor/{tutorId}/competency', [App\Http\Controllers\API\AdminShelter\TutorCompetencyController::class, 'index']);
        Route::post('/tutor/{tutorId}/competency', [App\Http\Controllers\API\AdminShelter\TutorCompetencyController::class, 'store']);
        Route::get('/tutor/{tutorId}/competency/{id}', [App\Http\Controllers\API\AdminShelter\TutorCompetencyController::class, 'show']);
        Route::post('/tutor/{tutorId}/competency/{id}', [App\Http\Controllers\API\AdminShelter\TutorCompetencyController::class, 'update']);
        Route::delete('/tutor/{tutorId}/competency/{id}', [App\Http\Controllers\API\AdminShelter\TutorCompetencyController::class, 'destroy']);
       
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
        Route::get('/keluarga', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'index']);
        Route::post('/keluarga', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'store']);
        Route::get('/keluarga/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'show']);
        Route::post('/keluarga/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'update']);
        Route::delete('/keluarga/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'destroy']);
        Route::delete('keluarga/{id}/force', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'forceDestroy']);
        Route::get('/keluarga-dropdown', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'getDropdownData']);
        Route::get('/keluarga-wilbin/{id_kacab}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'getWilbinByKacab']);
        Route::get('/keluarga-shelter/{id_wilbin}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeluargaController::class, 'getShelterByWilbin']);
        Route::get('/pengajuan-anak/priority-families', [App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController::class, 'getPriorityFamilies']);
        Route::get('/pengajuan-anak/search-keluarga', [App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController::class, 'searchKeluarga']);
        Route::post('/pengajuan-anak/validate-kk', [App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController::class, 'validateKK']);
        Route::post('/pengajuan-anak/submit', [App\Http\Controllers\API\AdminShelter\AdminShelterPengajuanAnakController::class, 'submitAnak']);  
        Route::get('/aktivitas', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'index']);
        Route::post('/aktivitas', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'store']);
        Route::get('/aktivitas/{id}', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'show']);
        Route::post('/aktivitas/{id}', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'update']);
        Route::delete('/aktivitas/{id}', [App\Http\Controllers\API\AdminShelter\AktivitasController::class, 'destroy']);
    

Route::prefix('laporan')->group(function () {
    Route::get('/anak-binaan', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAnakController::class, 'getLaporanAnakBinaan']);
    Route::get('/anak-binaan/child/{childId}', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAnakController::class, 'getChildDetailReport']);
    Route::get('/jenis-kegiatan-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAnakController::class, 'getJenisKegiatanOptions']);
    Route::get('/available-years', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAnakController::class, 'getAvailableYears']);
    Route::get('/anak-binaan/export', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAnakController::class, 'exportLaporanAnakBinaan']);

    Route::get('/tutor', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanTutorController::class, 'getLaporanTutor']);
    Route::get('/tutor/detail/{tutorId}', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanTutorController::class, 'getTutorDetailReport']);
    Route::get('/mapel-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanTutorController::class, 'getMapelOptions']);
    Route::get('/tutor/jenis-kegiatan-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanTutorController::class, 'getJenisKegiatanOptions']);
    Route::get('/tutor/available-years', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanTutorController::class, 'getAvailableYears']);
    Route::get('/tutor/export', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanTutorController::class, 'exportTutorData']);

    Route::get('/aktivitas', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAktivitasController::class, 'getLaporanAktivitas']);
    Route::get('/aktivitas/detail/{activityId}', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAktivitasController::class, 'getActivityDetailReport']);
    Route::get('/aktivitas/jenis-kegiatan-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAktivitasController::class, 'getJenisKegiatanOptions']);
    Route::get('/aktivitas/available-years', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanAktivitasController::class, 'getAvailableYears']);

    Route::get('/histori', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanHistoriController::class, 'getLaporanHistori']);
    Route::get('/histori/detail/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanHistoriController::class, 'getHistoriDetail']);
    Route::get('/histori/jenis-histori-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanHistoriController::class, 'getJenisHistoriOptions']);
    Route::get('/histori/available-years', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanHistoriController::class, 'getAvailableYears']);

    Route::get('/cpb', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanCpbController::class, 'getCpbReport']);
    Route::get('/cpb/status/{status}', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanCpbController::class, 'getCpbByStatus']);
    Route::get('/cpb/export', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanCpbController::class, 'exportCpbData']);

    Route::get('/raport', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanRaportController::class, 'getLaporanRaport']);
    Route::get('/raport/child/{childId}', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanRaportController::class, 'getChildDetailReport']);
    Route::get('/raport/semester-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanRaportController::class, 'getSemesterOptions']);
    Route::get('/raport/mata-pelajaran-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanRaportController::class, 'getMataPelajaranOptions']);
    Route::get('/raport/available-years', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanRaportController::class, 'getAvailableYears']);

    Route::get('/surat', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanSuratController::class, 'getLaporanSurat']);
    Route::get('/surat/shelter/{shelterId}', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanSuratController::class, 'getShelterDetail']);
    Route::get('/surat/filter-options', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanSuratController::class, 'getFilterOptions']);
    Route::get('/surat/available-years', [App\Http\Controllers\API\AdminShelter\AdminShelterLaporanSuratController::class, 'getAvailableYears']);
});
    
    Route::prefix('qr-tokens')->group(function () {
        Route::post('/generate', [App\Http\Controllers\API\QrTokenController::class, 'generate']);
        Route::post('/generate-batch', [App\Http\Controllers\API\QrTokenController::class, 'generateBatch']);
        Route::post('/validate-token', [App\Http\Controllers\API\QrTokenController::class, 'validateToken']);
        Route::get('/student/{id_anak}', [App\Http\Controllers\API\QrTokenController::class, 'getActiveToken']);
        Route::post('/invalidate', [App\Http\Controllers\API\QrTokenController::class, 'invalidate']);
    });
    
    Route::prefix('attendance')->group(function () {
        Route::post('/record-by-qr', [App\Http\Controllers\API\AttendanceController::class, 'recordAttendanceByQr']);
        Route::post('/record-manual', [App\Http\Controllers\API\AttendanceController::class, 'recordAttendanceManually']);
        Route::get('/activity/{id_aktivitas}', [App\Http\Controllers\API\AttendanceController::class, 'getByActivity']);
        Route::get('/student/{id_anak}', [App\Http\Controllers\API\AttendanceController::class, 'getByStudent']);
        Route::post('/{id_absen}/verify', [App\Http\Controllers\API\AttendanceController::class, 'manualVerify']);
        Route::post('/{id_absen}/reject', [App\Http\Controllers\API\AttendanceController::class, 'rejectVerification']);
        Route::get('/{id_absen}/verification-history', [App\Http\Controllers\API\AttendanceController::class, 'getVerificationHistory']);
    });

Route::prefix('tutor-attendance')->group(function () {
    Route::post('/generate-token', [App\Http\Controllers\API\TutorAttendanceController::class, 'generateTutorToken']);
    Route::post('/record-by-qr', [App\Http\Controllers\API\TutorAttendanceController::class, 'recordTutorAttendanceByQr']);
    Route::post('/record-manual', [App\Http\Controllers\API\TutorAttendanceController::class, 'recordTutorAttendanceManually']);
    Route::get('/activity/{id_aktivitas}', [App\Http\Controllers\API\TutorAttendanceController::class, 'getTutorAttendanceByActivity']);
    Route::get('/tutor/{id_tutor}', [App\Http\Controllers\API\TutorAttendanceController::class, 'getTutorAttendanceHistory']);
    Route::post('/validate-tutor-token', [App\Http\Controllers\API\TutorAttendanceController::class, 'validateTutorToken']);
});

Route::prefix('tutor-honor')->group(function () {
    Route::get('/tutor/{id_tutor}', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'getTutorHonor']);
    Route::get('/tutor/{id_tutor}/history', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'getHonorHistory']);
    Route::get('/tutor/{id_tutor}/statistics', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'getHonorStatistics']);
    Route::get('/tutor/{id_tutor}/month/{month}/year/{year}', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'getMonthlyDetail']);
    Route::get('/tutor/{id_tutor}/year-range', [TutorHonorController::class, 'getYearRange']);
    Route::post('/calculate/{id_tutor}', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'calculateHonor']);
    Route::post('/approve/{id_honor}', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'approveHonor']);
    Route::post('/mark-paid/{id_honor}', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'markAsPaid']);
    Route::get('/stats', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'getHonorStats']);
     Route::get('/current-settings', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'getCurrentSettings']);
        Route::post('/calculate-preview', [App\Http\Controllers\API\AdminShelter\TutorHonorController::class, 'calculatePreview']);
});
    
    Route::prefix('attendance-reports')->group(function () {
        Route::post('/statistics', [App\Http\Controllers\API\AttendanceReportController::class, 'generateStats']);
        Route::post('/tutor-payment', [App\Http\Controllers\API\AttendanceReportController::class, 'generateTutorPaymentReport']);
        Route::post('/export', [App\Http\Controllers\API\AttendanceReportController::class, 'exportAttendanceData']);
    });

Route::get('/materi/by-level', [App\Http\Controllers\API\AdminShelter\MateriController::class, 'getByLevel']);

// Kurikulum routes for Admin Shelter
Route::get('/kurikulum', [App\Http\Controllers\API\AdminShelter\AdminShelterKurikulumController::class, 'index']);
Route::get('/kurikulum/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKurikulumController::class, 'show']);
Route::get('/kurikulum/{id}/preview', [App\Http\Controllers\API\AdminShelter\AdminShelterKurikulumController::class, 'getPreview']);
Route::get('/kurikulum-dropdown', [App\Http\Controllers\API\AdminShelter\AdminShelterKurikulumController::class, 'getForDropdown']);

Route::get('/semester/active', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'getActive']);
Route::get('/semester/tahun-ajaran', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'getTahunAjaran']);
Route::get('/semester/{id}/statistics', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'statistics']);
Route::post('/semester/{id}/set-active', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'setActive']);
Route::get('/semester', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'index']);
Route::post('/semester', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'store']);
Route::get('/semester/{id}', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'show']);
Route::put('/semester/{id}', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'update']);
Route::delete('/semester/{id}', [App\Http\Controllers\API\AdminShelter\SemesterController::class, 'destroy']);

Route::get('/jenis-penilaian', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'getJenisPenilaian']);

Route::get('/penilaian', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'index']);
Route::post('/penilaian', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'store']);
Route::get('/penilaian/{id}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'show']);
Route::put('/penilaian/{id}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'update']);
Route::delete('/penilaian/{id}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'destroy']);
Route::get('/penilaian/anak/{idAnak}/semester/{idSemester}', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'getByAnakSemester']);
Route::post('/penilaian/bulk', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'bulkStore']);
Route::post('/penilaian/calculate-nilai-akhir', [App\Http\Controllers\API\AdminShelter\PenilaianController::class, 'calculateNilaiAkhir']);

Route::get('/nilai-sikap/{idAnak}/{idSemester}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'getNilaiSikap']);
Route::post('/nilai-sikap', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'storeNilaiSikap']);
Route::put('/nilai-sikap/{id}', [App\Http\Controllers\API\AdminShelter\RaportController::class, 'updateNilaiSikap']);

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

    Route::get('anak/{anakId}/raport-formal', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'index']);
    Route::post('anak/{anakId}/raport-formal', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'store']);
    Route::get('anak/{anakId}/raport-formal/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'show']);
    Route::post('anak/{anakId}/raport-formal/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'update']);
    Route::delete('anak/{anakId}/raport-formal/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterRaportFormalController::class, 'destroy']);

    // Keuangan routes for Admin Shelter
    Route::get('/keuangan', [App\Http\Controllers\API\AdminShelter\AdminShelterKeuanganController::class, 'index']);
    Route::post('/keuangan', [App\Http\Controllers\API\AdminShelter\AdminShelterKeuanganController::class, 'store']);
    Route::get('/keuangan/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeuanganController::class, 'show']);
    Route::put('/keuangan/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeuanganController::class, 'update']);
    Route::delete('/keuangan/{id}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeuanganController::class, 'destroy']);
    Route::get('/keuangan/child/{childId}', [App\Http\Controllers\API\AdminShelter\AdminShelterKeuanganController::class, 'getByChild']);
    Route::get('/keuangan-statistics', [App\Http\Controllers\API\AdminShelter\AdminShelterKeuanganController::class, 'getStatistics']);
});
    
    Route::middleware('role:donatur')
    ->prefix('donatur')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\API\DonaturController::class, 'dashboard']);
        Route::get('/profile', [App\Http\Controllers\API\DonaturController::class, 'getProfile']);
        Route::post('/profile', [App\Http\Controllers\API\DonaturController::class, 'updateProfile']);
        Route::get('/children', [App\Http\Controllers\Api\Donatur\DonaturAnakController::class, 'index']);
        Route::get('/children/{childId}', [App\Http\Controllers\Api\Donatur\DonaturAnakController::class, 'show']);

        Route::get('/children/{childId}/surat', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'index']);
        Route::post('/children/{childId}/surat', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'store']);
        Route::get('/children/{childId}/surat/{suratId}', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'show']);
        Route::put('/children/{childId}/surat/{suratId}/read', [App\Http\Controllers\Api\Donatur\DonaturSuratController::class, 'markAsRead']);

        Route::get('/children/{childId}/prestasi', [App\Http\Controllers\Api\Donatur\DonaturPrestasiController::class, 'index']);
        Route::get('/children/{childId}/prestasi/{prestasiId}', [App\Http\Controllers\Api\Donatur\DonaturPrestasiController::class, 'show']);
        Route::put('/children/{childId}/prestasi/{prestasiId}/read', [App\Http\Controllers\Api\Donatur\DonaturPrestasiController::class, 'markAsRead']);

        Route::get('/children/{childId}/raport', [App\Http\Controllers\Api\Donatur\DonaturRaportController::class, 'index']);
        Route::get('/children/{childId}/raport/{raportId}', [App\Http\Controllers\Api\Donatur\DonaturRaportController::class, 'show']);
        Route::get('/children/{childId}/raport-summary', [App\Http\Controllers\Api\Donatur\DonaturRaportController::class, 'summary']);

        Route::get('/children/{childId}/aktivitas', [App\Http\Controllers\Api\Donatur\DonaturAktivitasController::class, 'index']);
        Route::get('/children/{childId}/aktivitas/{aktivitasId}', [App\Http\Controllers\Api\Donatur\DonaturAktivitasController::class, 'show']);
        Route::get('/children/{childId}/attendance-summary', [App\Http\Controllers\Api\Donatur\DonaturAktivitasController::class, 'attendanceSummary']);
  
        Route::get('/berita', [App\Http\Controllers\API\Donatur\DonaturBeritaController::class, 'index']);
        Route::get('/berita/{id}', [App\Http\Controllers\API\Donatur\DonaturBeritaController::class, 'show']);
        Route::put('/berita/{id}/increment-view', [App\Http\Controllers\API\Donatur\DonaturBeritaController::class, 'incrementView']);

        Route::prefix('marketplace')->group(function () {
            Route::get('/available-children', [App\Http\Controllers\Api\Donatur\DonaturMarketplaceController::class, 'availableChildren']);
            Route::get('/children/{childId}/profile', [App\Http\Controllers\Api\Donatur\DonaturMarketplaceController::class, 'childProfile']);
            Route::post('/children/{childId}/sponsor', [App\Http\Controllers\Api\Donatur\DonaturSponsorshipController::class, 'sponsorChild']);
            Route::get('/filters', [App\Http\Controllers\Api\Donatur\DonaturMarketplaceController::class, 'getFilters']);
            Route::get('/featured-children', [App\Http\Controllers\Api\Donatur\DonaturMarketplaceController::class, 'featuredChildren']);
        });

        // Billing/Keuangan routes for Donatur
        Route::get('/billing', [App\Http\Controllers\API\Donatur\DonaturKeuanganController::class, 'index']);
        Route::get('/billing/{id}', [App\Http\Controllers\API\Donatur\DonaturKeuanganController::class, 'show']);
        Route::get('/billing/child/{childId}', [App\Http\Controllers\API\Donatur\DonaturKeuanganController::class, 'getByChild']);
        Route::get('/billing-summary', [App\Http\Controllers\API\Donatur\DonaturKeuanganController::class, 'getSummary']);
        Route::get('/billing-semesters', [App\Http\Controllers\API\Donatur\DonaturKeuanganController::class, 'getSemesters']);
    });
});