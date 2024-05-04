<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigTranDetail extends Model
{
    use HasFactory;
    /**
     * | Save the trans details  
     */
    public function saveTransDetails($tranId, $refReq)
    {
        $mPetTranDetail = new RigTranDetail();
        $mPetTranDetail->tran_id        = $tranId;
        $mPetTranDetail->application_id = $refReq['id'];
        $mPetTranDetail->charge_id      = $refReq['refChargeId'];
        $mPetTranDetail->total_demand   = $refReq['roundAmount'];
        $mPetTranDetail->payment_for    = $refReq['tranTypeId'];
        $mPetTranDetail->save();
    }
}
