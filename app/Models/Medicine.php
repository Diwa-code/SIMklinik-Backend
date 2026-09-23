<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Medicine extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'medicines';

    protected $fillable = [
        'name',
        'category',    // Contoh: Sirup, Tablet, Salep
        'base_price',  // Harga jual satuan
        'batches'      // Array berisi detail stok dan tanggal kedaluwarsa
    ];
}