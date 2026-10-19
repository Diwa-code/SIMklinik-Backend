<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection = 'mongodb';

    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function patientProfile()
    {
        return $this->hasOne(Patient::class, 'user_id');
    }

    public function doctorProfile()
    {
        return $this->hasOne(Doctor::class, 'user_id');
    }

    /**
     * Hitung kelengkapan profil pasien untuk indikator notifikasi front-end
     */
    public function getProfileCompleteness(): array
    {
        $patient = $this->patientProfile()->first();

        $fields = [
            'name' => ! empty($this->name),
            'phone' => ! empty($this->phone),
            'address' => ! empty($this->address),
            'nik' => ! empty($patient?->nik),
            'place_of_birth' => ! empty($patient?->place_of_birth),
            'date_of_birth' => ! empty($patient?->date_of_birth),
            'gender' => ! empty($patient?->gender),
            'blood_type' => ! empty($patient?->blood_type),
            'emergency_contact_name' => ! empty($patient?->emergency_contact_name),
            'emergency_contact_phone' => ! empty($patient?->emergency_contact_phone),
        ];

        // Field kritis yang dibutuhkan faskes sebelum booking appointment / rekam medis
        $criticalFields = [
            'nik' => ! empty($patient?->nik),
            'date_of_birth' => ! empty($patient?->date_of_birth),
            'gender' => ! empty($patient?->gender),
            'phone' => ! empty($this->phone),
        ];

        $completedCount = count(array_filter($fields));
        $totalCount = count($fields);
        $percentage = (int) round(($completedCount / $totalCount) * 100);

        $missingFields = array_keys(array_filter($fields, fn ($val) => ! $val));
        $missingCriticalFields = array_keys(array_filter($criticalFields, fn ($val) => ! $val));

        $isComplete = empty($missingFields);
        $isAppointmentReady = empty($missingCriticalFields);

        return [
            'is_complete' => $isComplete,
            'is_appointment_ready' => $isAppointmentReady,
            'percentage' => $percentage,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'missing_fields' => $missingFields,
            'missing_critical_fields' => $missingCriticalFields,
            'message' => $isComplete
                ? 'Profil data pasien sudah lengkap.'
                : ($isAppointmentReady
                    ? 'Data esensial siap untuk janji temu. Lengkapi sisa data profil untuk rekam medis optimal.'
                    : 'Harap lengkapi data identitas (NIK, tanggal lahir, jenis kelamin, nomor telepon) sebelum membuat janji temu.'),
        ];
    }
}
