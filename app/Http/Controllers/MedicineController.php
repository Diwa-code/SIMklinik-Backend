<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medicine;

class MedicineController extends Controller
{
    // Menampilkan daftar obat dan stok batch-nya
    public function index()
    {
        $medicines = Medicine::all();
        return response()->json([
            'message' => 'Daftar Obat Berhasil Dimuat',
            'data' => $medicines
        ]);
    }

    // Menambahkan obat baru beserta batch pasokannya
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'category' => 'required|string',
            'base_price' => 'required|numeric',
            'batches' => 'required|array',
            'batches.*.batch_no' => 'required|string',
            'batches.*.stock' => 'required|integer',
            'batches.*.exp_date' => 'required|date'
        ]);

        $medicine = Medicine::create([
            'name' => $request->name,
            'category' => $request->category,
            'base_price' => $request->base_price,
            'batches' => $request->batches
        ]);

        return response()->json([
            'message' => 'Data Obat dan Batch Berhasil Disimpan',
            'data' => $medicine
        ], 201);
    }
}