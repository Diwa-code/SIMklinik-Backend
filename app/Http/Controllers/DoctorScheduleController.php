<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    /**
     * Melihat jadwal aktif berdasarkan ID Dokter
     */
    public function show($doctorId)
    {
        $doctor = Doctor::find($doctorId)
            ?? Doctor::where('user_id', $doctorId)->first();

        $targetDoctorId = $doctor ? (string) $doctor->_id : $doctorId;

        $schedules = DoctorSchedule::where('doctor_id', $targetDoctorId)
            ->where('is_active', true)
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'message' => 'Jadwal Praktik Dokter Dimuat',
            'data' => $schedules,
        ]);
    }

    /**
     * Admin atau Dokter menambahkan jadwal praktik baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|string',
            'schedule_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'quota' => 'required|integer|min:1',
        ]);

        $doctor = Doctor::find($request->doctor_id)
            ?? Doctor::where('user_id', $request->doctor_id)->first();

        if (! $doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil dokter tidak ditemukan. Pastikan parameter doctor_id valid.',
            ], 404);
        }

        $schedule = DoctorSchedule::create([
            'doctor_id' => (string) $doctor->_id,
            'schedule_date' => $request->schedule_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'quota' => $request->quota,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Jadwal Praktik Berhasil Ditambahkan',
            'data' => $schedule,
        ], 201);
    }

    /**
     * Memperbarui jadwal praktik (jam, kuota, atau status aktif)
     */
    public function update(Request $request, $id)
    {
        $schedule = DoctorSchedule::find($id);

        if (! $schedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jadwal praktik tidak ditemukan.',
            ], 404);
        }

        $request->validate([
            'schedule_date' => 'sometimes|date_format:Y-m-d|after_or_equal:today',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'quota' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $schedule->update($request->only([
            'schedule_date', 'start_time', 'end_time', 'quota', 'is_active',
        ]));

        return response()->json([
            'message' => 'Jadwal Praktik Berhasil Diperbarui',
            'data' => $schedule->fresh(),
        ], 200);
    }

    /**
     * Menonaktifkan / menghapus jadwal praktik (soft deactivate via is_active=false)
     */
    public function destroy($id)
    {
        $schedule = DoctorSchedule::find($id);

        if (! $schedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jadwal praktik tidak ditemukan.',
            ], 404);
        }

        // Soft-deactivate: tandai jadwal tidak aktif agar data historis tetap terjaga
        $schedule->update(['is_active' => false]);

        return response()->json([
            'message' => 'Jadwal praktik berhasil dinonaktifkan',
        ], 200);
    }
}
