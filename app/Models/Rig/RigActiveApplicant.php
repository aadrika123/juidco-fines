<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigActiveApplicant extends Model
{
    use HasFactory;



    /**
     * | Save the rig  applicants details  
     */
    public function saveApplicants($req, $applicaionId)
    {
        $mRigActiveApplicant = new RigActiveApplicant();
        $mRigActiveApplicant->mobile_no         = $req->mobileNo;
        $mRigActiveApplicant->email             = $req->email;
        $mRigActiveApplicant->pan_no            = $req->panNo;
        $mRigActiveApplicant->applicant_name    = $req->applicantName;
        $mRigActiveApplicant->uid               = $req->uid ?? null;
        $mRigActiveApplicant->telephone         = $req->telephone;
        $mRigActiveApplicant->owner_type        = $req->ownerCategory;
        $mRigActiveApplicant->application_id    = $applicaionId;
        $mRigActiveApplicant->save();
    }

    /**
     * | Get Details of owner by ApplicationId
     */
    public function getApplicationDetails($applicationId)
    {
        return RigActiveApplicant::where('application_id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('id');
    }


    /**
     * | Deletet the active applicant Detials 
        | CAUTION
     */
    public function updateApplicantDetials($id, $refReq)
    {
        RigActiveApplicant::where('id', $id)
            ->where('status', 1)
            ->update($refReq);
    }
}
