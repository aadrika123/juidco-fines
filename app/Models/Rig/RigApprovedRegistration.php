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
            ->join('rig_approve_active_details', 'rig_approve_active_details.application_id', 'rig_approved_registrations.application_id')
            ->join('rig_active_registrations','rig_active_registrations.id','rig_approved_registrations.application_id');
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

     /**
     * | Get Approved Application by applicationId
     */
    public function getRigApprovedApplicationById($registrationId)
    {
        return RigApprovedRegistration::select(
            DB::raw("REPLACE(rig_approved_registrations.application_type, '_', ' ') AS ref_application_type"),
            'rig_approved_registrations.id as approve_id',
            'rig_approve_active_details.id as ref_rig_id',
            'rig_approve_applicants.id as ref_applicant_id',
            'rig_active_registrations.id',
            "rig_approved_registrations.application_no",
            "rig_approved_registrations.application_apply_date",
            "rig_approved_registrations.address",
            "rig_approved_registrations.application_type",
            "rig_active_registrations.payment_status",
            "rig_approved_registrations.status",
            "rig_approved_registrations.registration_id",
            "rig_approved_registrations.parked",
            "rig_approved_registrations.doc_upload_status",
            "rig_approved_registrations.registration_id",
            "rig_approved_registrations.doc_verify_status",
            "rig_approved_registrations.approve_date",
            "rig_approved_registrations.approve_end_date",
            "rig_approved_registrations.doc_verify_status",
            'rig_approve_active_details.*',
            'rig_approve_applicants.*',
            'rig_approved_registrations.status as registrationStatus',
            'rig_approve_active_details.status as Status',
            'rig_approve_applicants.status as applicantsStatus',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
         
            DB::raw("CASE 
            WHEN rig_approve_active_details.sex = '1' THEN 'Male'
            WHEN rig_approve_active_details.sex = '2' THEN 'Female'
            END AS ref_gender"),
        )
            ->join('ulb_masters', 'ulb_masters.id', 'rig_approved_registrations.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'rig_approved_registrations.ward_id')
            ->join('rig_approve_applicants', 'rig_approve_applicants.application_id', 'rig_approved_registrations.application_id')
            ->join('rig_approve_active_details', 'rig_approve_active_details.application_id', 'rig_approved_registrations.application_id')
            ->join('rig_active_registrations','rig_active_registrations.id','rig_approved_registrations.application_id')
            ->where('rig_approved_registrations.application_id', $registrationId);
    }
}
