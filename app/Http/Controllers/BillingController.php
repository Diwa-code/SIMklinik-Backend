<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Billing;

class BillingController extends Controller
{
    // Membuat tagihan baru berdasarkan kunjungan
    public function store(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string',
            'patient_id' => 'required|string',
            'consultation_fee' => 'required|numeric',
            'action_fee' => 'required|numeric',
            'medicine_fee' => 'required|numeric',
            'payment_method' => 'required|string' // cash, transfer, asuransi
        ]);

        // Kalkulasi otomatis grand total
        $grandTotal = $request->consultation_fee + $request->action_fee + $request->medicine_fee;

        $billing = Billing::create([
            'appointment_id' => $request->appointment_id,
            'patient_id' => $request->patient_id,
            'consultation_fee' => $request->consultation_fee,
            'action_fee' => $request->action_fee,
            'medicine_fee' => $request->medicine_fee,
            'grand_total' => $grandTotal,
            'payment_status' => 'paid', // Diubah menjadi paid setelah dilunasi
            'payment_method' => $request->payment_method
        ]);

        return response()->json([
            'message' => 'Tagihan Pembayaran Berhasil Dibuat',
            'data' => $billing
        ], 201);
    }
}