<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigApproveActiveDetail extends Model
{
    use HasFactory;

    /**
     * | Update the approved pet details 
     */
    public function updateApproverigStatus($id, $refReq)
    {
        RigApproveActiveDetail::where('id', $id)
            ->update($refReq);
    }

    /**
     * | Get the application pet details by application id 
     */
    public function getRigDetailsById($applicationId)
    {
        return RigApproveActiveDetail::where('application_id', $applicationId)
            ->where('status', 1);
    }
}
