<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigApproveApplicant extends Model
{
    use HasFactory;


    /*
    **
     * | Get approve applicant detial by application id
     */
    public function getApproveApplicant($applicationId)
    {
        return RigApproveApplicant::where('application_id', $applicationId)
            ->where('status', 1);
    }

    /**
     * | Update the approved applicant details 
     */
    public function updateAproveApplicantDetials($id, $refReq)
    {
        RigApproveApplicant::where('id', $id)
            ->update($refReq);
    }
}
