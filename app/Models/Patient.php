<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Patient extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'patients';

    protected $fillable = [
        'user_id',
        'nik',
        'bpjs_number',              // Nomor kartu BPJS (13 digit)
        'place_of_birth',           // Tempat lahir
        'date_of_birth',
        'gender',
        'blood_type',
        'rhesus',                   // Rhesus (+/-)
        'marital_status',           // Status pernikahan
        'occupation',               // Pekerjaan
        'allergies',                // Berisi array teks di MongoDB
        'chronic_diseases',         // Riwayat penyakit bawaan/kronis (array)
        'emergency_contact_name',   // Nama kontak darurat keluarga
        'emergency_contact_phone',  // Nomor HP kontak darurat
        'emergency_contact_relation', // Hubungan: Orang Tua, Pasangan, Anak, dll
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
