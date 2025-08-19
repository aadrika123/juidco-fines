<?php

namespace App\Models\Master;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActiveCitizenUndercare extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = 'pgsql_master';


    public function getDetailsForUnderCare($userId, $consumerId)
    {
        return ActiveCitizenUndercare::where('citizen_id', $userId)
            ->where('swm_id', $consumerId)
            ->where('deactive_status', false)
            ->first();
    }

    /**
     * | Save caretaker Details 
     */
    public function saveCaretakeDetails($applicationId, $mobileNo, $userId)
    {
        $mActiveCitizenUndercare = new ActiveCitizenUndercare();
        $mActiveCitizenUndercare->challan_id                = $applicationId;
        $mActiveCitizenUndercare->date_of_attachment    = Carbon::now();
        $mActiveCitizenUndercare->mobile_no             = $mobileNo;
        $mActiveCitizenUndercare->citizen_id            = $userId;
        $mActiveCitizenUndercare->save();
    }

    public function getDetailsByCitizenId($request)
    {
        $user = authUser($request); // Assuming you have a method to get the authenticated user
        // $user = 36;
        if (!$user) {
            throw new Exception("User Details Not found!");
        }
        return ActiveCitizenUndercare::where('citizen_id', $user->id)
            ->where('deactive_status', false)
            ->get();
    }
}
