<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Proses Login Pengguna (Semua Role)
     */
    public function login(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        // 2. Cari akun user
        $user = User::where('email', $request->email)->first();

        // 3. Cek ketersediaan dan kecocokan password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau Password salah'], 401);
        }

        // 4. Buat Token API
        $token = $user->createToken('simklinik-token')->plainTextToken;

        // 5. Siapkan data response berdasarkan Role
        $profile = null;
        if ($user->role === 'patient') {
            $profile = $user->patientProfile ?? null;
        } elseif ($user->role === 'doctor') {
            $profile = $user->doctorProfile ?? null;
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Login Berhasil',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
            'profile_data' => $profile
        ], 200);
    }

    /**
     * Proses Registrasi Pasien Baru (Publik)
     */
    public function register(Request $request)
    {
        // 1. Validasi input data registrasi pasien
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone'    => 'required|string',
            'address'  => 'required|string'
        ]);

        // 2. Buat user baru dengan role otomatis 'patient'
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
            'address'  => $request->address,
            'role'     => 'patient'
        ]);

        // 3. (Opsional) Jika Anda memiliki relasi terpisah untuk patientProfile, 
        // bisa dibuatkan di sini atau dibiarkan tersimpan di collection user.

        // 4. Buat token langsung agar setelah register pasien bisa langsung otomatis masuk
        $token = $user->createToken('simklinik-token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Registrasi pasien berhasil',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]
        ], 201);
    }
}