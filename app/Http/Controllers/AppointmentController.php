<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
            $query = Appointment::with(['patient', 'doctor'])
                ->where('patient_id', (string) $user->_id)
                ->orderBy('created_at', 'desc');
        } else {
            // Dokter dan Admin melihat antrean non-draft
            $query = Appointment::with(['patient', 'doctor'])
                ->where('status', '!=', 'draft')
                ->orderBy('date', 'asc')
                ->orderBy('queue_number', 'asc');
        }

        // Filter status fleksibel: misal ?status=waiting atau ?status=waiting,called
        if ($request->filled('status')) {
            $statuses = array_filter(array_map('trim', explode(',', $request->status)));
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        // Filter tanggal jika diberikan (?date=YYYY-MM-DD)
        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        // Filter dokter tertentu (?doctor_id=...)
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        $appointments = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Antrean Berhasil Dimuat',
            'data' => $appointments,
        ], 200);
    }

    /**
     * 1. Rekomendasi Berbasis Keluhan & Field 'specialization' di Koleksi Doctors
     */
    public function recommend(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string',
        ]);

        $appointment = $this->findPatientDraft($request, (string) $request->input('appointment_id'));
        if (! $appointment instanceof Appointment) {
            return $this->appointmentNotFoundResponse();
        }

        $matchedSpecialization = $this->specializationForComplaint($appointment->complaint);
        $availableSchedules = $this->availableSchedulesForAppointment($appointment, $matchedSpecialization);
        $recommendedSchedule = $availableSchedules->first();

        return response()->json([
            'status' => 'success',
            'appointment_id' => (string) $appointment->_id,
            'date' => $appointment->date,
            'complaint' => $appointment->complaint,
            'recommendation' => [
                'specialization' => $matchedSpecialization,
                'doctor_id' => $recommendedSchedule ? $recommendedSchedule->doctor_id : null,
                'doctor_name' => ($recommendedSchedule && $recommendedSchedule->doctor && $recommendedSchedule->doctor->user)
                    ? $recommendedSchedule->doctor->user->name
                    : null,
                'doctor_schedule_id' => $recommendedSchedule ? (string) $recommendedSchedule->_id : null,
                'start_time' => $recommendedSchedule?->start_time,
                'end_time' => $recommendedSchedule?->end_time,
            ],
            'available_doctors' => $availableSchedules,
        ], 200);
    }

    /**
     * 2. Pendaftaran Antrean Pasien
     * Dilengkapi validasi ketersediaan jadwal dokter dan kuota pasien per hari.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'complaint' => 'required|string',
            'guarantor' => 'required|string',
            'require_complete_profile' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $completeness = $user->getProfileCompleteness();

        // Validasi opsional: blokir appointment jika front-end meminta strict profile readiness
        if ($request->boolean('require_complete_profile') || $request->header('X-Require-Complete-Profile') === 'true') {
            if (! $completeness['is_appointment_ready']) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'INCOMPLETE_PROFILE',
                    'message' => 'Harap lengkapi data profil identitas pasien sebelum melakukan janji temu.',
                    'profile_completeness' => $completeness,
                ], 422);
            }
        }

        // Buat draft untuk menetapkan tanggal dan keluhan sebelum dokter dipilih.
        $appointment = Appointment::create([
            'patient_id' => (string) $user->_id,
            'date' => $request->date,
            'complaint' => $request->complaint,
            'guarantor' => $request->guarantor,
            'status' => 'draft',
            'payment_status' => 'unpaid',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Draft appointment berhasil dibuat. Gunakan appointment_id untuk mendapatkan rekomendasi dokter dan jadwal.',
            'data' => $appointment,
            'profile_completeness' => $completeness,
        ], 201);
    }

    public function updateDraft(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'sometimes|required|date_format:Y-m-d|after_or_equal:today',
            'complaint' => 'sometimes|required|string',
            'guarantor' => 'sometimes|required|string',
        ]);

        if ($validated === []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kirim minimal satu field untuk mengubah draft appointment.',
            ], 422);
        }

        $appointment = $this->findPatientDraft($request, $id);
        if (! $appointment instanceof Appointment) {
            return $this->appointmentNotFoundResponse();
        }

        $appointment->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Draft appointment berhasil diperbarui.',
            'data' => $appointment->fresh(),
        ], 200);
    }

    public function confirm(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'doctor_id' => 'required|string',
            'doctor_schedule_id' => 'required|string',
        ]);

        $appointment = $this->findPatientDraft($request, $id);
        if (! $appointment instanceof Appointment) {
            return $this->appointmentNotFoundResponse();
        }

        $doctor = Doctor::where('_id', $request->doctor_id)
            ->where('is_active', true)
            ->first();

        if (! $doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dokter tidak ditemukan atau sedang tidak aktif.',
            ], 404);
        }

        $schedule = DoctorSchedule::where('_id', $request->doctor_schedule_id)
            ->where('doctor_id', $request->doctor_id)
            ->where('schedule_date', $appointment->date)
            ->where('is_active', true)
            ->first();

        if (! $schedule) {
            return response()->json([
                'status' => 'error',
                'message' => "Jadwal praktik yang dipilih tidak aktif atau tidak sesuai dengan dokter dan tanggal ({$appointment->date}).",
            ], 422);
        }

        $matchedSpecialization = $this->specializationForComplaint($appointment->complaint);
        if (! str_contains(mb_strtolower((string) $doctor->specialization), mb_strtolower($matchedSpecialization))) {
            return response()->json([
                'status' => 'error',
                'message' => "Dokter yang dipilih tidak sesuai dengan keluhan. Keluhan ini diarahkan ke {$matchedSpecialization}.",
            ], 422);
        }

        $bookedCount = Appointment::where('doctor_schedule_id', (string) $schedule->_id)
            ->where('date', $appointment->date)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->count();

        if ($bookedCount >= $schedule->quota) {
            return response()->json([
                'status' => 'error',
                'message' => "Kuota antrean untuk sesi {$schedule->start_time}-{$schedule->end_time} pada tanggal {$appointment->date} sudah penuh ({$schedule->quota} antrean).",
            ], 422);
        }

        $existingAppointment = Appointment::where('patient_id', (string) $request->user()->_id)
            ->where('doctor_id', $request->doctor_id)
            ->where('date', $appointment->date)
            ->where('_id', '!=', $appointment->_id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->first();

        if ($existingAppointment) {
            return response()->json([
                'status' => 'error',
                'message' => "Anda sudah memiliki antrean aktif untuk dokter ini pada tanggal {$appointment->date} (Nomor: {$existingAppointment->queue_number}).",
            ], 422);
        }

        $lastAppointment = Appointment::where('doctor_id', $request->doctor_id)
            ->where('date', $appointment->date)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->first();

        $queueNumber = 'A-001';
        if ($lastAppointment && isset($lastAppointment->queue_number)) {
            $lastNumber = (int) substr($lastAppointment->queue_number, 2);
            $queueNumber = 'A-'.str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        }

        $appointment->update([
            'doctor_id' => (string) $doctor->_id,
            'doctor_schedule_id' => (string) $schedule->_id,
            'practice_start_time' => $schedule->start_time,
            'practice_end_time' => $schedule->end_time,
            'queue_number' => $queueNumber,
            'status' => 'waiting',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pilihan dokter dan jadwal berhasil dikonfirmasi. Appointment masuk antrean.',
            'data' => $appointment->fresh(),
        ], 200);
    }

    /**
     * Memperbarui status alur klinis antrean (waiting -> called -> in_consultation -> completed / cancelled).
     * Khusus Dokter dan Admin untuk pemanggilan pasien di poli.
     */
    public function updateStatus(Request $request, $id)
    {
        $appointment = Appointment::find($id);
        if (! $appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data antrean tidak ditemukan.',
            ], 404);
        }

        if ($appointment->status === 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Draft appointment harus dikonfirmasi dengan dokter dan jadwal sebelum status antrean diubah.',
            ], 422);
        }

        $request->validate([
            'status' => 'required|string|in:waiting,called,in_consultation,skipped,completed,cancelled',
        ]);

        $updateData = [
            'status' => $request->status,
        ];

        // Jika dipanggil ('called'), catat waktu dan tambah counter panggilan
        if ($request->status === 'called') {
            $updateData['call_count'] = ((int) ($appointment->call_count ?? 0)) + 1;
            $updateData['last_called_at'] = now();
        }

        // Jika dikembalikan ke 'waiting' (misal pasien sempat tidak di tempat saat dipanggil)
        if ($request->status === 'waiting' && in_array($appointment->status, ['called', 'skipped'])) {
            $updateData['returned_to_waiting_at'] = now();
            $updateData['is_recalled'] = true;
        }

        $appointment->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => "Status antrean berhasil diperbarui menjadi '{$request->status}'",
            'data' => $appointment->fresh(),
        ], 200);
    }

    public function getDoctorsByDate(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string',
        ]);

        $appointment = $this->findPatientDraft($request, (string) $request->input('appointment_id'));
        if (! $appointment instanceof Appointment) {
            return $this->appointmentNotFoundResponse();
        }

        $matchedSpecialization = $this->specializationForComplaint($appointment->complaint);
        $schedulesWithQuotaInfo = $this->availableSchedulesForAppointment($appointment, $matchedSpecialization);

        return response()->json([
            'status' => 'success',
            'appointment_id' => (string) $appointment->_id,
            'date' => $appointment->date,
            'complaint' => $appointment->complaint,
            'specialization' => $matchedSpecialization,
            'data' => $schedulesWithQuotaInfo,
        ], 200);
    }

    private function findPatientDraft(Request $request, string $appointmentId): ?Appointment
    {
        return Appointment::where('_id', $appointmentId)
            ->where('patient_id', (string) $request->user()->_id)
            ->where('status', 'draft')
            ->first();
    }

    private function appointmentNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Draft appointment tidak ditemukan atau bukan milik Anda.',
        ], 404);
    }

    private function availableSchedulesForAppointment(Appointment $appointment, string $matchedSpecialization): Collection
    {
        return DoctorSchedule::with(['doctor.user'])
            ->where('schedule_date', $appointment->date)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('end_time')
            ->get()
            ->filter(function (DoctorSchedule $schedule) use ($matchedSpecialization): bool {
                $doctor = $schedule->doctor;

                return $doctor !== null
                    && $doctor->is_active
                    && str_contains(mb_strtolower((string) $doctor->specialization), mb_strtolower($matchedSpecialization));
            })
            ->map(function (DoctorSchedule $schedule) use ($appointment): DoctorSchedule {
                $bookedCount = Appointment::where('doctor_schedule_id', (string) $schedule->_id)
                    ->where('date', $appointment->date)
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->count();

                $remainingQuota = max(0, $schedule->quota - $bookedCount);
                $schedule->booked_count = $bookedCount;
                $schedule->remaining_quota = $remainingQuota;
                $schedule->is_full = $remainingQuota === 0;
                $schedule->appointment_date = $appointment->date;

                return $schedule;
            })
            ->where('is_full', false)
            ->values();
    }

    private function specializationForComplaint(string $complaint): string
    {
        $complaint = mb_strtolower($complaint);

        if (str_contains($complaint, 'gigi') || str_contains($complaint, 'gusi')) {
            return 'Poliklinik Gigi';
        }

        if (str_contains($complaint, 'anak') || str_contains($complaint, 'bayi') || str_contains($complaint, 'imunisasi')) {
            return 'Poliklinik Anak';
        }

        return 'Poliklinik Umum';
    }
}
