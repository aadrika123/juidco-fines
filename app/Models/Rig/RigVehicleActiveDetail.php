<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigVehicleActiveDetail extends Model
{
    use HasFactory;


    /**
     * | Save rig active Details 
     */
    public function saverigDetails($req, $applicationId)
    {
        $mRigActiveDetail = new RigVehicleActiveDetail();
        $mRigActiveDetail->application_id           = $applicationId;
        $mRigActiveDetail->sex                      = $req->driverGender;
        $mRigActiveDetail->dob                      = $req->driverBirthDate;
        $mRigActiveDetail->driver_name              = $req->driverName;
        $mRigActiveDetail->vehicle_name             = $req->vehicleComapny;
        $mRigActiveDetail->vehicle_from             = $req->vehicleFrom;
        $mRigActiveDetail->vehicle_no               = $req->registrationNumber;
        $mRigActiveDetail->save();
    }


    /**
     * | Get Rig details by applicationId
     */
    public function getrigDetailsByApplicationId($applicationId)
    {
        return RigVehicleActiveDetail::where('application_id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('id');
    }


    /**
     * | Update the Status of Rig details 
     */
    public function updateRigStatus($id, $refDetails)
    {
        RigVehicleActiveDetail::where('id', $id)
            ->where('status', 1)
            ->update($refDetails);
    }

    // /**
    //  * | Get Rig details by applicationId
    //  */
    // public function getRigDetailsByApplicationId($applicationId)
    // {
    //     return RigVehicleActiveDetail::where('application_id', $applicationId)
    //         ->where('status', 1)
    //         ->orderByDesc('id');
    // }

    /**
     * | Update the pet details according to id
     */
    public function updateRigDetails($req, $rigDetails)
    {
        RigVehicleActiveDetail::where('id', $rigDetails->id)
            ->update([
              
                "vehicle_name"                  => $req->vehicleComapny            ?? $rigDetails->vehicle_name,
                "vehicle_name"                  => $req->vehicleComapny            ?? $rigDetails->vehicle_name
            ]);
    }
}
