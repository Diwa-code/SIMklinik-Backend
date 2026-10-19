<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MongoDB\Laravel\Eloquent\Model;

class Appointment extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'appointments';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'doctor_schedule_id',
        'practice_start_time',
        'practice_end_time',
        'queue_number',
        'date',
        'complaint',
        'guarantor', // Contoh: umum, asuransi
        'status', // Contoh: waiting, called, in_consultation, skipped, completed, cancelled
        'payment_status',
        'call_count',
        'last_called_at',
        'returned_to_waiting_at',
        'is_recalled',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class, 'appointment_id');
    }
}
