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

        // 2. Buat Akun Apoteker (Untuk uji endpoint input obat)
        User::create([
            'name' => 'Siti Apoteker, S.Farm',
            'email' => 'apoteker@simklinik.com',
            'password' => Hash::make('password123'),
            'role' => 'pharmacist'
        ]);

        // 3. Buat Akun Dokter & Profilnya (Disesuaikan dengan field str_number & sip_number)
        $doctorUser = User::create([
            'name' => 'dr. Andi Wijaya, Sp.PD',
            'email' => 'dokter@simklinik.com',
            'password' => Hash::make('password123'),
            'role' => 'doctor'
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->_id,
            'str_number' => 'STR-98765432',
            'sip_number' => 'SIP.12345.2026',
            'specialization' => 'Poliklinik Penyakit Dalam', // Disesuaikan agar klop dengan rekomendasi keluhan
            'is_active' => true
        ]);

        // 4. Buat Jadwal Praktik untuk dr. Andi
        DoctorSchedule::create([
            'doctor_id' => $doctor->_id,
            'day_of_week' => 'Senin',
            'start_time' => '08:00',
            'end_time' => '14:00',
            'quota' => 20,
            'is_active' => true
        ]);

        // 5. Buat Akun Pasien & Profilnya
        $patientUser = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@simklinik.com',
            'password' => Hash::make('password123'),
            'role' => 'patient'
        ]);

        Patient::create([
            'user_id' => $patientUser->_id,
            'nik' => '5171234567890001',
            'date_of_birth' => '1995-08-17',
            'gender' => 'L',
            'blood_type' => 'O',
            'allergies' => ['Penisilin', 'Udang']
        ]);

        // 6. Buat Data Obat (Dengan Struktur Batch FEFO)
        Medicine::create([
            'name' => 'Paracetamol 500mg',
            'category' => 'Tablet',
            'stock' => 150,
            'unit_price' => 5000,
            'expired_date' => '2026-12-01',
            'batches' => [
                [
                    'batch_no' => 'BATCH-A01',
                    'stock' => 50,
                    'exp_date' => '2026-12-01'
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