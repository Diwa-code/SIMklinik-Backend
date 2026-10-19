<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * Menampilkan profil lengkap pasien yang sedang login beserta status kelengkapannya.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = $user->patientProfile;
        $completeness = $user->getProfileCompleteness();

        return response()->json([
            'status' => 'success',
            'message' => 'Profil pasien berhasil dimuat',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'role' => $user->role,
                ],
                'patient_profile' => $patient,
                'profile_completeness' => $completeness,
            ],
        ], 200);
    }

    /**
     * Memperbarui / melengkapi data profil pasien (biodata, medis dasar, kontak darurat).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string',
            'address' => 'sometimes|string',
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

        // 1. Update data akun dasar (users)
        $userUpdates = $request->only(['name', 'phone', 'address']);
        if (! empty($userUpdates)) {
            $user->update($userUpdates);
        }

        // 2. Update atau create data profil medis & demografi (patients)
        $patientFields = $request->only([
            'nik',
            'bpjs_number',
            'place_of_birth',
            'date_of_birth',
            'gender',
            'blood_type',
            'rhesus',
            'marital_status',
            'occupation',
            'allergies',
            'chronic_diseases',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relation',
        ]);

        $patient = Patient::where('user_id', $user->_id)->first();
        if ($patient) {
            $patient->update($patientFields);
        } else {
            $patientFields['user_id'] = $user->_id;
            $patient = Patient::create($patientFields);
        }

        // 3. Hitung ulang kelengkapan data
        $completeness = $user->fresh()->getProfileCompleteness();

        return response()->json([
            'status' => 'success',
            'message' => 'Profil pasien berhasil diperbarui',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'role' => $user->role,
                ],
                'patient_profile' => $patient->fresh(),
                'profile_completeness' => $completeness,
            ],
        ], 200);
    }
}
