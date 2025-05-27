<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Itemset extends Model
{
    use HasFactory;

    protected $fillable = [
        'items',
        'items_hash',
        'item_count',
        'support_count',
        'support_value',
        'apriori_batch_id',
    ];

    // Cast 'items' ke array/collection saat diakses
    protected $casts = [
        'items' => 'array',
    ];
}