<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Menampilkan daftar antrean pasien.
     * - Pasien: hanya melihat antrean milik sendiri
     * - Dokter / Admin: melihat seluruh antrean
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'patient') {
            // Pasien hanya boleh melihat antrean miliknya sendiri
            $appointments = Appointment::with(['patient', 'doctor'])
                ->where('patient_id', $user->_id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // Dokter dan Admin melihat semua antrean
            $appointments = Appointment::with(['patient', 'doctor'])
                ->orderBy('date', 'asc')
                ->orderBy('queue_number', 'asc')
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Antrean Berhasil Dimuat',
            'data' => $appointments
        ], 200);
    }

    /**
     * 1. Rekomendasi Berbasis Keluhan & Field 'specialization' di Koleksi Doctors
     */
    public function recommend(Request $request)
    {
        $request->validate([
            'complaint' => 'required|string'
        ]);

        $complaint = strtolower($request->input('complaint'));

        // Petakan keluhan secara dinamis ke spesialisasi yang ada di data dokter
        $matchedSpecialization = 'Poliklinik Umum'; // Fallback default

        if (str_contains($complaint, 'demam') || str_contains($complaint, 'batuk') || str_contains($complaint, 'sesak') || str_contains($complaint, 'lemas') || str_contains($complaint, 'mual') || str_contains($complaint, 'pusing')) {
            $matchedSpecialization = 'Poliklinik Penyakit Dalam';
        } elseif (str_contains($complaint, 'gigi') || str_contains($complaint, 'gusi') || str_contains($complaint, 'nyeri gigi')) {
            $matchedSpecialization = 'Poliklinik Gigi';
        } elseif (str_contains($complaint, 'anak') || str_contains($complaint, 'bayi') || str_contains($complaint, 'imunisasi')) {
            $matchedSpecialization = 'Poliklinik Anak';
        }

        // Cari dokter aktif yang kolom 'specialization'-nya cocok dengan hasil pemetaan
        $recommendedDoctor = Doctor::with('user')
            ->where('specialization', 'LIKE', '%' . $matchedSpecialization . '%')
            ->where('is_active', true)
            ->first();

        // Jika tidak ada yang spesifik, ambil dokter aktif pertama yang tersedia di sistem
        if (!$recommendedDoctor) {
            $recommendedDoctor = Doctor::with('user')
                ->where('is_active', true)
                ->first();

            if ($recommendedDoctor) {
                $matchedSpecialization = $recommendedDoctor->specialization;
            }
        }

        // Ambil seluruh daftar dokter aktif beserta relasi user-nya untuk opsi pilihan manual pasien
        $allDoctors = Doctor::with('user')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'recommendation' => [
                'specialization' => $matchedSpecialization,
                'doctor_id' => $recommendedDoctor ? $recommendedDoctor->_id : null,
                'doctor_name' => ($recommendedDoctor && $recommendedDoctor->user) ? $recommendedDoctor->user->name : 'Dokter Umum Tersedia'
            ],
            'available_doctors' => $allDoctors
        ], 200);
    }

    /**
     * 2. Pendaftaran Antrean Pasien
     */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|string', // Merujuk ke _id dari collection doctors
            'date' => 'required|date',
            'complaint' => 'required|string',
            'guarantor' => 'required|string' // Contoh: Umum, BPJS, Asuransi
        ]);

        // Cek nomor antrean terakhir berdasarkan ID dokter dan tanggal yang sama
        $lastAppointment = Appointment::where('doctor_id', $request->doctor_id)
            ->where('date', $request->date)
            ->orderBy('created_at', 'desc')
            ->first();

        // Generate nomor antrean otomatis (A-001, A-002, dst)
        $queueNumber = 'A-001';
        if ($lastAppointment && isset($lastAppointment->queue_number)) {
            $lastNumber = (int) substr($lastAppointment->queue_number, 2);
            $queueNumber = 'A-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        }

        // Simpan pendaftaran antrean baru ke MongoDB
        $appointment = Appointment::create([
            'patient_id' => $request->user()->_id, // ID pasien dari token Sanctum
            'doctor_id' => $request->doctor_id,
            'queue_number' => $queueNumber,
            'date' => $request->date,
            'complaint' => $request->complaint,
            'guarantor' => $request->guarantor,
            'status' => 'waiting',        // Status awal antrean
            'payment_status' => 'unpaid'  // Status pembayaran awal
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran antrean berhasil dibuat',
            'data' => $appointment
        ], 201);
    }

    public function getDoctorsByDate(Request $request)
{
    $request->validate([
        'date'      => 'required|date',
        'complaint' => 'nullable|string'
    ]);

    $date = $request->input('date');
    
    // 1. Konversi tanggal ke nama hari Bahasa Indonesia
    $englishDay = Carbon::parse($date)->englishDayOfWeek;
    $daysMap = [
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu'
    ];
    $dayOfWeek = $daysMap[$englishDay] ?? '';

    // 2. Ambil jadwal dokter yang aktif pada hari tersebut
    $query = DoctorSchedule::with(['doctor.user'])
        ->where('day_of_week', $dayOfWeek)
        ->where('is_active', true);

    // Filter opsional berdasarkan keluhan (jika dikirim oleh front-end)
    if ($request->filled('complaint')) {
        $complaint = strtolower($request->input('complaint'));
        $matchedSpecialization = 'Poliklinik Umum';

        if (str_contains($complaint, 'demam') || str_contains($complaint, 'batuk') || str_contains($complaint, 'sesak') || str_contains($complaint, 'lemas') || str_contains($complaint, 'mual') || str_contains($complaint, 'pusing')) {
            $matchedSpecialization = 'Poliklinik Penyakit Dalam';
        } elseif (str_contains($complaint, 'gigi') || str_contains($complaint, 'gusi')) {
            $matchedSpecialization = 'Poliklinik Gigi';
        } elseif (str_contains($complaint, 'anak') || str_contains($complaint, 'bayi')) {
            $matchedSpecialization = 'Poliklinik Anak';
        }

        $query->whereHas('doctor', function ($q) use ($matchedSpecialization) {
            $q->where('specialization', 'LIKE', '%' . $matchedSpecialization . '%');
        });
    }

    $schedules = $query->get();

    // 3. Mapping data untuk menghitung kuota secara real-time pada tanggal tersebut
    $schedulesWithQuotaInfo = $schedules->map(function ($schedule) use ($date) {
        // Hitung berapa pasien yang sudah booking ke dokter ini pada tanggal tersebut
        $bookedCount = Appointment::where('doctor_id', $schedule->doctor_id)
            ->where('date', $date)
            ->where('status', '!=', 'cancelled') // Jangan hitung yang batal
            ->count();

        $remainingQuota = $schedule->quota - $bookedCount;
        $isFull = $remainingQuota <= 0;

        // Tambahkan informasi tambahan ke dalam JSON respons
        $schedule->booked_count = $bookedCount;
        $schedule->remaining_quota = max(0, $remainingQuota);
        $schedule->is_full = $isFull;

        return $schedule;
    });

    return response()->json([
        'status' => 'success',
        'date' => $date,
        'day_name' => $dayOfWeek,
        'data' => $schedulesWithQuotaInfo
    ], 200);
}

}
