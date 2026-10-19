<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Doctor extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'doctors';

    protected $fillable = [
        'user_id',
        'str_number',       // Nomor Surat Tanda Registrasi (Kompetensi Nasional)
        'sip_number',       // Nomor Surat Izin Praktik di klinik ini
        'specialization',   // Spesialisasi / Poliklinik
        'consultation_fee', // Tarif dasar konsultasi dokter (misal: 50000, 75000)
        'gender',           // Jenis kelamin dokter ('L' / 'P')
        'is_active',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
