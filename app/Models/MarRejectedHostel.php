<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarRejectedHostel extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = "pgsql_advertisements";
}
