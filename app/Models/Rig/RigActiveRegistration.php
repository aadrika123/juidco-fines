<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class RigActiveRegistration extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function saveRegistration($req, $user)
    {
        $userType = Config::get("rig.REF_USER_TYPE");
        $mrigActiveRegistration = new RigActiveRegistration();

        $mrigActiveRegistration->renewal                = $req->isRenewal ?? 0;
        $mrigActiveRegistration->registration_id        = $req->registrationId ?? null;

        $mrigActiveRegistration->application_no         = $req->applicationNo;
        $mrigActiveRegistration->address                = $req->address;

        $mrigActiveRegistration->workflow_id            = $req->workflowId;
        $mrigActiveRegistration->initiator_role_id      = $req->initiatorRoleId;
        $mrigActiveRegistration->finisher_role_id       = $req->finisherRoleId;
        $mrigActiveRegistration->current_role_id        = $req->initiatorRoleId;
        $mrigActiveRegistration->ip_address             = $req->ip();
        $mrigActiveRegistration->ulb_id                 = $req->ulbId;
        $mrigActiveRegistration->ward_id                = $req->ward;

        $mrigActiveRegistration->application_type       = $req->applicationType;                    // type new or renewal
        // $mrigActiveRegistration->occurrence_type_id     = $req->rigFrom;
        $mrigActiveRegistration->apply_through          = $req->applyThrough;                       // holding or saf
        $mrigActiveRegistration->owner_type             = $req->ownerCategory;
        $mrigActiveRegistration->application_type_id    = $req->applicationTypeId;

        $mrigActiveRegistration->created_at             = Carbon::now();
        $mrigActiveRegistration->application_apply_date = Carbon::now();

        $mrigActiveRegistration->holding_no             = $req->holdingNo ?? null;
        $mrigActiveRegistration->saf_no                 = $req->safNo ?? null;
        // $mrigActiveRegistration->rig_type               = $req->rigType;
        $mrigActiveRegistration->user_type              = $user->user_type;

        if ($user->user_type == $userType['1']) {
            $mrigActiveRegistration->apply_mode = "ONLINE";                                     // Static
            $mrigActiveRegistration->citizen_id = $user->id;
        } else {
            $mrigActiveRegistration->apply_mode = $user->user_type;
            $mrigActiveRegistration->user_id    = $user->id;
        }

        $mrigActiveRegistration->save();
        return [
            "id" => $mrigActiveRegistration->id,
            "applicationNo" => $req->applicationNo
        ];
    }

    /**
     * | Get application details by Id
     */
    public function getApplicationDtls($appId)
    {
        return RigActiveRegistration::select('*')
            ->where('id', $appId);
    }

    /**
     * | Deactivate the doc Upload Status 
     */
    public function updateUploadStatus($applicationId, $status)
    {
        return  RigActiveRegistration::where('id', $applicationId)
            ->where('status', true)
            ->update([
                "doc_upload_status" => $status
            ]);
    }

    /*
    **
     * | Get all details according to key 
     */
    public function getAllApplicationDetails($value, $key)
    {
        return DB::table('rig_active_registrations')
            ->leftJoin('wf_roles', 'wf_roles.id', 'rig_active_registrations.current_role_id')
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_active_registrations.id')
            ->join('rig_vehicle_active_details', 'rig_vehicle_active_details.application_id', 'rig_active_registrations.id')
            ->where('rig_active_registrations.' . $key, $value)
            ->where('rig_active_registrations.status', 1);
    }

    /**
     * | Get Application by applicationId
     */
    public function getrigApplicationById($applicationId)
    {
        return RigActiveRegistration::select(
            'rig_active_registrations.id as ref_application_id',
            DB::raw("REPLACE(rig_active_registrations.application_type, '_', ' ') AS ref_application_type"),
            'rig_vehicle_active_details.id as ref_rig_id',
            'rig_active_applicants.id as ref_applicant_id',
            'rig_active_registrations.*',
            'rig_vehicle_active_details.*',
            'rig_active_applicants.*',
            'rig_active_registrations.status as registrationStatus',
            'rig_vehicle_active_details.status as rigStatus',
            'rig_active_applicants.status as applicantsStatus',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            DB::raw("CASE 
            WHEN rig_vehicle_active_details.sex = '1' THEN 'Male'
            WHEN rig_vehicle_active_details.sex = '2' THEN 'Female'
            END AS ref_gender"),
            'wf_roles.role_name AS roleName'
        )
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_active_registrations.id')
            ->join('rig_vehicle_active_details', 'rig_vehicle_active_details.application_id', 'rig_active_registrations.id')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'rig_active_registrations.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'rig_active_registrations.ward_id')
            ->leftjoin('wf_roles', 'wf_roles.id', 'rig_active_registrations.current_role_id')
            ->where('rig_active_registrations.id', $applicationId)
            ->where('rig_active_registrations.status', '<>', 0);
    }

    /**
     * | Get applcation detials by id 
     */
    public function getApplicationDetailsById($id)
    {
        return RigActiveRegistration::where('id', $id)
            ->where('status', 1);
    }

    /**
     * | Save the status in Active table
     */
    public function saveApplicationStatus($applicationId, $refRequest)
    {
        RigActiveRegistration::where('id', $applicationId)
            ->update($refRequest);
    }

    /**
     * | Get active application by registration id 
     */
    public function getApplicationByRegId($regstrationId)
    {
        return RigActiveRegistration::where('registration_id', $regstrationId)
            ->where('status', 1);
    }

    /**
     * | Get application details by id
     */
    public function getApplicationById($id)
    {
        return RigActiveRegistration::join('ulb_masters', 'ulb_masters.id', 'rig_active_registrations.ulb_id')
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_active_registrations.id')
            ->join('rig_vehicle_active_details', 'rig_vehicle_active_details.application_id', 'rig_active_registrations.id')
            ->where('rig_active_registrations.id', $id)
            ->where('rig_active_registrations.status', 1);
    }

    /**
     * | Get Application details according to the related details in request 
     */
    public function getActiveApplicationDetails($req, $key, $refNo)
    {
        return RigActiveRegistration::select(
            'rig_active_registrations.id',
            'rig_active_registrations.application_no',
            'rig_active_registrations.application_type',
            'rig_active_registrations.payment_status',
            'rig_active_registrations.application_apply_date',
            'rig_active_registrations.doc_upload_status',
            'rig_active_registrations.renewal',
            'rig_active_applicants.mobile_no',
            'rig_active_applicants.applicant_name',
            DB::raw("CASE 
            WHEN rig_active_registrations.status= '1' THEN 'Pending'
            WHEN rig_active_registrations.status = '2' THEN 'Approve'
            WHEN rig_active_registrations.status = '0' THEN 'Rejected'
            END AS application_status"),
        )
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_active_registrations.id')
            ->where('rig_active_registrations.' . $key, 'LIKE', '%' . $refNo . '%')
            ->where('rig_active_registrations.status', '<>',0)
            ->where('rig_active_registrations.ulb_id', authUser($req)->ulb_id)
            ->orderByDesc('rig_active_registrations.id');
    }

    public function recentApplication($workflowIds, $roleId, $ulbId)
    {
        $data =  RigActiveRegistration::select(
            'rig_active_registrations.id as ref_application_id',
            DB::raw("REPLACE(rig_active_registrations.application_type, '_', ' ') AS ref_application_type"),
            'rig_vehicle_active_details.id as ref_rig_id',
            'rig_active_applicants.id as ref_applicant_id',
            'rig_active_registrations.*',
            'rig_vehicle_active_details.*',
            'rig_active_applicants.*',
            'rig_active_registrations.status as registrationStatus',
            'rig_vehicle_active_details.status as petStatus',
            'rig_active_applicants.status as applicantsStatus',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',

            
            DB::raw("CASE 
            WHEN rig_vehicle_active_details.sex = '1' THEN 'Male'
            WHEN rig_vehicle_active_details.sex = '2' THEN 'Female'
            END AS ref_gender"),
            'wf_roles.role_name AS roleName'
        )
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_active_registrations.id')
            ->join('rig_vehicle_active_details', 'rig_vehicle_active_details.application_id', 'rig_active_registrations.id')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'rig_active_registrations.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'rig_active_registrations.ward_id')
            ->leftjoin('wf_roles', 'wf_roles.id', 'rig_active_registrations.current_role_id')
            ->whereIn('workflow_id', $workflowIds)
            ->where('rig_active_registrations.ulb_id', $ulbId)
            ->whereIn('current_role_id', $roleId)
            ->where('rig_active_registrations.status', 1)
            ->orderBydesc('rig_active_registrations.id')
            ->take(10)
            ->get();
        $application = collect($data)->map(function ($value) {
            $value['applyDate'] = (Carbon::parse($value['applydate']))->format('d-m-Y');
            return $value;
        });
        return $application;
    }


    public function recentApplicationJsk($userId, $ulbId)
    {
        $data =  RigActiveRegistration::select(
            'rig_active_registrations.id as ref_application_id',
            DB::raw("REPLACE(rig_active_registrations.application_type, '_', ' ') AS ref_application_type"),
            'rig_vehicle_active_details.id as ref_rig_id',
            'rig_active_applicants.id as ref_applicant_id',
            'rig_active_registrations.*',
            'rig_vehicle_active_details.*',
            'rig_active_applicants.*',
            'rig_active_registrations.status as registrationStatus',
            'rig_vehicle_active_details.status as petStatus',
            'rig_active_applicants.status as applicantsStatus',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
    
            DB::raw("CASE 
            WHEN rig_vehicle_active_details.sex = '1' THEN 'Male'
            WHEN rig_vehicle_active_details.sex = '2' THEN 'Female'
            END AS ref_gender"),
            'wf_roles.role_name AS roleName'
        )
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_active_registrations.id')
            ->join('rig_vehicle_active_details', 'rig_vehicle_active_details.application_id', 'rig_active_registrations.id')

            ->join('ulb_masters', 'ulb_masters.id', '=', 'rig_active_registrations.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'rig_active_registrations.ward_id')
            ->leftjoin('wf_roles', 'wf_roles.id', 'rig_active_registrations.current_role_id')
            ->where('rig_active_registrations.ulb_id', $ulbId)
            ->where('rig_active_registrations.user_id', $userId)
            ->where('rig_active_registrations.status', "<>" , 0)
            ->orderBydesc('rig_active_registrations.id')
            ->take(10)
            ->get();
        $application = collect($data)->map(function ($value) {
            $value['applyDate'] = (Carbon::parse($value['applydate']))->format('d-m-Y');
            return $value;
        });
        return $application;
    }

    public function pendingApplicationCount()
    {
        $data =  RigActiveRegistration::select(
            DB::raw('count(rig_active_registrations.id) as total_pending_application')
        )
            ->where('rig_active_registrations.status', 1)
            ->first();

        return $data;
    }

    public function approvedApplicationCount()
    {
        $data =  RigApprovedRegistration::select(
            DB::raw('count(rig_approved_registrations.id) as total_approved_application')
        )
            ->where('rig_approved_registrations.status', 1)
            ->first();

        return $data;
    }

}
