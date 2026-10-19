<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAuditLog;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    /**
     * Menyimpan Rekam Medis SOAP oleh Dokter
     * Sekaligus mencatat Audit Trail dan mengunci validasi bahwa pasien ini telah selesai diperiksa.
     */
    public function store(Request $request)
    {
        // 1. Validasi input form SOAP dari dokter
        $request->validate([
            'appointment_id' => 'required|string', // Menghubungkan ke sesi antrean pasien
            'patient_id' => 'required|string',
            'subjective' => 'required|string', // Keluhan / Anamnesis dari pasien
            'objective' => 'required|string', // Hasil pemeriksaan fisik / tanda vital
            'assessment' => 'required|string', // Diagnosa / Penilaian dokter
            'plan' => 'required|string', // Rencana pengobatan / tindakan / resep
            'prescriptions' => 'nullable|array',   // Daftar obat jika ada resep (untuk modul apotek)
        ]);

        // 2. Ambil data antrean (appointment) untuk memastikan pasien ini memang terdaftar
        $appointment = Appointment::find($request->appointment_id);
        if (! $appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data antrean pasien tidak ditemukan.',
            ], 404);
        }

        if ($appointment->status === 'draft' || ! $appointment->doctor_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment harus dikonfirmasi dengan dokter dan jadwal sebelum rekam medis dibuat.',
            ], 422);
        }

        $user = $request->user();

        // Ambil profil dokter dari koleksi doctors untuk konsistensi relasi doctor_id
        $doctorProfile = Doctor::where('user_id', $user->_id)->first();
        $doctorId = $doctorProfile ? (string) $doctorProfile->_id : (string) $user->_id;

        // Validasi: pastikan dokter yang mengisi rekam medis adalah dokter yang dituju di antrean
        if ($doctorProfile && (string) $appointment->doctor_id !== (string) $doctorProfile->_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda tidak memiliki wewenang untuk mengisi rekam medis antrean dokter lain.',
            ], 403);
        }

        // 3. Simpan data rekam medis (SOAP) ke database MongoDB
        $medicalRecord = MedicalRecord::create([
            'appointment_id' => $request->appointment_id,
            'patient_id' => $request->patient_id,
            'doctor_id' => $doctorId,
            'subjective' => $request->subjective,
            'objective' => $request->objective,
            'assessment' => $request->assessment,
            'plan' => $request->plan,
            'prescriptions' => $request->prescriptions ?? [],
            'examined_at' => now(),
        ]);

        // 4. Catat ke Audit Trail (Kepatuhan UU PDP & Regulasi Rekam Medis)
        MedicalRecordAuditLog::create([
            'user_id' => (string) $user->_id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'patient_id' => (string) $request->patient_id,
            'action' => 'create',
            'medical_record_id' => (string) $medicalRecord->_id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => [
                'appointment_id' => $request->appointment_id,
                'diagnosis' => $request->assessment,
                'has_prescriptions' => ! empty($request->prescriptions),
                'prescriptions_qty' => count($request->prescriptions ?? []),
            ],
            'accessed_at' => now(),
        ]);

        // 5. Ubah status antrean pasien ini menjadi 'completed' (selesai).
        $appointment->update([
            'status' => 'completed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekam medis SOAP berhasil disimpan. Sesi pasien selesai, silakan lanjut ke pasien berikutnya.',
            'data' => [
                'medical_record' => $medicalRecord,
                'next_queue_status' => 'ready', // Sinyal ke frontend bahwa tombol Next Patient diizinkan
            ],
        ], 201);
    }

    /**
     * Riwayat Rekam Medis Pasien (Bisa diakses dokter, admin, atau pasien bersangkutan)
     * Dilengkapi proteksi keamanan data pribadi (UU PDP) dan pencatatan Audit Trail.
     */
    public function patientHistory(Request $request, $patientId)
    {
        $currentUser = $request->user();

        // Keamanan UU PDP: Pasien hanya berhak mengakses rekam medis miliknya sendiri!
        if ($currentUser->role === 'patient' && (string) $currentUser->_id !== (string) $patientId) {
            // Catat upaya akses tidak sah ke audit trail
            MedicalRecordAuditLog::create([
                'user_id' => (string) $currentUser->_id,
                'user_name' => $currentUser->name,
                'user_role' => $currentUser->role,
                'patient_id' => (string) $patientId,
                'action' => 'unauthorized_attempt',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'details' => [
                    'reason' => 'Pasien mencoba mengakses rekam medis milik pasien lain secara ilegal.',
                    'target_patient_id' => $patientId,
                ],
                'accessed_at' => now(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda hanya memiliki izin untuk melihat rekam medis milik Anda sendiri.',
            ], 403);
        }

        $records = MedicalRecord::with(['doctor.user'])
            ->where('patient_id', $patientId)
            ->orderBy('examined_at', 'desc')
            ->get();

        // Catat Audit Trail pembacaan data rekam medis
        MedicalRecordAuditLog::create([
            'user_id' => (string) $currentUser->_id,
            'user_name' => $currentUser->name,
            'user_role' => $currentUser->role,
            'patient_id' => (string) $patientId,
            'action' => 'view_history',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => [
                'records_count' => $records->count(),
            ],
            'accessed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat rekam medis berhasil dimuat',
            'data' => $records,
        ], 200);
    }

    /**
     * Menampilkan Audit Trail & Log Akses Rekam Medis (Khusus Admin / Tim Kepatuhan)
     */
    public function auditLogs(Request $request)
    {
        $query = MedicalRecordAuditLog::orderBy('accessed_at', 'desc');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $perPage = (int) $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data audit log rekam medis berhasil dimuat',
            'data' => $logs,
        ], 200);
    }
}
