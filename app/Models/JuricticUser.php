<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JuricticUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'inn'
    ];
}
