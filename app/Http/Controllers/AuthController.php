<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'email' => 'required|email',
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
            $profile = $user->patientProfile; // Memanggil relasi yang kita buat sebelumnya
        } elseif ($user->role === 'doctor') {
            $profile = $user->doctorProfile;
        }

        return response()->json([
            'message' => 'Login Berhasil',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile_data' => $profile
        ]);
    }
}