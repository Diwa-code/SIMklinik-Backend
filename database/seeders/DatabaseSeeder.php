<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\DoctorSchedule;
use App\Models\Medicine;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Akun Admin / Superuser
        User::create([
            'name' => 'I Wayan Diwangga Santa Dinata',
            'email' => 'admin@simklinik.com',
            'password' => Hash::make('password123'),
            'role' => 'admin'
        ]);

        // 2. Buat Akun Dokter & Profilnya
        $doctorUser = User::create([
            'name' => 'dr. Andi Wijaya, Sp.PD',
            'email' => 'dokter@simklinik.com',
            'password' => Hash::make('password123'),
            'role' => 'doctor'
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'sip' => 'SIP.12345.2026',
            'specialization' => 'Penyakit Dalam',
            'phone' => '08123456789'
        ]);

        // 3. Buat Jadwal Praktik untuk dr. Andi
        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'day_of_week' => 'Senin',
            'start_time' => '08:00',
            'end_time' => '14:00',
            'quota' => 20,
            'is_active' => true
        ]);

        // 4. Buat Akun Pasien & Profilnya
        $patientUser = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@simklinik.com',
            'password' => Hash::make('password123'),
            'role' => 'patient'
        ]);

        Patient::create([
            'user_id' => $patientUser->id,
            'nik' => '5171234567890001',
            'date_of_birth' => '1995-08-17',
            'gender' => 'L',
            'blood_type' => 'O',
            'allergies' => ['Penisilin', 'Udang']
        ]);

        // 5. Buat Data Obat (Dengan Struktur Batch FEFO)
        Medicine::create([
            'name' => 'Paracetamol 500mg',
            'category' => 'Tablet',
            'base_price' => 5000,
            'batches' => [
                [
                    'batch_no' => 'BATCH-A01',
                    'stock' => 50,
                    'exp_date' => '2026-12-01' // Kedaluwarsa lebih cepat (akan dipotong duluan)
                ],
                [
                    'batch_no' => 'BATCH-A02',
                    'stock' => 100,
                    'exp_date' => '2027-06-15'
                ]
            ]
        ]);

        $this->command->info('Database SIMRS berhasil diisi dengan data awal!');
    }
}