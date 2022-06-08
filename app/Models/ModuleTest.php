<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleTest extends Model
{
    use HasFactory;

    protected $casts = [
        'test' => 'array'
    ];
}
