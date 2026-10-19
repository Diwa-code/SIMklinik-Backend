<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class MedicalRecordAuditLog extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'medical_record_audit_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'patient_id',
        'action', // 'view_history', 'view_detail', 'create', 'update', 'unauthorized_attempt'
        'medical_record_id',
        'ip_address',
        'user_agent',
        'details',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
        'details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
