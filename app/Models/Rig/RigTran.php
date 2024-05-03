<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigTran extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        return RigTran::create($req);
    }
    /**
     * | Get transaction by application No
     */
    public function getTranByApplicationId($applicationId)
    {
        return RigTran::where('related_id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('id');
    }
}
