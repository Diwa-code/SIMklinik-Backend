<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class DoctorSchedule extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'doctor_schedules';

    protected $fillable = [
        'doctor_id',
        'schedule_date', // Tanggal kalender praktik, format YYYY-MM-DD.
        'start_time',  // 08:00
        'end_time',    // 12:00
        'quota',       // Maksimal pasien per sesi
        'is_active',    // boolean
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }
}
