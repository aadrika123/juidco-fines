<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'mar_shops';
    protected $connection = "pgsql_advertisements";
}
