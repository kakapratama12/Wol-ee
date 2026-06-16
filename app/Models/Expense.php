<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'description',
        'amount',
        'period_month',
        'period_year',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_month' => 'integer',
        'period_year' => 'integer',
    ];
}
