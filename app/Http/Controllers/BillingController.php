<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Billing;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use Illuminate\Http\Request;

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
            'status' => 'success',
            'message' => 'Daftar tagihan pembayaran berhasil dimuat',
            'data' => $billings,
        ], 200);
    }

    /**
     * Menampilkan detail satu tagihan berdasarkan ID
     */
    public function show($id)
    {
        $billing = Billing::with(['appointment', 'patient'])->find($id);

        if (! $billing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tagihan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail tagihan berhasil dimuat',
            'data' => $billing,
        ], 200);
    }

    /**
     * Membuat tagihan kasir pasien.
     * Stok obat BELUM dipotong pada tahap ini jika status masih unpaid (mencegah stok hilang jika pasien batal).
     * Jika penjamin BPJS, biaya ditanggung dan resep langsung diproses (potong stok FEFO).
     */
    public function store(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string',
            'doctor_fee' => 'sometimes|nullable|numeric|min:0',
            'guarantor' => 'required|string', // Umum, BPJS, Asuransi, dll
        ]);

        // 1. Validasi keberadaan antrean (appointment)
        $appointment = Appointment::with('doctor')->find($request->appointment_id);
        if (! $appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data antrean (appointment) tidak ditemukan.',
            ], 404);
        }

        if ($appointment->status === 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment harus dikonfirmasi dan diselesaikan sebelum tagihan dibuat.',
            ], 422);
        }

        // 2. Cegah pembuatan tagihan ganda untuk antrean yang sama
        $existingBilling = Billing::where('appointment_id', $request->appointment_id)->first();
        if ($existingBilling) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan untuk antrean ini sudah pernah dibuat sebelumnya.',
                'data' => $existingBilling,
            ], 422);
        }

        // 3. Ambil data Rekam Medis (SOAP) untuk kalkulasi estimasi biaya obat
        $medicalRecord = MedicalRecord::where('appointment_id', $request->appointment_id)->first();
        $medicineFee = 0;

        if ($medicalRecord && ! empty($medicalRecord->prescriptions)) {
            foreach ($medicalRecord->prescriptions as $item) {
                $medicineId = $item['medicine_id'] ?? $item['id'] ?? null;
                $qtyNeeded = $item['quantity'] ?? $item['qty'] ?? 1;

                if (! $medicineId) {
                    continue;
                }

                $medicine = Medicine::find($medicineId);
                if (! $medicine) {
                    continue;
                }

                $unitPrice = $medicine->base_price ?? $medicine->unit_price ?? 0;
                $medicineFee += $qtyNeeded * $unitPrice;
            }
        }

        // 4. Penentuan Tarif Jasa Dokter (Gunakan tarif default dokter jika tidak diinput manual)
        $isBpjs = ($request->guarantor === 'BPJS');
        $defaultDoctorFee = 50000;
        if ($appointment->doctor && isset($appointment->doctor->consultation_fee)) {
            $defaultDoctorFee = (float) $appointment->doctor->consultation_fee;
        }

        $inputDoctorFee = $request->filled('doctor_fee') ? (float) $request->doctor_fee : $defaultDoctorFee;
        $doctorFee = $isBpjs ? 0 : $inputDoctorFee;
        $finalMedicineFee = $isBpjs ? 0 : $medicineFee;
        $totalAmount = $doctorFee + $finalMedicineFee;
        $stockDeducted = false;

        // Jika BPJS, status otomatis dicover dan stok obat langsung dipotong via FEFO
        if ($isBpjs) {
            $this->deductFefoStock($medicalRecord);
            $stockDeducted = true;
        }

        // 5. Simpan data tagihan ke database
        $billing = Billing::create([
            'appointment_id' => $request->appointment_id,
            'patient_id' => $appointment->patient_id,
            'doctor_fee' => $doctorFee,
            'medicine_fee' => $finalMedicineFee,
            'total_amount' => $totalAmount,
            'guarantor' => $request->guarantor,
            'payment_status' => $isBpjs ? 'covered_by_bpjs' : 'unpaid',
            'stock_deducted' => $stockDeducted,
        ]);

        // 6. Perbarui HANYA payment_status pada antrean (status klinis tetap tidak berubah)
        $appointment->update([
            'payment_status' => $isBpjs ? 'paid' : 'pending_payment',
        ]);

        $msg = $isBpjs
            ? 'Tagihan berhasil dibuat, klaim BPJS disetujui, dan stok obat diproses via FEFO.'
            : 'Tagihan berhasil dibuat. Stok obat akan terpotong secara otomatis saat pembayaran diselesaikan (paid).';

        return response()->json([
            'status' => 'success',
            'message' => $msg,
            'data' => $billing,
        ], 201);
    }

    /**
     * Memperbarui status pembayaran tagihan (unpaid → paid / cancelled).
     * Saat tagihan lunas (paid), pemotongan stok FEFO otomatis dieksekusi.
     * Jika tagihan dibatalkan (cancelled), stok dikembalikan jika sebelumnya sudah terpotong.
     */
    public function update(Request $request, $id)
    {
        $billing = Billing::find($id);

        if (! $billing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tagihan tidak ditemukan.',
            ], 404);
        }

        $request->validate([
            'payment_status' => 'required|string|in:unpaid,paid,cancelled',
            'payment_method' => 'sometimes|string',
        ]);

        $newPaymentStatus = $request->payment_status;
        $medicalRecord = MedicalRecord::where('appointment_id', $billing->appointment_id)->first();

        // 1. Jika status diubah menjadi PAID dan stok belum pernah dipotong:
        if ($newPaymentStatus === 'paid' && ! $billing->stock_deducted) {
            $this->deductFefoStock($medicalRecord);
            $billing->stock_deducted = true;
        }

        // 2. Jika status diubah menjadi CANCELLED dan stok sudah terlanjur dipotong:
        if ($newPaymentStatus === 'cancelled' && $billing->stock_deducted) {
            $this->restoreFefoStock($medicalRecord);
            $billing->stock_deducted = false;
        }

        $billing->payment_status = $newPaymentStatus;
        if ($request->filled('payment_method')) {
            $billing->payment_method = $request->payment_method;
        }
        $billing->save();

        // 3. Update status pembayaran antrean (status klinis appointment tetap terjaga)
        Appointment::where('_id', $billing->appointment_id)->update([
            'payment_status' => $newPaymentStatus,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status tagihan berhasil diperbarui',
            'data' => $billing->fresh(),
        ], 200);
    }

    /**
     * Helper: Memotong stok obat dari batch terdekat kedaluwarsa (FEFO)
     */
    protected function deductFefoStock(?MedicalRecord $medicalRecord): void
    {
        if (! $medicalRecord || empty($medicalRecord->prescriptions)) {
            return;
        }

        foreach ($medicalRecord->prescriptions as $item) {
            $medicineId = $item['medicine_id'] ?? $item['id'] ?? null;
            $qtyNeeded = (int) ($item['quantity'] ?? $item['qty'] ?? 1);

            if (! $medicineId) {
                continue;
            }

            $medicine = Medicine::find($medicineId);
            if (! $medicine || empty($medicine->batches)) {
                continue;
            }

            // Urutkan batch berdasarkan exp_date terdekat (FEFO)
            $batches = collect($medicine->batches)->sortBy('exp_date')->values()->toArray();
            $remainingQty = $qtyNeeded;

            foreach ($batches as &$batch) {
                if ($remainingQty <= 0) {
                    break;
                }

                if ($batch['stock'] > 0) {
                    if ($batch['stock'] >= $remainingQty) {
                        $batch['stock'] -= $remainingQty;
                        $remainingQty = 0;
                    } else {
                        $remainingQty -= $batch['stock'];
                        $batch['stock'] = 0;
                    }
                }
            }

            $medicine->update(['batches' => $batches]);
        }
    }

    /**
     * Helper: Mengembalikan stok obat ke batch jika tagihan dibatalkan (Rollback)
     */
    protected function restoreFefoStock(?MedicalRecord $medicalRecord): void
    {
        if (! $medicalRecord || empty($medicalRecord->prescriptions)) {
            return;
        }

        foreach ($medicalRecord->prescriptions as $item) {
            $medicineId = $item['medicine_id'] ?? $item['id'] ?? null;
            $qtyToRestore = (int) ($item['quantity'] ?? $item['qty'] ?? 1);

            if (! $medicineId) {
                continue;
            }

            $medicine = Medicine::find($medicineId);
            if (! $medicine || empty($medicine->batches)) {
                continue;
            }

            $batches = $medicine->batches;
            if (! empty($batches)) {
                $batches[0]['stock'] += $qtyToRestore;
                $medicine->update(['batches' => $batches]);
            }
        }
    }
}
