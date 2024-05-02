<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigApproveActiveDetail extends Model
{
    use HasFactory;

    /**
     * | Update the approved pet details 
     */
    public function updateApprovePetStatus($id, $refReq)
    {
        RigApproveActiveDetail::where('id', $id)
            ->update($refReq);
    }
}
