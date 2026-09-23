<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DoctorScheduleController;

// Endpoint Publik
Route::middleware('auth:sanctum')->group(function () {
    
    // Hanya Dokter yang boleh menyimpan rekam medis SOAP & potong stok FEFO
    Route::post('/medical-records', [MedicalRecordController::class, 'store'])
         ->middleware('role:doctor');

    // Hanya Apoteker / Staf yang boleh menambah data obat baru
    Route::post('/medicines', [MedicineController::class, 'store'])
         ->middleware('role:admin,pharmacist');

    // Riwayat Rekam Medis Pasien
    Route::get('/medical-records/patient/{patientId}', [MedicalRecordController::class, 'patientHistory'])
         ->middleware('role:patient,doctor,admin');

     Route::post('/doctor-schedules', [DoctorScheduleController::class, 'store'])
     ->middleware('role:admin,doctor');

    // Umum (Bisa diakses semua role yang sudah login)
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/medicines', [MedicineController::class, 'index']);
    Route::post('/billings', [BillingController::class, 'store']);
    Route::get('/doctor-schedules/{doctorId}', [DoctorScheduleController::class, 'show']);
});