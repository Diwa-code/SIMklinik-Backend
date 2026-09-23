<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DoctorSchedule;

class DoctorScheduleController extends Controller
{
    // Melihat jadwal aktif berdasarkan ID Dokter
    public function show($doctorId)
    {
        $schedules = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'message' => 'Jadwal Praktik Dokter Dimuat',
            'data' => $schedules
        ]);
    }

    // Admin atau Dokter menambahkan jadwal baru
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|string',
            'day_of_week' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'quota' => 'required|integer|min:1'
        ]);

        $schedule = DoctorSchedule::create([
            'doctor_id' => $request->doctor_id,
            'day_of_week' => $request->day_of_week,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'quota' => $request->quota,
            'is_active' => true
        ]);

        return response()->json([
            'message' => 'Jadwal Praktik Berhasil Ditambahkan',
            'data' => $schedule
        ], 201);
    }
}