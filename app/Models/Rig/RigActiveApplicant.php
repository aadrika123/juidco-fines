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

    /**
     * | Get active application details according to related details 
     */
    public function getRelatedApplicationDetails($req, $key, $refNo)
    {
        return RigActiveApplicant::select(
            'rig_active_registrations.id',
            'rig_active_registrations.application_no',
            'rig_active_registrations.application_type',
            'rig_active_registrations.payment_status',
            'rig_active_registrations.application_apply_date',
            'rig_active_registrations.doc_upload_status',
            'rig_active_registrations.renewal',
            'rig_active_applicants.mobile_no',
            'rig_active_applicants.applicant_name',
        )
            ->join('rig_active_registrations', 'rig_active_registrations.id', 'rig_active_applicants.application_id')
            ->where('rig_active_applicants.' . $key, 'LIKE', '%' . $refNo . '%')
            ->where('rig_active_registrations.status', 1)
            ->where('rig_active_registrations.ulb_id', authUser($req)->ulb_id)
            ->orderByDesc('rig_active_registrations.id');
    }
}
