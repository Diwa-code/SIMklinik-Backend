<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Billing;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Medicine;

class BillingController extends Controller
{
    /**
     * Menampilkan seluruh daftar tagihan (Admin / Apoteker)
     */
    public function index()
    {
        $billings = Billing::with(['appointment', 'patient'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar tagihan pembayaran berhasil dimuat',
            'data'    => $billings
        ], 200);
    }

    /**
     * Menampilkan detail satu tagihan berdasarkan ID
     */
    public function show($id)
    {
        $billing = Billing::with(['appointment', 'patient'])->find($id);

        if (!$billing) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data tagihan tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail tagihan berhasil dimuat',
            'data'    => $billing
        ], 200);
    }

    /**
     * Membuat tagihan otomatis + Proses Resep Apotek & Potong Stok FEFO
     */
    public function store(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string',
            'doctor_fee'     => 'required|numeric|min:0',
            'guarantor'      => 'required|string' // Umum, BPJS, dll
        ]);

        // 1. Validasi keberadaan appointment
        $appointment = Appointment::find($request->appointment_id);
        if (!$appointment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data antrean (appointment) tidak ditemukan.'
            ], 404);
        }

        // 2. Ambil data Rekam Medis (SOAP) untuk melihat resep obat dokter
        $medicalRecord = MedicalRecord::where('appointment_id', $request->appointment_id)->first();

        $medicineFee = 0;

        // 3. Jika dokter memberikan resep, proses pemotongan stok obat (FEFO) & hitung biaya obat
        if ($medicalRecord && !empty($medicalRecord->prescriptions)) {
            foreach ($medicalRecord->prescriptions as $item) {
                $medicineId = $item['medicine_id'] ?? $item['id'] ?? null;
                $qtyNeeded = $item['quantity'] ?? $item['qty'] ?? 1;

                if (!$medicineId) continue;

                $medicine = Medicine::find($medicineId);
                if (!$medicine) continue;

                // Urutkan batches berdasarkan tanggal kedaluwarsa terdekat (FEFO)
                $batches = collect($medicine->batches)->sortBy('exp_date')->values();
                $remainingQtyNeeded = $qtyNeeded;
                $updatedBatches = $batches->toArray();
                $itemCost = 0;

                // Kurangi stok dari batch terdekat kedaluwarsa
                foreach ($updatedBatches as &$batch) {
                    if ($remainingQtyNeeded <= 0) break;

                    if ($batch['stock'] > 0) {
                        if ($batch['stock'] >= $remainingQtyNeeded) {
                            $batch['stock'] -= $remainingQtyNeeded;
                            $itemCost += $remainingQtyNeeded * ($medicine->base_price ?? $medicine->unit_price ?? 0);
                            $remainingQtyNeeded = 0;
                        } else {
                            $remainingQtyNeeded -= $batch['stock'];
                            $itemCost += $batch['stock'] * ($medicine->base_price ?? $medicine->unit_price ?? 0);
                            $batch['stock'] = 0;
                        }
                    }
                }

                // Simpan pembaruan stok batches ke database inventaris obat
                $medicine->update(['batches' => $updatedBatches]);

                $medicineFee += $itemCost;
            }
        }

        // 4. Penyesuaian Tarif jika Pasien menggunakan BPJS
        $doctorFee = ($request->guarantor === 'BPJS') ? 0 : $request->doctor_fee;
        $medicineFee = ($request->guarantor === 'BPJS') ? 0 : $medicineFee;
        $totalAmount = $doctorFee + $medicineFee;

        // 5. Simpan data tagihan ke database
        $billing = Billing::create([
            'appointment_id' => $request->appointment_id,
            'patient_id'     => $appointment->patient_id,
            'doctor_fee'     => $doctorFee,
            'medicine_fee'   => $medicineFee,
            'total_amount'   => $totalAmount,
            'guarantor'      => $request->guarantor,
            'payment_status' => ($request->guarantor === 'BPJS') ? 'covered_by_bpjs' : 'unpaid'
        ]);

        // 6. Perbarui status pembayaran pada antrean
        $appointment->update([
            'payment_status' => ($request->guarantor === 'BPJS') ? 'paid' : 'pending_payment'
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tagihan berhasil dibuat, resep diproses, dan stok obat terpotong otomatis (FEFO).',
            'data'    => $billing
        ], 201);
    }

    /**
     * Memperbarui status pembayaran tagihan (Misal: unpaid → paid)
     */
    public function update(Request $request, $id)
    {
        $billing = Billing::find($id);

        if (!$billing) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data tagihan tidak ditemukan.'
            ], 404);
        }

        $request->validate([
            'payment_status' => 'required|string|in:unpaid,paid,cancelled',
            'payment_method' => 'sometimes|string'
        ]);

        $billing->update($request->only(['payment_status', 'payment_method']));

        // Jika tagihan lunas, update juga status pembayaran di appointment
        if ($request->payment_status === 'paid') {
            Appointment::where('_id', $billing->appointment_id)->update([
                'payment_status' => 'paid'
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Status tagihan berhasil diperbarui',
            'data'    => $billing->fresh()
        ], 200);
    }
}