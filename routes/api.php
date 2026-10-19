<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

// ==========================================
// 1. ENDPOINT PUBLIK (Tanpa Token Sanctum)
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/doctors/register', [DoctorController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

// ==========================================
// 2. ENDPOINT TERPROTEKSI (Wajib Token Sanctum)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // ------------------------------------------
    // MODUL PROFIL PASIEN & KELENGKAPAN DATA
    // ------------------------------------------
    Route::get('/patient/profile', [PatientController::class, 'profile'])
        ->middleware('role:patient');

    Route::put('/patient/profile', [PatientController::class, 'updateProfile'])
        ->middleware('role:patient');

    // ------------------------------------------
    // MODUL REKAM MEDIS (Medical Records) & AUDIT TRAIL
    // ------------------------------------------
    // Audit Trail Log Kepatuhan UU PDP (Khusus Admin / Compliance Officer)
    Route::get('/medical-records/audit-logs', [MedicalRecordController::class, 'auditLogs'])
        ->middleware('role:admin');

    Route::post('/medical-records', [MedicalRecordController::class, 'store'])
        ->middleware('role:doctor');

    Route::get('/medical-records/patient/{patientId}', [MedicalRecordController::class, 'patientHistory'])
        ->middleware('role:patient,doctor,admin');

    // ------------------------------------------
    // MODUL INVENTARIS OBAT (Apotek / Farmasi)
    // ------------------------------------------
    Route::resource('medicines', MedicineController::class)
        ->only(['index', 'show'])
        ->middleware('role:admin,pharmacist,doctor');

    Route::resource('medicines', MedicineController::class)
        ->only(['store', 'update'])
        ->middleware('role:admin,pharmacist');

    Route::resource('medicines', MedicineController::class)
        ->only(['destroy'])
        ->middleware('role:admin');

    // ------------------------------------------
    // MODUL KASIR & PEMBAYARAN (Billings)
    // - Lihat data: Admin, Apoteker, dan Pasien (milik sendiri)
    // - Buat/Ubah tagihan: HANYA Admin dan Apoteker (Dokter dibebaskan)
    // ------------------------------------------
    Route::resource('billings', BillingController::class)
        ->only(['index', 'show'])
        ->middleware('role:admin,pharmacist,patient');

    Route::resource('billings', BillingController::class)
        ->only(['store', 'update'])
        ->middleware('role:admin,pharmacist');

    // ------------------------------------------
    // MODUL JADWAL PRAKTIK DOKTER (Doctor Schedules)
    // ------------------------------------------
    Route::get('/doctor-schedules/{doctorId}', [DoctorScheduleController::class, 'show']);

    Route::resource('doctor-schedules', DoctorScheduleController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:admin,doctor');

    // ------------------------------------------
    // MODUL ANTREAN / JANJI TEMU (Appointments)
    // ------------------------------------------
    // Melihat daftar antrean (pasien lihat miliknya, dokter/admin lihat semua)
    Route::get('/appointments', [AppointmentController::class, 'index'])
        ->middleware('role:patient,doctor,admin');

    // Fitur pencarian dokter berdasarkan tanggal dan keluhan (digunakan pasien)
    Route::get('/appointments/available-doctors', [AppointmentController::class, 'getDoctorsByDate'])
        ->middleware('role:patient');

    Route::post('/appointments/recommend', [AppointmentController::class, 'recommend'])
        ->middleware('role:patient');

    // Pendaftaran antrean murni dilakukan oleh pasien
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware('role:patient');

    Route::patch('/appointments/{id}/draft', [AppointmentController::class, 'updateDraft'])
        ->middleware('role:patient');

    Route::post('/appointments/{id}/confirm', [AppointmentController::class, 'confirm'])
        ->middleware('role:patient');

    // Update status alur klinis antrean (waiting, called, in_consultation, completed, cancelled)
    Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])
        ->middleware('role:doctor,admin');
});
