<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSchedule extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'doctor_schedules';

    protected $fillable = [
        'doctor_id',
        'day_of_week', // Senin, Selasa, dst.
        'start_time',  // 08:00
        'end_time',    // 12:00
        'quota',       // Maksimal pasien per sesi
        'is_active'    // boolean
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }
}