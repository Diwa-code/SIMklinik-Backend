<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Doctor extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'doctors';

    protected $fillable = [
        'user_id',
        'sip_number',
        'specialization',
        'is_active'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}