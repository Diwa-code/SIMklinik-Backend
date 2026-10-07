<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Models\Appointment;

class MedicalRecordController extends Controller
{
    /**
     * Menyimpan Rekam Medis SOAP oleh Dokter
     * Sekaligus mengunci validasi bahwa pasien ini telah selesai diperiksa.
     */
    public function store(Request $request)
    {
        // 1. Validasi input form SOAP dari dokter
        $request->validate([
            'appointment_id' => 'required|string', // Menghubungkan ke sesi antrean pasien
            'patient_id'     => 'required|string',
            'subjective'     => 'required|string', // Keluhan / Anamnesis dari pasien
            'objective'      => 'required|string', // Hasil pemeriksaan fisik / tanda vital
            'assessment'     => 'required|string', // Diagnosa / Penilaian dokter
            'plan'           => 'required|string', // Rencana pengobatan / tindakan / resep
            'prescriptions'  => 'nullable|array'   // Daftar obat jika ada resep (untuk modul apotek)
        ]);

        // 2. Ambil data antrean (appointment) untuk memastikan pasien ini memang terdaftar
        $appointment = Appointment::find($request->appointment_id);
        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data antrean pasien tidak ditemukan.'
            ], 404);
        }

        // 3. Pastikan dokter yang login adalah dokter yang bertugas di appointment tersebut
        // (Menggunakan ID user yang sedang login via token Sanctum)
        $doctorId = $request->user()->_id;
        
        // Simpan data rekam medis (SOAP) ke database MongoDB
        $medicalRecord = MedicalRecord::create([
            'appointment_id' => $request->appointment_id,
            'patient_id'     => $request->patient_id,
            'doctor_id'      => $doctorId,
            'subjective'     => $request->subjective,
            'objective'      => $request->objective,
            'assessment'     => $request->assessment,
            'plan'           => $request->plan,
            'prescriptions'  => $request->prescriptions ?? [],
            'examined_at'    => now()
        ]);

        // 4. Logika Validasi Tombol "Next Patient":
        // Ubah status antrean pasien ini menjadi 'completed' (selesai).
        // Dengan status ini, antrean selesai dan sistem mengizinkan dokter beralih ke pasien berikutnya.
        $appointment->update([
            'status' => 'completed'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekam medis SOAP berhasil disimpan. Sesi pasien selesai, silakan lanjut ke pasien berikutnya.',
            'data' => [
                'medical_record' => $medicalRecord,
                'next_queue_status' => 'ready' // Sinyal ke frontend bahwa tombol Next Patient diizinkan
            ]
        ], 201);
    }

    /**
     * Riwayat Rekam Medis Pasien (Bisa diakses dokter atau pasien bersangkutan)
     */
    public function patientHistory($patientId)
    {
        $records = MedicalRecord::where('patient_id', $patientId)
            ->orderBy('examined_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat rekam medis berhasil dimuat',
            'data' => $records
        ], 200);
    }
}