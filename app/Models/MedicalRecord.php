<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'medical_records';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'soap' 
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}