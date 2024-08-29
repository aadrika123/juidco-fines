<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigChequeDtl extends Model
{
    use HasFactory;


    /**
     * | Save the cheque details 
     */
    public function postChequeDtl($req)
    {
        $mPetChequeDtl = new RigChequeDtl();
        $mPetChequeDtl->application_id     =  $req['application_id'] ?? null;
        $mPetChequeDtl->transaction_id     =  $req['transaction_id'];
        $mPetChequeDtl->cheque_date        =  $req['cheque_date'];
        $mPetChequeDtl->bank_name          =  $req['bank_name'];
        $mPetChequeDtl->branch_name        =  $req['branch_name'];
        $mPetChequeDtl->cheque_no          =  $req['cheque_no'];
        $mPetChequeDtl->user_id            =  $req['user_id'];
        $mPetChequeDtl->save();
    }

    /**
     * | Get cheque details by trans id
     */
    public function getDetailsByTranId($tranId)
    {
        return RigChequeDtl::where('transaction_id', $tranId)
            ->where('status', 1);
    }
     /**
     * | Get data of cheque details 
     */
    public function chequeDtlById($request)
    {
        return RigChequeDtl::select('*')
            ->where('id', $request->chequeId)
            // ->where('workflow_id', $request->workflowId)
            ->where('status', 2)
            ->first();
    }
}
