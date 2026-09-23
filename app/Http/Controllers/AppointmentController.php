<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    // Mengambil daftar semua antrean
    public function index()
    {
        // Menggunakan relasi untuk mengambil data pasien dan dokter sekaligus
        $appointments = Appointment::with(['patient', 'doctor'])->get();
        
        return response()->json([
            'message' => 'Daftar Antrean Berhasil Dimuat',
            'data' => $appointments
        ]);
    }

    // Mendaftarkan antrean baru
    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|string',
            'doctor_id' => 'required|string',
            'queue_number' => 'required|string',
            'date' => 'required|date',
            'guarantor' => 'required|string'
        ]);

        // 1. Deteksi nama hari dari tanggal yang diinput pasien (dalam bahasa Indonesia)
        $parsedDate = Carbon::parse($request->date)->locale('id');
        $dayOfWeek = $parsedDate->dayName; // Menghasilkan: "Senin", "Selasa", dst.

        // 2. Cek ketersediaan jadwal dokter pada hari tersebut
        $schedule = DoctorSchedule::where('doctor_id', $request->doctor_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (!$schedule) {
            return response()->json([
                'message' => "Pendaftaran gagal. Dokter tidak memiliki jadwal praktik pada hari $dayOfWeek."
            ], 400);
        }

        // 3. Cek apakah kuota antrean harian sudah penuh
        $currentAppointmentsCount = Appointment::where('doctor_id', $request->doctor_id)
            ->where('date', $request->date)
            ->count();

        if ($currentAppointmentsCount >= $schedule->quota) {
            return response()->json([
                'message' => "Pendaftaran gagal. Kuota maksimal ({$schedule->quota} pasien) untuk tanggal ini sudah penuh."
            ], 400);
        }

        // 4. Jika lolos semua validasi, simpan antrean
        $appointment = Appointment::create([
            'patient_id' => $request->patient_id,
            'doctor_id' => $request->doctor_id,
            'queue_number' => $request->queue_number,
            'date' => $request->date,
            'status' => 'waiting',
            'guarantor' => $request->guarantor
        ]);

        return response()->json([
            'message' => 'Antrean Berhasil Didaftarkan',
            'data' => $appointment
        ], 201);
    }
}