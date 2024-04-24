<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenaltyDailycollectiondetail extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        return PenaltyDailycollectiondetail::create($req);
    }
}
