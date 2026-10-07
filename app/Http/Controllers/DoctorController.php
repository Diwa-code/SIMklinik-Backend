<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Support\Facades\Hash;

class DoctorController extends Controller
{
    /**
     * Pendaftaran Dokter Baru (Dilindungi Secret Key Manajemen Klinik)
     */
    public function store(Request $request)
    {
        // 1. Validasi Header Secret Key agar tidak sembarang orang bisa mendaftar
        $secretKey = $request->header('X-Register-Secret');
        if ($secretKey !== config('app.doctor_register_secret', 'RAHASIA_KLINIK')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Anda tidak memiliki hak akses untuk mendaftarkan dokter.'
            ], 403);
        }

        // 2. Validasi input data kredensial & legalitas medis
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
            'str_number' => 'required|string|unique:doctors,str_number',
            'sip_number' => 'required|string|unique:doctors,sip_number',
            'specialization' => 'required|string',
        ]);

        try {
            // 3. Buat akun dasar di koleksi 'users' dengan role 'doctor'
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'doctor',
            ]);

            // 4. Buat profil spesifik di koleksi 'doctors' yang terhubung via user_id
            $doctor = Doctor::create([
                'user_id' => $user->_id,
                'str_number' => $request->str_number,
                'sip_number' => $request->sip_number,
                'specialization' => $request->specialization,
                'is_active' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Data dokter dan legalitas praktiknya berhasil didaftarkan.',
                'data' => [
                    'user' => $user,
                    'doctor_profile' => $doctor
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mendaftarkan dokter: ' . $e->getMessage()
            ], 500);
        }
    }
}