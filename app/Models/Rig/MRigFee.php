<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MRigFee extends Model
{
    use HasFactory;
    /**
     * | Get fee details according to id
     */
    public function getFeeById($id)
    {
        return MRigFee::where('id', $id)
            ->where('status', 1)
            ->first();
    }
}
