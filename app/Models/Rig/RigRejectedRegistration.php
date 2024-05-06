<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RigRejectedRegistration extends Model
{
    use HasFactory;

    /**
     * | Get the rejected application details using 
     */
    public function getRejectedAppByAppId($id)
    {
        return RigRejectedRegistration::where('rig_rejected_registrations.application_id', $id)
            ->orderByDesc('id');
    }

    /**
     * | Get all details according to key 
     */
    public function getAllRejectedApplicationDetails()
    {
        return DB::table('rig_rejected_registrations')
            ->leftJoin('wf_roles', 'wf_roles.id', 'rig_rejected_registrations.current_role_id')
            ->join('rig_rejected_applicants', 'rig_rejected_applicants.application_id', 'rig_rejected_registrations.application_id')
            ->join('rig_vehicle_rejected_details', 'rig_vehicle_rejected_details.application_id', 'rig_rejected_registrations.application_id')
            ->join('rig_active_registrations','rig_active_registrations.id','rig_rejected_registrations.application_id');
    }

    /**
     * | Get Rig rejected application details by id
     */
    public function getRejectedApplicationById($id)
    {
        return RigRejectedRegistration::join('ulb_masters', 'ulb_masters.id', '=', 'rig_rejected_registrations.ulb_id') 
            ->join('rig_rejected_applicants', 'rig_rejected_applicants.application_id', 'rig_rejected_registrations.application_id')
            ->where('rig_rejected_registrations.application_id', $id)
            ->where('rig_rejected_registrations.status', '<>', 0);
    }

    /**
     * | Get Rejected Application by applicationId
     */
    public function getRigRejectedApplicationById($registrationId)
    {
        return RigRejectedRegistration::select(
            DB::raw("REPLACE(rig_rejected_registrations.application_type, '_', ' ') AS ref_application_type"),
            'rig_rejected_registrations.id as rejected_id',
            'rig_vehicle_rejected_details.id as ref_rig_id',
            'rig_rejected_applicants.id as ref_applicant_id',
            'rig_rejected_registrations.*',
            'rig_vehicle_rejected_details.*',
            'rig_rejected_applicants.*',
            'rig_rejected_registrations.status as registrationStatus',
            'rig_vehicle_rejected_details.status as Status',
            'rig_rejected_applicants.status as applicantsStatus',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            DB::raw("CASE 
            WHEN rig_vehicle_rejected_details.sex = '1' THEN 'Male'
            WHEN rig_vehicle_rejected_details.sex = '2' THEN 'Female'
            END AS ref_gender"),
        )
            ->join('ulb_masters', 'ulb_masters.id', 'rig_rejected_registrations.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'rig_rejected_registrations.ward_id')
            ->join('rig_rejected_applicants', 'rig_rejected_applicants.application_id', 'rig_rejected_registrations.application_id')
            ->join('rig_vehicle_rejected_details', 'rig_vehicle_rejected_details.application_id', 'rig_rejected_registrations.application_id')
            ->where('rig_rejected_registrations.id', $registrationId);
    }


}
