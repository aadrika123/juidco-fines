<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MMarket extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'm_market';
    protected $connection = "pgsql_advertisements";
}
