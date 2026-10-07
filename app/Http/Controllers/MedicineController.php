<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medicine;

class MedicineController extends Controller
{
    /**
     * Menampilkan daftar inventaris obat di apotek
     */
    public function index()
    {
        $medicines = Medicine::orderBy('name')->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar inventaris obat berhasil dimuat',
            'data'    => $medicines
        ], 200);
    }

    /**
     * Menampilkan detail satu data obat
     */
    public function show($id)
    {
        $medicine = Medicine::find($id);

        if (!$medicine) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data obat tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail obat berhasil dimuat',
            'data'    => $medicine
        ], 200);
    }

    /**
     * Menambah data obat baru beserta batches-nya (Hanya untuk Admin / Apoteker)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'                 => 'required|string',
            'category'             => 'required|string',
            'base_price'           => 'required|numeric|min:0',
            'batches'              => 'required|array|min:1',
            'batches.*.batch_no'   => 'required|string',
            'batches.*.stock'      => 'required|integer|min:0',
            'batches.*.exp_date'   => 'required|date'
        ]);

        $medicine = Medicine::create([
            'name'       => $request->name,
            'category'   => $request->category,
            'base_price' => $request->base_price,
            'batches'    => $request->batches
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Obat baru dengan manajemen batch FEFO berhasil ditambahkan',
            'data'    => $medicine
        ], 201);
    }

    /**
     * Memperbarui data obat atau menambah/mengubah batches (Hanya untuk Admin / Apoteker)
     */
    public function update(Request $request, $id)
    {
        $medicine = Medicine::find($id);

        if (!$medicine) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data obat tidak ditemukan.'
            ], 404);
        }

        $request->validate([
            'name'                 => 'sometimes|string',
            'category'             => 'sometimes|string',
            'base_price'           => 'sometimes|numeric|min:0',
            'batches'              => 'sometimes|array|min:1',
            'batches.*.batch_no'   => 'required_with:batches|string',
            'batches.*.stock'      => 'required_with:batches|integer|min:0',
            'batches.*.exp_date'   => 'required_with:batches|date'
        ]);

        // Update field yang dikirimkan
        $medicine->update($request->only([
            'name', 'category', 'base_price', 'batches'
        ]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Data obat dan manajemen batch berhasil diperbarui',
            'data'    => $medicine->fresh()
        ], 200);
    }

    /**
     * Menghapus data obat dari inventaris (Hanya untuk Admin)
     */
    public function destroy($id)
    {
        $medicine = Medicine::find($id);

        if (!$medicine) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data obat tidak ditemukan.'
            ], 404);
        }

        $medicine->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data obat berhasil dihapus dari inventaris'
        ], 200);
    }
}