<?php

namespace App\Http\Controllers\Rig;

use App\Http\Controllers\Controller;
use App\Models\Rig\RefRequiredDocument;
use App\Models\rig\rigActiveApplicant;
use App\Models\rig\rigActiveDetail;
use App\Models\rig\rigActiveRegistration;
use App\Models\Rig\RigApproveActiveDetail;
use App\Models\rig\rigApproveApplicant;
use App\Models\rig\rigApproveDetail;
use App\Models\rig\rigApprovedRegistration;
use App\Models\rig\rigRejectedRegistration;
use App\Models\rig\rigRenewalRegistration;
use App\Models\Rig\RigVehicleActiveDetail;
use App\Models\Rig\WfActiveDocument as RigWfActiveDocument;
use App\Models\WfWorkflowrolemap as ModelsWfWorkflowrolemap;
use App\Models\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfWorkflow;
use App\Models\WfWorkflowrolemap;
use App\Models\Workflows\WorkflowTrack;
use App\Pipelines\Marriage\SearchByApplicationNo as MarriageSearchByApplicationNo;
use App\Pipelines\rig\SearchByApplicationNo as rigSearchByApplicationNo;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Pipeline\Pipeline;
use App\Pipelines\rig\SearchByApplicationNo;
use  App\Models\Rig\WfActiveDocument;

class RigWorkflowController extends Controller
{
    //

    use Workflow;

    private $_masterDetails;
    private $_propertyType;
    private $_occupancyType;
    private $_workflowMasterId;
    private $_rigParamId;
    private $_rigModuleId;
    private $_userType;
    private $_rigWfRoles;
    private $_docReqCatagory;
    private $_dbKey;
    private $_fee;
    private $_applicationType;
    private $_offlineVerificationModes;
    private $_paymentMode;
    private $_offlineMode;
    protected $_DB_NAME;
    protected $_DB;
    protected $_DB_NAME2;
    protected $_DB2;
    protected $_wfroles;
    # Class constructer 
    public function __construct()
    {
        $this->_masterDetails           = Config::get("rig.MASTER_DATA");
        $this->_propertyType            = Config::get("rig.PROP_TYPE");
        $this->_occupancyType           = Config::get("rig.PROP_OCCUPANCY_TYPE");
        $this->_workflowMasterId        = Config::get("rig.WORKFLOW_MASTER_ID");
        $this->_rigParamId              = Config::get("rig.PARAM_ID");
        $this->_rigModuleId             = Config::get('rig.rig_MODULE_ID');
        $this->_userType                = Config::get("rig.REF_USER_TYPE");
        $this->_rigWfRoles              = Config::get("rig.ROLE_LABEL");
        $this->_docReqCatagory          = Config::get("rig.DOC_REQ_CATAGORY");
        $this->_dbKey                   = Config::get("rig.DB_KEYS");
        $this->_fee                     = Config::get("rig.FEE_CHARGES");
        $this->_applicationType         = Config::get("rig.APPLICATION_TYPE");
        $this->_offlineVerificationModes = Config::get("rig.VERIFICATION_PAYMENT_MODES");
        $this->_paymentMode             = Config::get("rig.PAYMENT_MODE");
        $this->_offlineMode             = Config::get("rig.OFFLINE_PAYMENT_MODE");
        $this->_wfroles                = Config::get('rig.ROLE_LABEL');
        # Database connectivity
        $this->_DB_NAME2    = "pgsql_master";
        $this->_DB2         = DB::connection($this->_DB_NAME2);
    }


    /**
     * | Database transaction connection
     */
    public function begin()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB2->getDatabaseName();
        DB::beginTransaction();
        if ($db1 != $db2)
            $this->_DB->beginTransaction();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB2->beginTransaction();
    }
    /**
     * | Database transaction connection
     */
    public function rollback()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB2->getDatabaseName();
        DB::rollBack();
        if ($db1 != $db2)
            $this->_DB->rollBack();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB2->rollBack();
    }
    /**
     * | Database transaction connection
     */
    public function commit()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB2->getDatabaseName();
        DB::commit();
        if ($db1 != $db2)
            $this->_DB->commit();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB2->commit();
    }
    /**
     * | Inbox
     * | workflow
        | Serial No :
        | Working
     */
    public function inbox(Request $request)
    {
        try {
           return $user   = authUser($request);
            $userId = $user->id;
            $ulbId  = $user->ulb_id;
            $pages  = $request->perPage ?? 10;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $msg = "Inbox List Details!";

            // $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $rigList = $this->getrigApplicatioList($workflowIds, $ulbId)
                ->whereIn('rig_active_registrations.current_role_id', $roleId)
                // ->whereIn('rig_active_registrations.ward_id', $occupiedWards)
                // ->where('rig_active_registrations.is_escalate', false)
                ->where('rig_active_registrations.parked', false)
                ->paginate($pages);

            if (collect($rigList)->last() == 0 || !$rigList) {
                $msg = "Data not found!";
            }
            // $inbox = app(Pipeline::class)
            //     ->send(
            //         $rigList
            //     )
            //     ->through([
            //         rigSearchByApplicationNo::class
            //     ])
            //     ->thenReturn()
            // ->paginate($pages);
            return responseMsgs(true, $msg, remove_null($rigList), '', '02', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $request->deviceId);
        }
    }
    /**
     * | Common function
        | Move the function in trait 
        | Caution move the function 
     */
    public function getrigApplicatioList($workflowIds, $ulbId)
    {
        return RigActiveRegistration::select(
            'rig_active_registrations.id',
            'rig_active_registrations.application_no',
            'rig_active_applicants.id as owner_id',
            'rig_active_applicants.applicant_name as owner_name',
            'rig_active_registrations.ward_id',
            'u.ward_name as ward_no',
            'rig_active_registrations.workflow_id',
            'rig_active_registrations.current_role_id as role_id',
            'rig_active_registrations.application_apply_date',
            'rig_active_registrations.parked',
            'rig_active_registrations.is_escalate'
        )
            ->join('ulb_ward_masters as u', 'u.id', '=', 'rig_active_registrations.ward_id')
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_active_registrations.id')
            ->join('rig_vehicle_active_details', 'rig_vehicle_active_details.application_id', 'rig_active_registrations.id')
            ->where('rig_active_registrations.status', 1)
            ->where('rig_active_registrations.ulb_id', $ulbId)
            ->whereIn('rig_active_registrations.workflow_id', $workflowIds)
            ->orderByDesc('rig_active_applicants.id');
    }
    /**
     * | OutBox
     * | Outbox details for display
        | Serial No :
        | Working
     */
    public function outbox(Request $req)
    {
        try {
            $user                   = authUser($req);
            $userId                 = $user->id;
            $ulbId                  = $user->ulb_id;
            $pages                  = $req->perPage ?? 10;
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();
            $msg = "Outbox List!";

            // $occupiedWards  = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId         = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds    = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $rigList = $this->getrigApplicatioList($workflowIds, $ulbId)
                ->whereNotIn('rig_active_registrations.current_role_id', $roleId)
                // ->whereIn('rig_active_registrations.ward_id', $occupiedWards)
                ->orderByDesc('rig_active_registrations.id')
                ->paginate($pages);

            if (collect($rigList)->last() == 0 || !$rigList) {
                $msg = "Data not found!";
            }
            return responseMsgs(true, $msg, remove_null($rigList), '', '01', '.ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }


    /**
     * | Get details for the rig special inbox
        | Serial No :
        | Working
     */
    public function RigSpecialInbox(Request $request)
    {
        try {
            $user   = authUser($request);
            $userId = $user->id;
            $ulbId  = $user->ulb_id;
            $pages  = $request->perPage ?? 10;
            $msg    = "Inbox List Details!";
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $rigList = $this->getrigApplicatioList($workflowIds, $ulbId)
                // ->whereIn('rig_active_registrations.ward_id', $occupiedWards)
                ->where('rig_active_registrations.is_escalate', true)
                ->paginate($pages);
            if (collect($rigList)->last() == 0 || !$rigList) {
                $msg = "Data not found!";
            }
            return responseMsgs(true, $msg, remove_null($rigList), '', '02', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $request->deviceId);
        }
    }


    /*
    **
     * | Post escalte Details of rig Application
        | Serial No :
        | Working
     */
    public function postEscalate(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "escalateStatus"    => "required|int",
                "applicationId"     => "required|int",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $userId = authUser($request)->id;
            $applicationId = $request->applicationId;
            $mRigActiveRegistration = new RigActiveRegistration();
            $applicationsData = $mRigActiveRegistration->getApplicationDetailsById($applicationId)->first();
            if (!$applicationsData) {
                throw new Exception("Application details not found!");
            }
            $applicationsData->is_escalate = $request->escalateStatus;
            $applicationsData->escalate_by = $userId;
            $applicationsData->save();
            return responseMsgs(true, $request->escalateStatus == 1 ? 'rig application is Escalated' : "rig application is removed from Escalated", [], '', "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Workflow final approvale for the application
     * | Also adjust the renewal process
        | Serial No : 
        | Parent function
        | Working
     */
    public function finalApprovalRejection(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|digits_between:1,9223372036854775807',
                'status'        => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $approveDetails         = [];
            $userId                 = authUser($request)->id;
            $applicationId          = $request->applicationId;
            $mRigActiveRegistration = new RigActiveRegistration();
            $mWfRoleUsermap         = new WfRoleusermap();

            # Get Application details 
            $application = $mRigActiveRegistration->getrigApplicationById($applicationId)->first();
            if (!$application) {
                throw new Exception("application Details not found!");
            }

            # Check the workflow role 
            $workflowId     = $application->workflow_id;
            $applicationNo  = $application->application_no;
            $getRoleReq = new Request([                                                                 // make request to get role id of the user
                'userId'        => $userId,
                'workflowId'    => $workflowId
            ]);
            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);

            # Check params 
            $this->checkParamForApproval($readRoleDtls, $application);

            DB::beginTransaction();
            # Approval of grievance application 
            if ($request->status == 1) {                                                                // Static
                # If application is approved for the first time or renewal
                if ($application->renewal == 0) {                                                       // Static
                    $approveDetails = $this->finalApproval($request, $application);
                    $returnData['uniqueTokenId'] = $approveDetails['registrationId'] ?? null;
                } else {
                    $this->finalApprovalRenewal($request, $application);
                }
                $msg = "Application Successfully Approved !!";
            }
            # Rejection of grievance application
            if ($request->status == 0) {                                                                // Static
                $this->finalRejectionOfAppication($request, $application);
                $msg = "Application Successfully Rejected !!";
            }
            DB::commit();
            $returnData["applicationNo"] = $applicationNo;
            return responseMsgs(true, $msg, $returnData, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | Check param For final approval and rejection 
        | Serial No :
        | Working
     */
    public function checkParamForApproval($readRoleDtls, $application)
    {
        if (!$readRoleDtls) {
            throw new Exception("Role details not found!");
        }
        if ($readRoleDtls->wf_role_id != $application->finisher_role_id) {
            throw new Exception("You are not the Finisher!");
        }
        // if ($application->doc_upload_status == false || $application->payment_status != 1) {
        //     throw new Exception("Document Not Fully Uploaded or Payment in not Done!");
        // }
        if ($application->doc_verify_status == false) {
            throw new Exception("Document Not Fully Verified!");
        }
    }

    /**
     * | Final approval process for rig application 
        | Serial No :
        | Working
        | Caution performing Deletion of active application
     */
    public function finalApproval($request, $applicationDetails)
    {
        $now                        = Carbon::now();
        $status                     = 2;
        $applicationId              = $request->applicationId;
        $rigTrack                 = new WorkflowTrack();
        $mRigActiveRegistration     = new RigActiveRegistration();
        $mRigApprovedRegistration   = new RigApprovedRegistration();
        $mRigActiveApplicant        = new RigActiveApplicant();
        $mRigActiveDetail           = new RigVehicleActiveDetail();
        $lastLicenceDate            = $now->copy()->addYear()->subDay();
        $key                        = "REG-";                                           // Static
        $registrationId             = $this->getUniqueId($key);


        # Check if the approve application exist
        $someDataExist = $mRigApprovedRegistration->getApproveAppByAppId($applicationId)
            ->whereNot('status', 0)
            ->first();
        if ($someDataExist) {
            throw new Exception("Approve application details exist in active table ERROR!");
        }

        # Data formating for save the consumer details 
        $refApplicationDetial   = $mRigActiveRegistration->getApplicationDetailsById($applicationId)->first();
        $refOwnerDetails        = $mRigActiveApplicant->getApplicationDetails($applicationId)->first();
        $refrigDetails          = $mRigActiveDetail->getrigDetailsByApplicationId($applicationId)->first();

        # Saving the data in the approved application table
        $approvedrigRegistration = $refApplicationDetial->replicate();
        $approvedrigRegistration->setTable('rig_approved_registrations');                           // Static
        $approvedrigRegistration->application_id    = $applicationId;
        $approvedrigRegistration->approve_date      = $now;
        $approvedrigRegistration->registration_id   = $registrationId;
        $approvedrigRegistration->approve_end_date  = $lastLicenceDate;
        $approvedrigRegistration->approve_user_id   = authUser($request)->id;
        $approvedrigRegistration->save();

        # Save the rig owner details 
        $approvedrigApplicant = $refOwnerDetails->replicate();
        $approvedrigApplicant->setTable('rig_approve_applicants');                                  // Static
        $approvedrigApplicant->created_at = $now;
        $approvedrigApplicant->save();

        # Save the rig detials 
        $approvedrigDetails = $refrigDetails->replicate();
        $approvedrigDetails->setTable('rig_approve_active_details');                                       // Static
        $approvedrigDetails->created_at = $now;
        $approvedrigDetails->save();

        # Send record in the track table 
        $metaReqs = [
            'moduleId'          => $this->_rigModuleId,
            'workflowId'        => $applicationDetails->workflow_id,
            'refTableDotId'     => 'rig_active_registrations.id',                                   // Static
            'refTableIdValue'   => $applicationId,
            'user_id'           => authUser($request)->id,
        ];
        $request->request->add($metaReqs);
        $rigTrack->saveTrack($request);

        # Delete the details form the active table
        $refAppReq = [
            "status" => $status
        ];
        $mRigActiveRegistration->saveApplicationStatus($applicationId, $refAppReq);
        $mRigActiveApplicant->updateApplicantDetials($refOwnerDetails->id, $refAppReq);
        $mRigActiveDetail->updaterigStatus($refrigDetails->id, $refAppReq);
        return [
            "approveDetails" => $approvedrigRegistration,
            "registrationId" => $registrationId
        ];
    }

    /**
     * | Final Approval of a renewal application 
        | Serial No :
        | Working
     */
    public function finalApprovalRenewal($request, $applicationDetails)
    {
        $now                        = Carbon::now();
        $status                     = 0;                                        // Static
        $applicationId              = $request->applicationId;
        $rigTrack                   = new WorkflowTrack();
        $mRigActiveRegistration     = new RigActiveRegistration();
        $mRigActiveApplicant        = new RigActiveApplicant();
        $mRigActiveDetail           = new RigVehicleActiveDetail();
        $mRigApprovedRegistration   = new RigApprovedRegistration();
        $mRigApproveApplicant       = new RigApproveApplicant();
        $mRigApproveDetail          = new RigApproveActiveDetail();
        $lastLicenceDate            = $now->addYear()->subDay();
        $registrationId             = $applicationDetails->registration_id;

        # Data formating for save the consumer details 
        $refApplicationDetial   = $mRigActiveRegistration->getApplicationDetailsById($applicationId)->first();
        $refOwnerDetails        = $mRigActiveApplicant->getApplicationDetails($applicationDetails->ref_application_id)->first();
        $refrigDetails          = $mRigActiveDetail->getrigDetailsByApplicationId($applicationDetails->ref_application_id)->first();

        # Check data existence
        $approveDataExist = $mRigApprovedRegistration->getApproveAppByRegId($applicationDetails->registration_id)
            ->where('status', 2)                                                // Static
            ->first();
        if (!$approveDataExist) {
            throw new Exception("renewal application details dont exist, table ERROR!");
        }

        # get approve application detials 
        $approveApplicantDetail = $mRigApproveApplicant->getApproveApplicant($approveDataExist->application_id)->first();
        $approverigDetail = $mRigApproveDetail->getrigDetailsById($approveDataExist->application_id)->first();

        # Saving the data in the approved application table
        $approvedrigRegistration = $refApplicationDetial->replicate();
        $approvedrigRegistration->setTable('rig_approved_registrations');                           // Static
        $approvedrigRegistration->application_id    = $applicationDetails->ref_application_id;
        $approvedrigRegistration->approve_date      = $now;
        $approvedrigRegistration->registration_id   = $registrationId;
        $approvedrigRegistration->approve_end_date  = $lastLicenceDate;
        $approvedrigRegistration->approve_user_id   = authUser($request)->id;
        $approvedrigRegistration->save();

        # Save the rig owner details 
        $approvedrigApplicant = $refOwnerDetails->replicate();
        $approvedrigApplicant->setTable('rig_approve_applicants');                                  // Static
        $approvedrigApplicant->created_at = $now;
        $approvedrigApplicant->save();

        # Save the rig detials 
        $approvedrigDetails = $refrigDetails->replicate();
        $approvedrigDetails->setTable('rig_approve_details');                                       // Static
        $approvedrigDetails->created_at = $now;
        $approvedrigDetails->save();

        # Delete the details form the active table # updating the status
        $activeData = [
            "status" => $status
        ];
        $mRigActiveRegistration->saveApplicationStatus($applicationDetails->ref_application_id, $activeData);
        $mRigActiveApplicant->updateApplicantDetials($refOwnerDetails->id, $activeData);
        $mRigActiveDetail->updateRigStatus($refrigDetails->id, $activeData);

        # Save approved renewal data in renewal table
        $renewalrigRegistration = $approveDataExist->replicate();
        $renewalrigRegistration->setTable('rig_renewal_registrations');                             // Static  
        $renewalrigRegistration->created_at = $now;
        $renewalrigRegistration->save();

        # Save the approved applicant data in renewal table
        $renewalApplicantReg = $approveApplicantDetail->replicate();
        $renewalApplicantReg->setTable('rig_renewal_applicants');                                   // Static
        $renewalApplicantReg->created_at = $now;
        $renewalApplicantReg->save();

        # Save the approved rig data in renewal details 
        $renewalrigDetails = $approverigDetail->replicate();
        $renewalrigDetails->setTable('rig_renewal_details');                                        // Static
        $renewalrigDetails->created_at = $now;
        $renewalrigDetails->save();

        # Delete the details form the active table # Updating the status
        $approveData = [
            "status" => $status
        ];
        $mRigApprovedRegistration->updateApproveAppStatus($approveDataExist->id, $approveData);
        $mRigApproveApplicant->updateAproveApplicantDetials($approveApplicantDetail->id, $approveData);  /// Not done
        $mRigApproveDetail->updateApproverigStatus($approverigDetail->id, $approveData);             /// Not done   

        # Send record in the track table 
        $metaReqs = [
            'moduleId'          => $this->_rigModuleId,
            'workflowId'        => $applicationDetails->workflow_id,
            'refTableDotId'     => 'rig_active_registrations.id',                                   // Static
            'refTableIdValue'   => $applicationDetails->ref_application_id,
            'user_id'           => authUser($request)->id,
        ];
        $request->request->add($metaReqs);
        $rigTrack->saveTrack($request);
    }


    /**
     * | Fianl rejection of the application 
        | Serial No :
        | Under Con
        | Recheck
     */
    public function finalRejectionOfAppication($request, $applicationDetails)
    {
        $now                        = Carbon::now();
        $status                     = 0;                                               // Static     
        $applicationId              = $request->applicationId;
        $rigTrack                 = new WorkflowTrack();
        $mRigRejectedRegistration   = new RigRejectedRegistration();
        $mRigActiveRegistration     = new RigActiveRegistration();
        $mRigActiveApplicant        = new RigActiveApplicant();
        $mRigActiveDetail           = new RigVehicleActiveDetail();

        # Check if the rejected application exist
        $someDataExist = $mRigRejectedRegistration->getRejectedAppByAppId($applicationId)
            ->whereNot('status', '<>', 0)
            ->first();
        if ($someDataExist) {
            throw new Exception("Rejected application details exist in rejected table ERROR!");
        }

        # Data formating for save the consumer details 
        $refApplicationDetial   = $mRigActiveRegistration->getApplicationDetailsById($applicationId)->first();
        $refOwnerDetails        = $mRigActiveApplicant->getApplicationDetails($applicationId)->first();
        $refRigDetails          = $mRigActiveDetail->getRigDetailsByApplicationId($applicationId)->first();

        # Saving the data in the rejected application table
        $rejectedRigRegistration = $refApplicationDetial->replicate();
        $rejectedRigRegistration->setTable('rig_rejected_registrations');                           // Static
        $rejectedRigRegistration->application_id    = $applicationId;
        $rejectedRigRegistration->rejected_date     = $now;
        $rejectedRigRegistration->rejected_user_id  = authUser($request)->id;
        $rejectedRigRegistration->save();

        # Save the Rig owner details 
        $approvedRigApplicant = $refOwnerDetails->replicate();
        $approvedRigApplicant->setTable('rig_rejected_applicants');                                  // Static
        $approvedRigApplicant->created_at = $now;
        $approvedRigApplicant->save();

        # Save the Rig detials 
        $approvedRigDetails = $refRigDetails->replicate();
        $approvedRigDetails->setTable('rig_vehicle_rejected_details');                                       // Static
        $approvedRigDetails->created_at = $now;
        $approvedRigDetails->save();

        # Send record in the track table 
        $metaReqs = [
            'moduleId'          =>   $this->_rigModuleId,
            'workflowId'        => $applicationDetails->workflow_id,
            'refTableDotId'     => 'rig_active_registrations.id',                                   // Static
            'refTableIdValue'   => $applicationId,
            'user_id'           => authUser($request)->id,
        ];
        $request->request->add($metaReqs);
        $rigTrack->saveTrack($request);

        # Delete the details form the active table
        $refAppReq = [
            "status" => $status
        ];
        $mRigActiveRegistration->saveApplicationStatus($applicationId, $refAppReq);
        $mRigActiveApplicant->updateApplicantDetials($refOwnerDetails->id, $refAppReq);
        $mRigActiveDetail->updateRigStatus($refRigDetails->id, $refAppReq);
        return $rejectedRigRegistration;
    }

    /**
     * | Generate Order Id
        | Serial No :
        | Working
     */
    protected function getUniqueId($key)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 10; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
        $uniqueId = (($key . date('dmyhism') . $randomString));
        $uniqueId = explode("=", chunk_split($uniqueId, 26, "="))[0];
        return $uniqueId;
    }

    /**
     * | Verify, Reject document 
     */

    public function docVerifyRejects(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'id'            => 'required|digits_between:1,9223372036854775807',
                'applicationId' => 'required|digits_between:1,9223372036854775807',
                'docRemarks'    =>  $req->docStatus == "Rejected" ? 'required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/' : "nullable",
                'docStatus'     => 'required|in:Verified,Rejected'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            # Variable Assignments
            $mWfDocument                = new WfActiveDocument();
            $mRigHoard                  = new rigActiveRegistration();
            $mWfRoleusermap             = new WfRoleusermap();
            $wfDocId                    = $req->id;
            $applicationId              = $req->applicationId;
            $userId                     = authUser($req)->id;
            $wfLevel                    = $this->_wfroles;


            # validating application
            $applicationDtl = $mRigHoard->getApplicationDtls($applicationId)
                ->first();
            if (!$applicationDtl || collect($applicationDtl)->isEmpty())
                throw new Exception("Application Details Not Found");

            # validating roles
            $waterReq = new Request([
                'userId'        => $userId,
                'workflowId'    => $applicationDtl['workflow_id']
            ]);
            $senderRoleDtls = $mWfRoleusermap->getRoleByUserWfAndId($waterReq);
            if (!$senderRoleDtls || collect($senderRoleDtls)->isEmpty())
                throw new Exception("Role Not Available");

            # validating role for DA
            // $senderRoleId = $senderRoleDtls->wf_role_id;
            // if ($senderRoleId != $wfLevel['DA'])                                    // Authorization for Dealing Assistant Only
            //     throw new Exception("You are not Authorized");

            # validating if full documet is uploaded
            $ifFullDocVerified = $this->ifFullDocVerified($applicationId);          // (Current Object Derivative Function 0.1)
            // if ($ifFullDocVerified == 1)
            //     throw new Exception("Document Fully Verified");

            DB::beginTransaction();
            if ($req->docStatus == "Verified") {
                $status = 1;
            }
            if ($req->docStatus == "Rejected") {
                # For Rejection Doc Upload Status and Verify Status will disabled 
                $status = 2;
                $applicationDtl->doc_upload_status = 0;
                $applicationDtl->doc_verify_status = false;
                $applicationDtl->save();
            }
            $reqs = [
                'remarks'           => $req->docRemarks,
                'verify_status'     => $status,
                'action_taken_by'   => $userId
            ];
            $mWfDocument->docVerifyRejectv2($wfDocId, $reqs);
            if ($req->docStatus == 'Verified')
                $ifFullDocVerifiedV1 = $this->ifFullDocVerified($applicationId, $req->docStatus);
            else
                $ifFullDocVerifiedV1 = 0;                                         // In Case of Rejection the Document Verification Status will always remain false

            // dd($ifFullDocVerifiedV1);
            if ($ifFullDocVerifiedV1 == 1) {                                     // If The Document Fully Verified Update Verify Status
                $applicationDtl->doc_verify_status = 1;
                $applicationDtl->save();
            }
            DB::commit();
            return responseMsgs(true, $req->docStatus . " Successfully", "", "010204", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010204", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Check if the Document is Fully Verified or Not (0.1) | up
     * | @param
     * | @var 
     * | @return
        | Serial No :  
        | Working 
     */

    public function ifFullDocVerified($applicationId)
    {
        $mRigHoard                  = new rigActiveRegistration();
        $mWfActiveDocument          = new WfActiveDocument();
        $refapplication = $mRigHoard->getApplicationDtls($applicationId)
            ->firstOrFail();

        $refReq = [
            'activeId' => $applicationId,
            'workflowId' => $refapplication->workflow_id,
            'moduleId' => 15
        ];
        $refDocList = $mWfActiveDocument->getVerifiedDocsByActiveId($refReq);
        return $this->isAllDocs($applicationId, $refDocList, $refapplication);
    }
    /**
     * | Checks the Document Upload Or Verify Status
     * | @param activeApplicationId
     * | @param refDocList list of Verified and Uploaded Documents
     * | @param refSafs saf Details
     */
    public function isAllDocs($applicationId, $refDocList, $refapp)
    {
        $docList = array();
        $verifiedDocList = array();
        $verifiedDocList['rigDocs'] = $refDocList->where('owner_dtl_id', null)->values();
        $collectUploadDocList = collect();
        $rigListDocs = $this->getRigTypeDocList($refapp);
        $docList['rigDocs'] = explode('#', $rigListDocs);
        collect($verifiedDocList['rigDocs'])->map(function ($item) use ($collectUploadDocList) {
            return $collectUploadDocList->push($item['doc_code']);
        });
        $mrigDocs = collect($docList['rigDocs']);
        // List Documents
        $flag = 1;
        foreach ($mrigDocs as $item) {
            if (!$item) {
                continue;
            }
            $explodeDocs = explode(',', $item);
            $type = $explodeDocs[0] ?? "O";
            array_shift($explodeDocs);
            foreach ($explodeDocs as $explodeDoc) {
                $changeStatus = 0;
                if (in_array($explodeDoc, $collectUploadDocList->toArray())) {
                    $changeStatus = 1;
                    break;
                }
            }
            if ($changeStatus == 0 && $type == "R") {
                $flag = 0;
                break;
            }
            if ($changeStatus == 0)
                break;
        }

        if ($flag == 0)
            return 0;
        else
            return 1;
    }

    #get doc which is required 
    public function getRigTypeDocList($refapps)
    {
        $moduleId = 15;

        $mrefRequiredDoc = RefRequiredDocument::firstWhere('module_id', $moduleId);
        if ($mrefRequiredDoc && isset($mrefRequiredDoc['requirements'])) {
            $documentLists = $mrefRequiredDoc['requirements'];
        } else {
            $documentLists = [];
        }
        return $documentLists;
    }

    /**
     * | Get approved and rejected application list by the finisher
        | Serial No :
        | Working
     */
    public function listfinisherApproveApplications(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filterBy'  => 'nullable|in:mobileNo,applicantName,applicationNo,holdingNo,safNo',              // Static
                'parameter' => 'nullable',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                       = authUser($request);
            $userId                     = $user->id;
            $confWorkflowMasterId       = $this->_workflowMasterId;
            $key                        = $request->filterBy;
            $paramenter                 = $request->parameter;
            $pages                      = $request->perPage ?? 10;
            $refstring                  = Str::snake($key);
            $msg                        = "Approve application list!";
            $mRigApprovedRegistration   = new RigApprovedRegistration();

            # Check params for role user 
            $roleDetails = $this->getUserRollV2($userId, $user->ulb_id, $confWorkflowMasterId);
            $this->checkParamForUser($user, $roleDetails);

            try {
                $baseQuerry = $mRigApprovedRegistration->getAllApprovdApplicationDetails()
                    ->select(
                        DB::raw("REPLACE(rig_approved_registrations.application_type, '_', ' ') AS ref_application_type"),
                        DB::raw("TO_CHAR(rig_approved_registrations.application_apply_date, 'DD-MM-YYYY') as ref_application_apply_date"),
                        "rig_approved_registrations.*",
                        "rig_approve_applicants.applicant_name",
                        "rig_approve_applicants.mobile_no",
                        "wf_roles.role_name",
                        "rig_approved_registrations.status as registrationSatus",
                        DB::raw("CASE 
                        WHEN rig_approved_registrations.status = 1 THEN 'Approved'
                        WHEN rig_approved_registrations.status = 2 THEN 'Under Renewal Process'
                        END as current_status")
                    )
                    ->where('rig_approved_registrations.status', '<>', 0)
                    ->where('rig_approve_applicants.status', '<>', 0)
                    ->where('rig_approve_active_details.status', '<>', 0)
                    ->where('rig_approved_registrations.approve_user_id', $userId)
                    ->where('rig_approved_registrations.finisher_role_id', $roleDetails->role_id)
                    ->where('rig_approved_registrations.current_role_id', $roleDetails->role_id)
                    ->orderByDesc('rig_approved_registrations.id');

                # Collect querry Exceptions 
            } catch (QueryException $qurry) {
                return responseMsgs(false, "An error occurred during the query!", $qurry->getMessage(), "", "01", ".ms", "POST", $request->deviceId);
            }

            if ($request->filterBy && $request->parameter) {
                $msg = "rig approved appliction details according to $key!";
                # Distrubtion of search category  ❗❗ Static
                switch ($key) {
                    case ("mobileNo"):
                        $activeApplication = $baseQuerry->where('rig_approve_applicants.' . $refstring, 'LIKE', '%' . $paramenter . '%')
                            ->paginate($pages);
                        break;
                    case ("applicationNo"):
                        $activeApplication = $baseQuerry->where('rig_approved_registrations.' . $refstring, 'ILIKE', '%' . $paramenter . '%')
                            ->paginate($pages);
                        break;
                    case ("applicantName"):
                        $activeApplication = $baseQuerry->where('rig_approve_applicants.' . $refstring, 'ILIKE', '%' . $paramenter . '%')
                            ->paginate($pages);
                        break;
                    default:
                        throw new Exception("Data provided in filterBy is not valid!");
                }
                # Check if data not exist
                $checkVal = collect($activeApplication)->last();
                if (!$checkVal || $checkVal == 0) {
                    $msg = "Data Not found!";
                }
                return responseMsgs(true, $msg, remove_null($activeApplication), "", "01", responseTime(), $request->getMethod(), $request->deviceId);
            }

            # Get the latest data for Finisher
            $returnData = $baseQuerry->orderBy('rig_approved_registrations.approve_date')->paginate($pages);
            return responseMsgs(true, $msg, remove_null($returnData), "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | Check the user details 
        | Serial No:
        | Working
     */
    public function checkParamForUser($user, $roleDetails)
    {
        if (!$roleDetails) {
            throw new Exception("user Dont have role in rig workflow!");
        }
        if ($roleDetails->is_finisher == false) {
            throw new Exception("You are not the finisher!");
        }
    }


    /*
    **
     * | Get the rejected application list 
        | Serial No :
        | Working
     */
    public function listfinisherRejectApplications(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filterBy'  => 'nullable|in:mobileNo,applicantName,applicationNo,holdingNo,safNo',              // Static
                'parameter' => 'nullable',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                       = authUser($request);
            $userId                     = $user->id;
            $confWorkflowMasterId       = $this->_workflowMasterId;
            $key                        = $request->filterBy;
            $paramenter                 = $request->parameter;
            $pages                      = $request->perPage ?? 10;
            $refstring                  = Str::snake($key);
            $msg                        = "Rejected application list!";
            $mRigRejectedRegistration   = new RigRejectedRegistration();

            # Check params for role user 
            $roleDetails = $this->getUserRollV2($userId, $user->ulb_id, $confWorkflowMasterId);
            $this->checkParamForUser($user, $roleDetails);

            try {
                $baseQuerry = $mRigRejectedRegistration->getAllRejectedApplicationDetails()
                    ->select(
                        DB::raw("REPLACE(rig_rejected_registrations.application_type, '_', ' ') AS ref_application_type"),
                        DB::raw("TO_CHAR(rig_rejected_registrations.application_apply_date, 'DD-MM-YYYY') as ref_application_apply_date"),
                        "rig_rejected_registrations.*",
                        "rig_rejected_applicants.applicant_name",
                        "rig_rejected_applicants.mobile_no",
                        "wf_roles.role_name",
                        "rig_rejected_registrations.status as registrationSatus",
                        DB::raw("CASE 
                        WHEN rig_rejected_registrations.status = 1 THEN 'Approved'
                        WHEN rig_rejected_registrations.status = 2 THEN 'Under Renewal Process'
                        END as current_status")
                    )
                    ->where('rig_rejected_registrations.status', '<>', 0)
                    ->where('rig_rejected_applicants.status', '<>', 0)
                    ->where('rig_vehicle_rejected_details.status', '<>', 0)
                    ->where('rig_rejected_registrations.rejected_user_id', $userId)
                    ->where('rig_rejected_registrations.finisher_role_id', $roleDetails->role_id)
                    ->where('rig_rejected_registrations.current_role_id', $roleDetails->role_id)
                    ->orderByDesc('rig_rejected_registrations.id');

                # Collect querry Exceptions 
            } catch (QueryException $qurry) {
                return responseMsgs(false, "An error occurred during the query!", $qurry->getMessage(), "", "01", ".ms", "POST", $request->deviceId);
            }

            if ($request->filterBy && $request->parameter) {
                $msg = "rig rejected appliction details according to $key!";
                # Distrubtion of search category  ❗❗ Static
                switch ($key) {
                    case ("mobileNo"):
                        $activeApplication = $baseQuerry->where('rig_rejected_applicants.' . $refstring, 'LIKE', '%' . $paramenter . '%')
                            ->paginate($pages);
                        break;
                    case ("applicationNo"):
                        $activeApplication = $baseQuerry->where('rig_rejected_registrations.' . $refstring, 'ILIKE', '%' . $paramenter . '%')
                            ->paginate($pages);
                        break;
                    case ("applicantName"):
                        $activeApplication = $baseQuerry->where('rig_rejected_applicants.' . $refstring, 'ILIKE', '%' . $paramenter . '%')
                            ->paginate($pages);
                        break;
                    default:
                        throw new Exception("Data provided in filterBy is not valid!");
                }
                # Check if data not exist
                $checkVal = collect($activeApplication)->last();
                if (!$checkVal || $checkVal == 0) {
                    $msg = "Data Not found!";
                }
                return responseMsgs(true, $msg, remove_null($activeApplication), "", "01", responseTime(), $request->getMethod(), $request->deviceId);
            }

            # Get the latest data for Finisher
            $returnData = $baseQuerry->orderBy('rig_rejected_registrations.rejected_date')->paginate($pages);
            return responseMsgs(true, $msg, remove_null($returnData), "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }
}
