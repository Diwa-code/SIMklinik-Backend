<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Models\Appointment;
use App\Models\Medicine;

class MedicalRecordController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string',
            'patient_id' => 'required|string',
            'doctor_id' => 'required|string',
            'soap' => 'required|array',
            'soap.plan.prescriptions' => 'sometimes|array',
            'soap.plan.prescriptions.*.medicine' => 'required_with:soap.plan.prescriptions|string',
            'soap.plan.prescriptions.*.qty' => 'required_with:soap.plan.prescriptions|integer'
        ]);

        // 1. Simpan Rekam Medis SOAP
        $medicalRecord = MedicalRecord::create([
            'appointment_id' => $request->appointment_id,
            'patient_id' => $request->patient_id,
            'doctor_id' => $request->doctor_id,
            'soap' => $request->soap
        ]);

        // 2. Otomatis Potong Stok Berdasarkan Resep (Logika FEFO)
        if (isset($request->soap['plan']['prescriptions'])) {
            foreach ($request->soap['plan']['prescriptions'] as $item) {
                // Cari data obat berdasarkan nama
                $medicine = Medicine::where('name', $item['medicine'])->first();

                if ($medicine && !empty($medicine->batches)) {
                    // Urutkan batch berdasarkan exp_date terdekat (Ascending)
                    $batches = collect($medicine->batches)->sortBy('exp_date')->values()->all();
                    
                    $qtyNeeded = $item['qty'];
                    $updatedBatches = [];

                    foreach ($batches as $batch) {
                        if ($qtyNeeded > 0 && $batch['stock'] > 0) {
                            if ($batch['stock'] >= $qtyNeeded) {
                                // Stok batch ini cukup untuk mencukupi kebutuhan
                                $batch['stock'] -= $qtyNeeded;
                                $qtyNeeded = 0;
                            } else {
                                // Stok batch ini habis terserap, lanjut ke batch berikutnya
                                $qtyNeeded -= $batch['stock'];
                                $batch['stock'] = 0;
                            }
                        }
                        $updatedBatches[] = $batch;
                    }

                    // Simpan kembali array batches yang sudah terpotong ke MongoDB
                    $medicine->update(['batches' => $updatedBatches]);
                }
            }
        }

        // 3. Ubah Status Antrean Menjadi Selesai
        Appointment::where('_id', $request->appointment_id)->update(['status' => 'done']);

        return response()->json([
            'message' => 'Rekam Medis Tersimpan & Stok Obat Berhasil Dikurangi secara FEFO',
            'data' => $medicalRecord
        ], 201);
    }

    public function patientHistory($patientId)
    {
        $records = MedicalRecord::where('patient_id', $patientId)
            ->with(['appointment.doctor'])
            ->get();

        return response()->json([
            'message' => 'Riwayat Rekam Medis Berhasil Dimuat',
            'data' => $records
        ]);
    }
}