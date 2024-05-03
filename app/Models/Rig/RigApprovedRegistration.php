<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RigApprovedRegistration extends Model
{
    use HasFactory;

    /**
     * | Get the approve application details using 
     */
    public function getApproveAppByRegId($id)
    {
        return RigApprovedRegistration::where('registration_id', $id)
            ->orderByDesc('id');
    }

    /**
     * | Update the Approve application Detials
     */
    public function updateApproveAppStatus($id, $refDetails)
    {
        RigApprovedRegistration::where('id', $id)
            ->update($refDetails);
    }
    /**
     * | Get the approve application details using 
     */
    public function getApproveAppByAppId($id)
    {
        return RigApprovedRegistration::where('rig_approved_registrations.application_id', $id)
            ->orderByDesc('id');
    }

    /**
     * | Get the approved application details by id
     */
    public function getApplictionByRegId($id)
    {
        return RigApprovedRegistration::select(
            "rig_approved_registrations.id AS approveId",
            "rig_approved_registrations.owner_type as ref_owner_type",
            "rig_approve_applicants.id AS applicantId",
            "rig_approve_active_details.id AS rigId",
            "rig_approved_registrations.*",
            "rig_approve_applicants.*",
            "rig_approve_active_details.*"
        )
            ->join('rig_approve_applicants', 'rig_approve_applicants.application_id', 'rig_approved_registrations.application_id')
            ->join('rig_approve_active_details', 'rig_approve_active_details.application_id', 'rig_approved_registrations.application_id')
            ->where('rig_approved_registrations.id', $id)
            ->where('rig_approved_registrations.status', 1);
    }

    /**
     * | Update the related status for Approved appications
     */
    public function updateRelatedStatus($id, $refReq)
    {
        RigApprovedRegistration::where('id', $id)
            ->where('status', 1)
            ->update($refReq);
    }
/**
     * | Get all details according to key 
     */
    public function getAllApprovdApplicationDetails()
    {
        return DB::table('rig_approved_registrations')
            ->leftJoin('wf_roles', 'wf_roles.id', 'rig_approved_registrations.current_role_id')
            ->join('rig_approve_applicants', 'rig_approve_applicants.application_id', 'rig_approved_registrations.application_id')
            ->join('rig_approve_active_details', 'rig_approve_active_details.application_id', 'rig_approved_registrations.application_id');
    }

     /**
     * | Get application details according to id
     */
    public function getApproveDetailById($id)
    {
        return RigApprovedRegistration::join('ulb_masters', 'ulb_masters.id', '=', 'rig_approved_registrations.ulb_id')
            ->join('rig_approve_active_details', 'rig_approve_active_details.application_id', 'rig_approved_registrations.application_id')
            ->join('rig_approve_applicants', 'rig_approve_applicants.application_id', 'rig_approved_registrations.application_id')
            ->where('rig_approved_registrations.application_id', $id)
            ->where('rig_approved_registrations.status', '<>', 0);
    }
}
