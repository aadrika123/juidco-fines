<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RigRenewalRegistration extends Model
{
    use HasFactory;


    /**
     * | Get Rig renewal application details by id
     */
    public function getRenewalApplicationById($id)
    {
        return RigRenewalRegistration::join('ulb_masters', 'ulb_masters.id', '=', 'rig_renewal_registrations.ulb_id')
            ->join('rig_renewal_applicants', 'rig_renewal_applicants.application_id', 'rig_renewal_registrations.application_id')
            ->where('rig_renewal_registrations.application_id', $id)
            ->where('rig_renewal_registrations.status', '<>', 0);
    }

    /**
     * | Get Rig renewal application list by registration id
     */
    public function getRenewalApplicationByRegId($regId)
    {
        return RigRenewalRegistration::join('ulb_masters', 'ulb_masters.id', '=', 'rig_renewal_registrations.ulb_id')
            ->join('rig_renewal_applicants', 'rig_renewal_applicants.application_id', 'rig_renewal_registrations.application_id')
            ->where('rig_renewal_registrations.registration_id', $regId)
            ->where('rig_renewal_registrations.status', '<>', 0);
    }

    /**
     * | Get Renewal Application details by applicationId
     */
    public function getPetRenewalApplicationById($registrationId)
    {
        return RigRenewalRegistration::select(
            DB::raw("REPLACE(rig_renewal_registrations.application_type, '_', ' ') AS ref_application_type"),
            'rig_renewal_registrations.id as rejected_id',
            'rig_renewal_details.id as ref_rig_id',
            'rig_renewal_applicants.id as ref_applicant_id',
            'rig_renewal_registrations.*',
            'rig_renewal_details.*',
            'rig_renewal_applicants.*',
            'rig_renewal_registrations.status as registrationStatus',
            'rig_renewal_details.status as rigStatus',
            'rig_renewal_applicants.status as applicantsStatus',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            DB::raw("CASE 
            WHEN rig_renewal_details.sex = '1' THEN 'Male'
            WHEN rig_renewal_details.sex = '2' THEN 'Female'
            END AS ref_gender"),
        )
            ->join('ulb_masters', 'ulb_masters.id', 'rig_renewal_registrations.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'rig_renewal_registrations.ward_id')
            ->join('rig_renewal_applicants', 'rig_renewal_applicants.application_id', 'rig_renewal_registrations.application_id')
            ->join('rig_renewal_details', 'rig_renewal_details.application_id', 'rig_renewal_registrations.application_id')
            ->where('rig_renewal_registrations.id', $registrationId);
    }
}
