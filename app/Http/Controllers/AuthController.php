<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
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
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Cari akun user
        $user = User::where('email', $request->email)->first();

        // 3. Cek ketersediaan dan kecocokan password
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau Password salah'], 401);
        }

        // 4. Buat Token API
        $token = $user->createToken('simklinik-token')->plainTextToken;

        // 5. Siapkan data response berdasarkan Role
        $profile = null;
        $profileCompleteness = null;
        if ($user->role === 'patient') {
            $profile = $user->patientProfile ?? null;
            $profileCompleteness = $user->getProfileCompleteness();
        } elseif ($user->role === 'doctor') {
            $profile = $user->doctorProfile ?? null;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login Berhasil',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile_data' => $profile,
            'profile_completeness' => $profileCompleteness,
        ], 200);
    }

    /**
     * Proses Registrasi Pasien Baru (Publik)
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:mongodb.users,email',
            'password' => 'required|string|min:6',
            'phone' => 'required|string',
            'address' => 'required|string',
            'role' => 'sometimes|string|in:patient,pharmacist',
            'nik' => 'sometimes|nullable|string',
            'bpjs_number' => 'sometimes|nullable|string',
            'place_of_birth' => 'sometimes|nullable|string',
            'date_of_birth' => 'sometimes|nullable|date',
            'gender' => 'sometimes|nullable|in:L,P',
            'blood_type' => 'sometimes|nullable|string',
            'rhesus' => 'sometimes|nullable|string|in:+,-',
            'marital_status' => 'sometimes|nullable|string',
            'occupation' => 'sometimes|nullable|string',
            'allergies' => 'sometimes|nullable|array',
            'chronic_diseases' => 'sometimes|nullable|array',
            'emergency_contact_name' => 'sometimes|nullable|string',
            'emergency_contact_phone' => 'sometimes|nullable|string',
            'emergency_contact_relation' => 'sometimes|nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'role' => $request->role ?? 'patient',
        ]);

        $patientProfile = null;
        $profileCompleteness = null;
        if ($user->role === 'patient') {
            $patientProfile = Patient::create([
                'user_id' => $user->_id,
                'nik' => $request->nik ?? null,
                'bpjs_number' => $request->bpjs_number ?? null,
                'place_of_birth' => $request->place_of_birth ?? null,
                'date_of_birth' => $request->date_of_birth ?? null,
                'gender' => $request->gender ?? null,
                'blood_type' => $request->blood_type ?? null,
                'rhesus' => $request->rhesus ?? null,
                'marital_status' => $request->marital_status ?? null,
                'occupation' => $request->occupation ?? null,
                'allergies' => $request->allergies ?? [],
                'chronic_diseases' => $request->chronic_diseases ?? [],
                'emergency_contact_name' => $request->emergency_contact_name ?? null,
                'emergency_contact_phone' => $request->emergency_contact_phone ?? null,
                'emergency_contact_relation' => $request->emergency_contact_relation ?? null,
            ]);
            $profileCompleteness = $user->getProfileCompleteness();
        }

        $token = $user->createToken('simklinik-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi '.$user->role.' berhasil',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'profile_data' => $patientProfile,
            'profile_completeness' => $profileCompleteness,
        ], 201);
    }
}
