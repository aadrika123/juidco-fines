<?php

namespace App\Models\Rig;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigRegistrationCharge extends Model
{
    use HasFactory;
    /**
     * | Save the pet registration charge
        | Caution 
     */
    public function saveRegisterCharges($req)
    {
        $mRigRegistrationCharge = new RigRegistrationCharge();
        $mRigRegistrationCharge->application_id     = $req->applicationId;
        $mRigRegistrationCharge->charge_category    = $req->applicationTypeId;
        $mRigRegistrationCharge->amount             = $req->amount;
        $mRigRegistrationCharge->penalty            = 0;                                        // Static
        $mRigRegistrationCharge->registration_fee   = $req->registrationFee;
        $mRigRegistrationCharge->created_at         = Carbon::now();
        $mRigRegistrationCharge->rebate             = 0;                                        // Static
        $mRigRegistrationCharge->paid_status        = $req->refPaidstatus ?? 0;
        $mRigRegistrationCharge->charge_category_name = $req->applicationType;
        $mRigRegistrationCharge->save();
    }

    /**
     * | Get registration charges accordng to application id 
     */
    public function getChargesbyId($id)
    {
        return RigRegistrationCharge::where('application_id', $id)
            ->where('status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Save payment status for payment
     */
    public function saveStatus($id, $refRequest)
    {
        RigRegistrationCharge::where('id', $id)
            ->update($refRequest);
    }
}
