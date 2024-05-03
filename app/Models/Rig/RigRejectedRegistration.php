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
            ->join('rig_vehicle_rejected_details', 'rig_vehicle_rejected_details.application_id', 'rig_rejected_registrations.application_id');
    }
}
