<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetTran extends Model
{
    use HasFactory;
    protected $connection = "pgsql_advertisements";
}
