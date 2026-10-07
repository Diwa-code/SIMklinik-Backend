<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\DoctorController;

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
    // MODUL REKAM MEDIS (Medical Records)
    // ------------------------------------------
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
         ->middleware('role:patient,doctor,admin');

    Route::post('/appointments/recommend', [AppointmentController::class, 'recommend'])
         ->middleware('role:patient');

    // Pendaftaran antrean murni dilakukan oleh pasien
    Route::post('/appointments', [AppointmentController::class, 'store'])
         ->middleware('role:patient');
});