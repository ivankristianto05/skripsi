<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssociationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'apriori_batch_id',
        'antecedent',
        'consequent',
        'confidence',
        'lift',
        'support_value_rule',
    ];

    protected $casts = [
        'antecedent' => 'array',
        'consequent' => 'array',
        'confidence' => 'float', // atau decimal:4
        'lift' => 'float',       // atau decimal:4
        'support_value_rule' => 'float', // atau decimal:4
    ];
}