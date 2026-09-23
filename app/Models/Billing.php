<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'billings';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'consultation_fee', // Biaya jasa dokter
        'action_fee',       // Biaya tindakan (misal: cek darah, jahit luka)
        'medicine_fee',     // Total harga obat yang ditebus
        'grand_total',      // Jumlah akhir yang harus dibayar
        'payment_status',   // unpaid, paid, cancelled
        'payment_method'    // cash, transfer, asuransi
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}