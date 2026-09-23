<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Patient extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'patients';

    protected $fillable = [
        'user_id',
        'nik',
        'date_of_birth',
        'gender',
        'blood_type',
        'allergies', // Berisi array teks di MongoDB
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}