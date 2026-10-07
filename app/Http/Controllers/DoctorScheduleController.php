<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DoctorSchedule;

class DoctorScheduleController extends Controller
{
    /**
     * Melihat jadwal aktif berdasarkan ID Dokter
     */
    public function show($doctorId)
    {
        $schedules = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'message' => 'Jadwal Praktik Dokter Dimuat',
            'data'    => $schedules
        ]);
    }

    /**
     * Admin atau Dokter menambahkan jadwal praktik baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id'   => 'required|string',
            'day_of_week' => 'required|string',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'quota'       => 'required|integer|min:1'
        ]);

        $schedule = DoctorSchedule::create([
            'doctor_id'   => $request->doctor_id,
            'day_of_week' => $request->day_of_week,
            'start_time'  => $request->start_time,
            'end_time'    => $request->end_time,
            'quota'       => $request->quota,
            'is_active'   => true
        ]);

        return response()->json([
            'message' => 'Jadwal Praktik Berhasil Ditambahkan',
            'data'    => $schedule
        ], 201);
    }

    /**
     * Memperbarui jadwal praktik (jam, kuota, atau status aktif)
     */
    public function update(Request $request, $id)
    {
        $schedule = DoctorSchedule::find($id);

        if (!$schedule) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Jadwal praktik tidak ditemukan.'
            ], 404);
        }

        $request->validate([
            'day_of_week' => 'sometimes|string',
            'start_time'  => 'sometimes|date_format:H:i',
            'end_time'    => 'sometimes|date_format:H:i|after:start_time',
            'quota'       => 'sometimes|integer|min:1',
            'is_active'   => 'sometimes|boolean'
        ]);

        $schedule->update($request->only([
            'day_of_week', 'start_time', 'end_time', 'quota', 'is_active'
        ]));

        return response()->json([
            'message' => 'Jadwal Praktik Berhasil Diperbarui',
            'data'    => $schedule->fresh()
        ], 200);
    }

    /**
     * Menonaktifkan / menghapus jadwal praktik (soft deactivate via is_active=false)
     */
    public function destroy($id)
    {
        $schedule = DoctorSchedule::find($id);

        if (!$schedule) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Jadwal praktik tidak ditemukan.'
            ], 404);
        }

        // Soft-deactivate: tandai jadwal tidak aktif agar data historis tetap terjaga
        $schedule->update(['is_active' => false]);

        return response()->json([
            'message' => 'Jadwal praktik berhasil dinonaktifkan',
        ], 200);
    }
}