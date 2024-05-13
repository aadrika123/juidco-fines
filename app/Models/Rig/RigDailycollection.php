<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigDailycollection extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        return RigDailycollection::create($req);
    }
}
