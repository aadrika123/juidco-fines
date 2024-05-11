<?php

namespace App\Http\Controllers\Rig;

use App\DocUpload;
use App\Models\Rig\RefRequiredDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rig\RigEditReq;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Traits\Workflow\Workflow;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Rig\RigRegistrationReq;
use App\IdGenerator\IdGeneration;
use App\MicroServices\DocumentUpload;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropFloor;
use App\Models\Property\PropProperty;
use App\Models\Rig\CustomDetail as RigCustomDetail;
use App\Models\Rig\MRigFee;
use App\Models\Rig\RigActiveApplicant;
use App\Models\Rig\RigActiveRegistration;
use App\Models\Rig\RigApprovedRegistration;
use App\Models\Rig\RigRegistrationCharge;
use App\Models\Rig\RigRejectedRegistration;
use App\Models\Rig\RigTran;
use App\Models\Rig\RigVehicleActiveDetail;
use App\Models\Rig\WfActiveDocument as RigWfActiveDocument;
use App\Models\Rig\WorkflowTrack;
use Carbon\Carbon;

use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Models\WfWorkflow;
use App\Models\WfWorkflowrolemap;
use App\Models\Rig\WfActiveDocument;
use App\Models\Rig\CustomDetail;
use App\Models\Rig\RigAudit;
use App\Models\WfRoleusermap;
use Illuminate\Support\Collection;
use Termwind\Components\Raw;

class RigRegistrationController extends Controller
{

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
    private $_applyMode;
    private $_tranType;
    private $_tableName;
    protected $_DB_NAME;
    protected $_DB;
    protected $_DB_NAME2;
    protected $_DB2;
    # Class constructer 
    public function __construct()
    {
        $this->_masterDetails       = Config::get("rig.MASTER_DATA");
        $this->_propertyType        = Config::get("rig.PROP_TYPE");
        $this->_occupancyType       = Config::get("rig.PROP_OCCUPANCY_TYPE");
        $this->_workflowMasterId    = Config::get("rig.WORKFLOW_MASTER_ID");
        $this->_rigParamId          = Config::get("rig.PARAM_ID");
        $this->_rigModuleId         = Config::get('rig.RIG_MODULE_ID');
        $this->_userType            = Config::get("rig.REF_USER_TYPE");
        $this->_rigWfRoles          = Config::get("rig.ROLE_LABEL");
        $this->_docReqCatagory      = Config::get("rig.DOC_REQ_CATAGORY");
        $this->_dbKey               = Config::get("rig.DB_KEYS");
        $this->_fee                 = Config::get("rig.FEE_CHARGES");
        $this->_applicationType     = Config::get("rig.APPLICATION_TYPE");
        $this->_applyMode           = Config::get("rig.APPLY_MODE");
        $this->_tranType            = Config::get("rig.TRANSACTION_TYPE");
        $this->_tableName           = Config::get("rig.TABLE_NAME");
        # Database connectivity
        // $this->_DB_NAME     = "pgsql_property";
        // $this->_DB          = DB::connection($this->_DB_NAME);
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

    #-----------------------------------------------------------------------------------------------------------------------------------#


    /**
     * | Apply for the Rig Registration
     * | Save form data 
     * | @param req
        | Serial No : 0
        | Need Modifications in saving charges
     */
    public function applyRigRegistration(RigRegistrationReq $req)
    {
        try {
            $mRigActiveDetail           = new RigVehicleActiveDetail();
            $mRigActiveRegistration     = new RigActiveRegistration();
            $mRigActiveApplicant        = new RigActiveApplicant();
            $mWfWorkflow                = new WfWorkflow();
            $mWorkflowTrack             = new WorkflowTrack();
            $mRigRegistrationCharge     = new RigRegistrationCharge();
            $mMRigFee                   = new MRigFee();
            $mDocuments                 = $req->documents;
            $user                       = authUser($req);
            $ulbId                      = $req->ulbId ?? 2;                                                 // Static / remove
            $workflowMasterId           = $this->_workflowMasterId;
            $rigParamId                 = $this->_rigParamId;
            $feeId                      = $this->_fee;
            $confApplicationType        = $this->_applicationType;
            $confApplyThrough           = $this->_masterDetails['REGISTRATION_THROUGH'];
            $section                    = 0;

            # Get iniciater and finisher for the workflow 
            $ulbWorkflowId = $mWfWorkflow->getulbWorkflowId($workflowMasterId, $ulbId);
            if (!$ulbWorkflowId) {
                throw new Exception("Respective Ulb is not maped to 'rig Registration' Workflow!");
            }
            if ($req->isRenewal == 0) {
                $registrationCharges = $mMRigFee->getFeeById($feeId['REGISTRATION']);
                if (!$registrationCharges) {
                    throw new Exception("Currently charges are not available!");
                }
            } else {
                $registrationCharges = $mMRigFee->getFeeById($feeId['RENEWAL']);
                if (!$registrationCharges) {
                    throw new Exception("Currently charges are not available!");
                }
            }

            # Save data in track
            if ($user->user_type == $this->_userType['1']) {
                $citzenId = $user->id;
            } else {
                $citzenId = $user->id;
            }

            # Get the Initiator and Finisher details 
            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);
            $refFinisherRoleId  = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId     = collect(DB::select($refFinisherRoleId))->first()->role_id;
            $initiatorRoleId    = collect(DB::select($refInitiatorRoleId))->first()->role_id;

            # Data Base interaction 
            DB::beginTransaction();
            $idGeneration = new IdGeneration($rigParamId['REGISTRATION'], $ulbId, $section, 0);                                     // Generate the application no 
            $rigApplicationNo = $idGeneration->generateId();
            $refData = [
                "finisherRoleId"    => $finisherRoleId,
                "initiatorRoleId"   => $initiatorRoleId,
                "workflowId"        => $ulbWorkflowId->id,
                "applicationNo"     => $rigApplicationNo,
            ];
            $req->merge($refData);

            # Renewal and the New Registration
            if ($req->isRenewal == 0 || !isset($req->isRenewal)) {
                if (isset($req->registrationId)) {
                    throw new Exception("Registration No is Not Req for new rig Registraton!");
                }
                $refData = [
                    "applicationType"   => "New_Apply",
                    "applicationTypeId" => $confApplicationType['NEW_APPLY']
                ];
                $req->merge($refData);
            }
            if ($req->isRenewal == 1) {
                $refData = [
                    "applicationType"   => "Renewal",
                    "registrationId"    => $req->registrationId,
                    "applicationTypeId" => $confApplicationType['RENEWAL']
                ];
                $req->merge($refData);
            }
            # Save active details 
            $applicationDetails = $mRigActiveRegistration->saveRegistration($req, $user);
            $mRigActiveApplicant->saveApplicants($req, $applicationDetails['id']);
            $mRigActiveDetail->saverigDetails($req, $applicationDetails['id']);


            # Save registration charges
            $metaRequest = new Request([
                "applicationId"     => $applicationDetails['id'],
                "applicationType"   => $req->applicationType,
                "amount"            => $registrationCharges->amount,
                "registrationFee"   => $registrationCharges->amount,
                "applicationTypeId" => $req->applicationTypeId
            ]);
            $mRigRegistrationCharge->saveRegisterCharges($metaRequest);
            $ApplicationId = $metaRequest['applicationId'];


            $data = $this->storeDocument($req, $ApplicationId, $ulbWorkflowId->id, $ulbId, $mDocuments);


            # Save the data in workflow track

            $metaReqs = new Request(
                [

                    'citizenId'         => $citzenId ?? null,
                    'moduleId'          => $this->_rigModuleId,
                    'workflowId'        => $ulbWorkflowId->id,
                    'refTableDotId'     => $this->_tableName['2'] . '.id',                             // Static                              // Static
                    'refTableIdValue'   => $applicationDetails['id'],
                    'user_id'           => $userId ?? null,
                    'ulb_id'            => $ulbId,
                    'senderRoleId'      => null,
                    'receiverRoleId'    => $initiatorRoleId,
                    'auth'              => $req->auth
                ]
            );
            
            $mWorkflowTrack->saveTrack($metaReqs);

            DB::commit();
            # Data structure for return
            $returnData = [
                "id"            => $applicationDetails['id'],
                "applicationNo" => $applicationDetails['applicationNo'],
            ];
            return responseMsgs(true, "rig Registration application submitted!", $returnData, "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    public function storeDocument($req, $ApplicationId, $workflowId, $ulbId, $mDocuments)
    {
        try {
            #initiatialise variable 

            $data = [];
            $docUpload = new DocUpload;
            $relativePath = Config::get('rig.RIG_RELATIVE_PATH.REGISTRATION');
            $mWfActiveDocument = new WfActiveDocument();
            $mRigActiveRegistration = new RigActiveRegistration();
            $user = collect(authUser($req));

            // $documentTypes = [
            //     'photo1'      => ' Fitness Image',
            //     'photo2'      => ' Tax Image',
            //     'photo3'      => ' License Image',
            // ];

            foreach ($mDocuments as $document) {
                $file = $document['image'];
                $req->merge([
                    'document' => $file
                ]);
                #_Doc Upload through a DMS
                $imageName = $docUpload->upload($req);
                $metaReqs = [
                    'moduleId' => Config::get('workflow-constants.ADVERTISMENT_MODULE') ?? 15,
                    'activeId' => $ApplicationId,
                    'workflowId' => $workflowId,
                    'ulbId' => $ulbId,
                    'relativePath' => $relativePath,
                    'document' => $imageName,
                    'doc_category' => $req->docCategory,
                    'docCode' => $document['docCode'],
                    'ownerDtlId' => $document['ownerDtlId'],
                    'unique_id' => $imageName['data']['uniqueId'] ?? null,
                    'reference_no' => $imageName['data']['ReferenceNo'] ?? null,
                ];

                // Save document metadata in wfActiveDocuments
                $mWfActiveDocument->postDocuments(new Request($metaReqs), $user);
                //update docupload  status 
                $mRigActiveRegistration->updateUploadStatus($ApplicationId, true);
            }

            return $data;
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }


    public function checkParamForRegister($req)
    {
        $mPropProperty          = new PropProperty();
        $mPropFloor             = new PropFloor();
        $mPropActiveSaf         = new PropActiveSaf();
        $mPropActiveSafsFloor   = new PropActiveSafsFloor();

        $confApplyThrough   = $this->_masterDetails['REGISTRATION_THROUGH'];
        $confPropertyType   = $this->_propertyType;
        $ownertype          = $this->_masterDetails['OWNER_TYPE_MST'];

        switch ($req->applyThrough) {
            case ($req->applyThrough == $confApplyThrough['Holding']):
                $refPropDetails = $mPropProperty->getPropDtls()
                    ->where('prop_properties.holding_no', $req->propertyNo)
                    ->first();
                if (is_null($refPropDetails)) {
                    throw new Exception("property according to $req->propertyNo not found!");
                }
                if ($refPropDetails->prop_type_mstr_id != $confPropertyType['VACANT_LAND']) {
                    $floorsDetails = $mPropFloor->getPropFloors($refPropDetails->id)->get();
                    $isTenant = $this->getPropOccupancyType($floorsDetails);
                    if ($req->ownerCategory == $ownertype['Tenant'] && $isTenant == false) {
                        throw new Exception("Respective property dont have tenant!");
                    }
                }
                if ($refPropDetails->prop_type_mstr_id == $confPropertyType['VACANT_LAND']) {
                    throw new Exception("Rig cannot be applied in VACANT LAND!");
                }
                $returnDetails = [
                    "tenant"        => $isTenant,
                    "propDetails"   => $refPropDetails,
                ];
                break;

            case ($req->applyThrough == $confApplyThrough['Saf']):
                $refSafDetails = $mPropActiveSaf->getSafDtlBySaf()->where('prop_active_safs.saf_no', $req->propertyNo)
                    ->first();
                if (is_null($refSafDetails)) {
                    throw new Exception("property according to $req->propertyNo not found!");
                }
                if ($refSafDetails->prop_type_mstr_id != $confPropertyType['VACANT_LAND']) {
                    $floorsDetails = $mPropActiveSafsFloor->getSafFloors($refSafDetails->id)->get();
                    $isTenant = $this->getPropOccupancyType($floorsDetails);
                    if ($req->ownerCategory == $ownertype['Tenant'] && $isTenant == false) {
                        throw new Exception("Respective property dont have tenant!");
                    }
                }
                if ($refSafDetails->prop_type_mstr_id == $confPropertyType['VACANT_LAND']) {
                    throw new Exception("rig cannot be applied in VACANT LAND!");
                }
                $returnDetails = [
                    "tenant"        => $isTenant,
                    "propDetails"   => $refSafDetails,
                ];
                break;
        }
        return $returnDetails;
    }


    /**
     * | Get occupancy type accordingly for saf and holding
        | Serial No : 0
        | Working
     */
    public function getPropOccupancyType($floorDetails)
    {
        $confOccupancyType  = $this->_occupancyType;
        $refOccupancyType   = collect($confOccupancyType)->flip();
        $isTenanted = collect($floorDetails)
            ->where('occupancy_type_mstr_id', $refOccupancyType['TENANTED'])
            ->first();

        if ($isTenanted) {
            return true;                               // Static
        }
        return false;                                   // Static
    }



    /**
     * | Get Application list for the respective user 
     * | List the application filled by the user 
        | Serial No :
        | Working
     */
    public function getApplicationList(Request $req)
    {
        try {
            $user                   = authUser($req);
            $confUserType           = $this->_userType;
            $confDbKey              = $this->_dbKey;
            $mRigActiveRegistration = new RigActiveRegistration();
            $mRigTran               = new RigTran();

            if ($user->user_type != $confUserType['1']) {                                       // If not a citizen
                throw new Exception("You are not an autherised Citizen!");
            }
            # Collect querry Exceptions 
            try {
                $refAppDetails = $mRigActiveRegistration->getAllApplicationDetails($user->id, $confDbKey['1'])
                    ->select(
                        DB::raw("REPLACE(rig_active_registrations.application_type, '_', ' ') AS ref_application_type"),
                        DB::raw("TO_CHAR(rig_active_registrations.application_apply_date, 'DD-MM-YYYY') as ref_application_apply_date"),
                        "rig_active_registrations.*",
                        "rig_active_applicants.applicant_name",
                        "wf_roles.role_name"
                    )
                    ->orderByDesc('rig_active_registrations.id')
                    ->get();
            } catch (QueryException $q) {
                return responseMsgs(false, "An error occurred during the query!", $q->getMessage(), "", "01", ".ms", "POST", $req->deviceId);
            }
            // $returnData = collect($refAppDetails);
            # Get transaction no for the respective application
            $returnData = collect($refAppDetails)->map(function ($value) use ($mRigTran) {
                if ($value->payment_status != 0) {
                    $tranNo = $mRigTran->getTranDetails($value->id,)->first();
                    // Check if $tranNo is not null before accessing its properties
                    if ($tranNo) {
                        $value->transactionNo = $tranNo->tran_no;
                    } else {
                        $value->transactionNo = null; // Or handle it as per your requirement
                    }
                }
                return $value;
            });
            return responseMsgs(true, "list of active registration!", remove_null($returnData), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Get application details by application id
     * | collective data with registration charges
        | Serial No :
        | Working
     */
    public function getApplicationDetails(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $applicationId          = $req->applicationId;
            $mRigActiveRegistration = new RigActiveRegistration();
            $mRigRegistrationCharge = new RigRegistrationCharge();
            $mRigTran               = new RigTran();

            $applicationDetails = $mRigActiveRegistration->getrigApplicationById($applicationId)->first();
            if (is_null($applicationDetails)) {
                throw new Exception("application Not found!");
            }
            $chargeDetails = $mRigRegistrationCharge->getChargesbyId($applicationDetails->ref_application_id)
                ->select(
                    'id AS chargeId',
                    'amount',
                    'registration_fee',
                    'paid_status',
                    'charge_category',
                    'charge_category_name'
                )
                ->first();
            if (is_null($chargeDetails)) {
                throw new Exception("Charges for respective application not found!");
            }
            if ($chargeDetails->paid_status != 0) {
                # Get Transaction details 
                $tranDetails = $mRigTran->getTranByApplicationId($applicationId)->first();
                if (!$tranDetails) {
                    throw new Exception("Transaction details not found there is some error in data !");
                }
                $applicationDetails['transactionDetails'] = $tranDetails;
            }
            $chargeDetails['roundAmount'] = round($chargeDetails['amount']);
            $applicationDetails['charges'] = $chargeDetails;
            return responseMsgs(true, "Listed application details!", remove_null($applicationDetails), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }


    /**
     * | Get the upoaded docunment
        | Serial No : 
        | Working
     */
    public function getUploadDocuments(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWfActiveDocument      = new WfActiveDocument();
            $mRigActiveRegistration = new RigActiveRegistration();
            $refDocUpload           = new DocUpload;
            $moduleId               = $this->_rigModuleId;
            $applicationId          = $req->applicationId;

            $RigDetails = $mRigActiveRegistration->getRigApplicationById($applicationId)->first();
            if (!$RigDetails)
                throw new Exception("Application Not Found for this ($applicationId) application Id!");

            $workflowId = $RigDetails->workflow_id;
            $documents  = $mWfActiveDocument->getRigDocsByAppNo($applicationId, $workflowId, $moduleId)
                ->where('d.status', '!=', 0)
                ->get();
            // $returnData = collect($documents)->map(function ($value) {
            //     $path =  $this->getDocUrl($value->refDocUpload);
            //     $value->doc_path = !empty(trim($value->refDocUpload)) ? $path : null;
            //     return $value;
            // });
            $data = $refDocUpload->getDocUrl($documents)->toArray();
            foreach ($data as $key => $value) {
                if ($value['doc_code'] == "FITNESS") {
                    $data[$key]['doc_code'] = "POLLUTION";
                }
            }
            return responseMsgs(true, "Uploaded Documents", remove_null($data), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Apply the renewal for Rig 
     * | registered Rig renewal process
        | Serial No :
        | Under Con 
        | Check
     */
    public function applyRigRenewal(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'registrationId'    => 'required|int',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $renewal = 1;                                                                           // Static
            $mRigApprovedRegistration = new RigApprovedRegistration();

            # Check the Registered Application existence
            $refApprovedDetails = $mRigApprovedRegistration->getApplictionByRegId($request->registrationId)->first();
            if (!$refApprovedDetails) {
                throw new Exception("Application Detail Not found!");
            }

            # Check Params for renewal of Application
            $this->checkParamForRenewal($refApprovedDetails->registration_id, $refApprovedDetails);
            $newReq = [
                "address"           => $refApprovedDetails->address,
                "applyThrough"      => $refApprovedDetails->apply_through,
                "ownerCategory"     => $refApprovedDetails->ref_owner_type,
                "ulbId"             => $refApprovedDetails->ulb_id,
                "ward"              => $refApprovedDetails->ward_id,
                "applicantName"     => $refApprovedDetails->applicant_name,
                "mobileNo"          => $refApprovedDetails->mobile_no,
                "email"             => $request->email ?? $refApprovedDetails->email,
                "panNo"             => $refApprovedDetails->pan_no,
                "telephone"         => $refApprovedDetails->telephone,
                "registrationId"    => $refApprovedDetails->registration_id,        // Important
                "isRenewal"         => $renewal,                                    // Static
                "auth"              => $request->auth,
                "documents"         => $request->documents,
                "driverBirthDate"   => $refApprovedDetails->dob

            ];
            $rigRegistrationReq = new RigRegistrationReq($newReq);

            DB::beginTransaction();
            # Apply so that appliction get to workflow
            $applyDetails = $this->applyRigRegistration($rigRegistrationReq);                   // Here 
            if ($applyDetails->original['status'] == false) {
                throw new Exception($applyDetails->original['message'] ?? "Renewal Process can't be done!");
            };
            # Update the details for renewal
            $this->updateRenewalDetails($refApprovedDetails);
            DB::commit();
            $returnDetails = $applyDetails->original['data'];
            return responseMsgs(true, "Application applied for renewal!", $returnDetails, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Update the status for the renewal process in approved table and to other related table
        | Serial No :
        | Working
     */
    public function updateRenewalDetails($previousApproveDetils)
    {
        $mRigApprovedRegistration = new RigApprovedRegistration();
        $updateReq = [
            'status' => 2                                                                       // Static
        ];
        $mRigApprovedRegistration->updateRelatedStatus($previousApproveDetils->approveId, $updateReq);
    }
    /**
     * | check param for renewal of Rig 
        | Serial No :
        | Under con
        | ❗❗ Uncomment the restriction for yearly licence check ❗❗
     */
    public function checkParamForRenewal($renewalId, $refApprovedDetails)
    {
        $now = Carbon::now();
        $mRigActiveRegistration = new RigActiveRegistration();
        $isRenewalInProcess = $mRigActiveRegistration->getApplicationByRegId($renewalId)
            ->where('renewal', 1)
            ->first();
        if ($isRenewalInProcess) {
            throw new Exception("Renewal of the Application is in process!");
        }

        # Check the lecence year difference 
        $approveDate = Carbon::parse($refApprovedDetails->approve_date);
        $approveDate = $approveDate->copy()->addDays(7);
        $yearDifferernce = $approveDate->diffInYears($now);
        if ($yearDifferernce <= 1) {
            throw new Exception("Application has an active licence please apply Larter!");
        }
    }

    /**
     * | Show approved appliction for citizen side
        | Serial No :
        | Working
     */
    public function getApproveRegistration(Request $req)
    {
        try {
            $user                       = authUser($req);
            $confUserType               = $this->_userType;
            $mRigApprovedRegistration   = new RigApprovedRegistration();

            if ($user->user_type != $confUserType['1']) {                                       // If not a citizen
                throw new Exception("You are not an autherised Citizen!");
            }
            # Collect querry Exceptions 
            try {
                $refApproveDetails = $mRigApprovedRegistration->getAllApprovdApplicationDetails()
                    ->select(
                        DB::raw("REPLACE(rig_approved_registrations.application_type, '_', ' ') AS ref_application_type"),
                        DB::raw("TO_CHAR(rig_approved_registrations.application_apply_date, 'DD-MM-YYYY') as ref_application_apply_date"),
                        "rig_active_registrations.id",
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
                        "rig_approve_applicants.applicant_name",
                        "rig_approve_applicants.mobile_no",
                        "rig_active_registrations.user_type",
                        "rig_approve_applicants.applicant_name",
                        "wf_roles.role_name",
                        "rig_approved_registrations.status as registrationSatus",
                        DB::raw("CASE 
                        WHEN rig_approved_registrations.status = 1 THEN 'Approved'
                        WHEN rig_approved_registrations.status = 2 THEN 'Under Renewal Process'
                        END as current_status")
                    )
                    ->where('rig_approved_registrations.status', '<>', 0)
                    ->where('rig_approved_registrations.citizen_id', $user->id)
                    ->orderByDesc('rig_approved_registrations.id')
                    ->get();
            } catch (QueryException $qurry) {
                return responseMsgs(false, "An error occurred during the query!", $qurry->getMessage(), "", "01", ".ms", "POST", $req->deviceId);
            }
            return responseMsgs(true, "list of active registration!", remove_null($refApproveDetails), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
     * | get the rejected applications for respective user
        | Serial No :
        | Working
     */
    public function getRejectedRegistration(Request $req)
    {
        try {
            $user                       = authUser($req);
            $confUserType               = $this->_userType;
            $mRigRejectedRegistration   = new RigRejectedRegistration();

            if ($user->user_type != $confUserType['1']) {                                       // If not a citizen
                throw new Exception("You are not an autherised Citizen!");
            }
            # Collect querry Exceptions 
            try {
                $refRejectedDetails = $mRigRejectedRegistration->getAllRejectedApplicationDetails()
                    ->select(
                        DB::raw("REPLACE(rig_rejected_registrations.application_type, '_', ' ') AS ref_application_type"),
                        DB::raw("TO_CHAR(rig_rejected_registrations.application_apply_date, 'DD-MM-YYYY') as ref_application_apply_date"),
                        "rig_active_registrations.id",
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
                        "rig_approve_applicants.applicant_name",
                        "rig_approve_applicants.mobile_no",
                        "rig_active_registrations.user_type",
                        "rig_rejected_applicants.applicant_name",
                        "wf_roles.role_name",
                        "rig_rejected_registrations.status as registrationSatus",
                        DB::raw("CASE 
                        WHEN rig_rejected_registrations.status = 1 THEN 'Approved'
                        WHEN rig_rejected_registrations.status = 2 THEN 'Under Renewal Process'
                        END as current_status")
                    )
                    ->where('rig_rejected_registrations.status', '<>', 0)
                    ->where('rig_rejected_registrations.citizen_id', $user->id)
                    ->orderByDesc('rig_rejected_registrations.id')
                    ->get();
            } catch (QueryException $qurry) {
                return responseMsgs(false, "An error occurred during the query!", $qurry->getMessage(), "", "01", responseTime(), $req->getMethod(), $req->deviceId);
            }
            return responseMsgs(true, "list of active registration!", remove_null($refRejectedDetails), "", "01", responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $req->getMethod(), $req->deviceId);
        }
    }


    //BEGIN///////////////////////////////////////////////////////////////////////////////
    /**
     * | Get Application details for workflow view 
     * | @param request
     * | @var ownerDetails
     * | @var applicantDetails
     * | @var applicationDetails
     * | @var returnDetails
     * | @return returnDetails : list of individual applications
        | Serial No : 08
        | Workinig 
     */
    public function getApplicationsDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            # object assigning              
            $mRigActiveRegistration = new RigActiveRegistration();
            $mRigActiveApplicant    = new RigActiveApplicant();
            $mWorkflowMap           = new WfWorkflowrolemap();
            $mWorkflowTracks        = new WorkflowTrack();
            // $mCustomDetails         = new CustomDetail();
            $applicationId          = $request->applicationId;
            $aplictionList          = array();

            # application details
            $applicationDetails = $mRigActiveRegistration->getRigApplicationById($applicationId)->first();
            if (!$applicationDetails) {
                throw new Exception("Application data according to $request->applicationId not found");
            }
            # owner Details
            $applyDate = Carbon::createFromFormat('Y-m-d', $applicationDetails->application_apply_date)->format('d-m-Y');
            $aplictionList['application_no'] = $applicationDetails->application_no;
            $aplictionList['apply_date']     = $applyDate;

            # DataArray
            $basicDetails       = $this->getBasicDetails($applicationDetails);
            // $propertyDetails    = $this->getpropertyDetails($applicationDetails);
            $rigDetails         = $this->getrefRigDetails($applicationDetails);

            $firstView = [
                'headerTitle'   => 'Basic Details',
                'data'          => $basicDetails
            ];
            $secondView = [
                'headerTitle'   => ' Rig Details',
                'data'          => $rigDetails
            ];
            // $thirdView = [
            //     'headerTitle'   => 'Rig Details',
            //     'data'          => $rigDetails
            // ];
            $fullDetailsData['fullDetailsData']['dataArray'] = new collection([$firstView, $secondView,]);

            # CardArray
            $cardDetails = $this->getCardDetails($applicationDetails);
            $cardData = [
                'headerTitle' => 'Rig Registration',
                'data' => $cardDetails
            ];
            $fullDetailsData['fullDetailsData']['cardArray'] = new Collection($cardData);

            # TableArray
            $ownerDetail = $mRigActiveApplicant->getApplicationDetails($applicationId)->get();
            $ownerList = $this->getOwnerDetails($ownerDetail);
            $ownerView = [
                'headerTitle' => 'Owner Details',
                'tableHead' => ["#", "Owner Name", "Mobile No", "Email", "Pan"],
                'tableData' => $ownerList
            ];
            $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerView]);

            # Level comment
            $mtableId = $applicationDetails->ref_application_id;
            $mRefTable = "rig_active_registrations.id";                         // Static
            $levelComment['levelComment'] = $mWorkflowTracks->getTracksByRefId($mRefTable, $mtableId);

            #citizen comment
            $refCitizenId = $applicationDetails->citizen_id;
            $citizenComment['citizenComment'] = $mWorkflowTracks->getCitizenTracks($mRefTable, $mtableId, $refCitizenId);

            # Role Details
            $metaReqs = [
                'customFor'     => 'Rig',
                'wfRoleId'      => $applicationDetails->current_role_id,
                'workflowId'    => $applicationDetails->workflow_id,
                'lastRoleId'    => $applicationDetails->last_role_id
            ];
            $request->request->add($metaReqs);
            $roleDetails['roleDetails'] = $mWorkflowMap->getRoleDetails($request);

            # Timeline Data
            $timelineData['timelineData'] = collect($request);

            # Departmental Post
            // $custom = $mCustomDetails->getCustomDetails($request);
            // $departmentPost['departmentalPost'] = $custom;

            # Payments Details
            // return array_merge($aplictionList, $fullDetailsData,$levelComment,$citizenComment,$roleDetails,$timelineData,$departmentPost);
            $returnValues = array_merge($aplictionList, $fullDetailsData, $levelComment, $citizenComment, $roleDetails, $timelineData,);
            return responseMsgs(true, "listed Data!", $returnValues, "", "02", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "02", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * |------------------ Basic Details ------------------|
     * | @param applicationDetails
     * | @var collectionApplications
        | Serial No : 08.01
        | Workinig 
     */
    public function getBasicDetails($applicationDetails)
    {
        if ($applicationDetails->apply_through == 1) {
            $applyThrough = "Holding";
        } else {
            $applyThrough = "Saf";
        }
        // Check if the application type is "Renewal"
        if ($applicationDetails->application_type === "Renewal") {
            $applicationType = "Renewal";
        } else {
            // Extracting "NEW" from "New_Apply"
            $applicationType = strtoupper(substr($applicationDetails->application_type, 0, 3));
        }
        $applyDate = Carbon::createFromFormat('Y-m-d', $applicationDetails->application_apply_date)->format('d-m-Y');
        return new Collection([
            ['displayString' => 'Ward No',              'key' => 'WardNo',                  'value' => $applicationDetails->ward_name],
            ['displayString' => 'Type of Connection',   'key' => 'TypeOfConnection',        'value' => $applicationType],
            ['displayString' => 'Apply From',           'key' => 'ApplyFrom',               'value' => $applicationDetails->apply_mode],
            ['displayString' => 'Apply Date',           'key' => 'ApplyDate',               'value' => $applyDate]
        ]);
    }

    /**
     * |------------------ Property Details ------------------|
     * | @param applicationDetails
     * | @var propertyDetails
     * | @var collectionApplications
        | Serial No : 08.02
        | Workinig 
     */
    public function getpropertyDetails($applicationDetails)
    {
        $propertyDetails = array();
        if (!is_null($applicationDetails->holding_no)) {
            array_push($propertyDetails, ['displayString' => 'Holding No',    'key' => 'AppliedBy',  'value' => $applicationDetails->holding_no]);
        }
        if (!is_null($applicationDetails->saf_no)) {
            array_push($propertyDetails, ['displayString' => 'Saf No',        'key' => 'AppliedBy',   'value' => $applicationDetails->saf_no]);
        }
        if ($applicationDetails->owner_type == 1) {
            $ownerType = "Owner";
        } else {
            $ownerType = "Tenant";
        }
        array_push($propertyDetails, ['displayString' => 'Ward No',       'key' => 'WardNo',      'value' => $applicationDetails->ward_name]);
        array_push($propertyDetails, ['displayString' => 'Address',       'key' => 'Address',     'value' => $applicationDetails->address]);
        array_push($propertyDetails, ['displayString' => 'Owner Type',    'key' => 'OwnerType',   'value' => $ownerType]);

        return $propertyDetails;
    }

    /**
     * |------------------ Owner details ------------------|
     * | @param ownerDetails
        | Serial No : 08.04
        | Workinig 
     */
    public function getrefRigDetails($applicationDetails)
    {


        return new Collection([

            ['displayString' => 'Vin Number',                          'key' => 'vinNumber',                         'value' => $applicationDetails->vehicle_name],
            ['displayString' => 'Vehicle Number',                      'key' => 'vehicleNumber',                       'value' => $applicationDetails->vehicle_no],

        ]);
    }

    /**
     * |------------------ Get Card Details ------------------|
     * | @param applicationDetails
     * | @param ownerDetails
     * | @var ownerDetail
     * | @var collectionApplications
        | Serial No : 08.05
        | Workinig 
     */
    public function getCardDetails($applicationDetails)
    {
        $applyDate = Carbon::createFromFormat('Y-m-d', $applicationDetails->application_apply_date)->format('d-m-Y');
        return new Collection([
            ['displayString' => 'Ward No.',             'key' => 'WardNo.',             'value' => $applicationDetails->ward_name],
            ['displayString' => 'Application No.',      'key' => 'ApplicationNo.',      'value' => $applicationDetails->application_no],
            ['displayString' => 'Owner Name',           'key' => 'OwnerName',           'value' => $applicationDetails->applicant_name],
            ['displayString' => 'Connection Type',      'key' => 'ConnectionType',      'value' => $applicationDetails->application_type],
            ['displayString' => 'Apply-Date',           'key' => 'ApplyDate',           'value' => $applyDate],
            ['displayString' => 'Address',              'key' => 'Address',             'value' => $applicationDetails->address],
        ]);
    }
    /**
     * |------------------ Owner details ------------------|
     * | @param ownerDetails
        | Serial No : 08.04
        | Workinig 
     */
    public function getOwnerDetails($ownerDetails)
    {
        return collect($ownerDetails)->map(function ($value, $key) {
            return [
                $key + 1,
                $value['applicant_name'],
                $value['mobile_no'],
                $value['email'],
                $value['pan_no']
            ];
        });
    }

    /**
     * | Get the rejected application details 
        | Serial No :
        | Working
     */
    public function getRejectedApplicationDetails(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'registrationId' => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $viewRenewButton            = false;
            $applicationId              = $req->registrationId;
            $mRigRejectedRegistration   = new RigRejectedRegistration();
            $mRigRegistrationCharge     = new RigRegistrationCharge();
            $mRigTran                   = new RigTran();

            $rejectedApplicationDetails = $mRigRejectedRegistration->getRigRejectedApplicationById($applicationId)
                ->where('rig_rejected_registrations.status', '<>', 0)                                                       // Static
                ->first();
            if (is_null($rejectedApplicationDetails)) {
                throw new Exception("application Not found!");
            }
            $chargeDetails = $mRigRegistrationCharge->getChargesbyId($rejectedApplicationDetails->application_id)
                ->select(
                    'id AS chargeId',
                    'amount',
                    'registration_fee',
                    'paid_status',
                    'charge_category',
                    'charge_category_name'
                )

                ->first();
            if (is_null($chargeDetails)) {
                throw new Exception("Charges for respective application not found!");
            }
            # Get Transaction details
            $tranDetails = null;
            if ($chargeDetails->paid_status == 1) {
                $tranDetails = $mRigTran->getTranByApplicationId($rejectedApplicationDetails->application_id)->first();
                if (!$tranDetails) {
                    throw new Exception("Transaction details not found there is some error in data !");
                }
            }
            # return Details 
            $rejectedApplicationDetails['transactionDetails']    = $tranDetails;
            $chargeDetails['roundAmount']                       = round($chargeDetails['amount']);
            $rejectedApplicationDetails['charges']               = $chargeDetails;
            $rejectedApplicationDetails['viewRenewalButton']     = $viewRenewButton;
            return responseMsgs(true, "Listed application details!", remove_null($rejectedApplicationDetails), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }
    /**
     * | Get Approved application details by application id
     * | collective data with registration charges
        | Serial No :
        | Working
     */
    public function getApprovedApplicationDetails(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'registrationId' => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                       = authUser($req);
            $viewRenewButton            = false;
            $applicationId              = $req->registrationId;
            $mRigApprovedRegistration   = new RigApprovedRegistration();
            $mRigRegistrationCharge     = new RigRegistrationCharge();
            $mPetTran                   = new RigTran();

            $approveApplicationDetails = $mRigApprovedRegistration->getRigApprovedApplicationById($applicationId)
                ->where('rig_approved_registrations.status', '<>', 0)                                                       // Static
                ->first();
            if (is_null($approveApplicationDetails)) {
                throw new Exception("application Not found!");
            }
            $chargeDetails = $mRigRegistrationCharge->getChargesbyId($approveApplicationDetails->application_id)
                ->select(
                    'id AS chargeId',
                    'amount',
                    'registration_fee',
                    'paid_status',
                    'charge_category',
                    'charge_category_name'
                )
                ->first();
            if (is_null($chargeDetails)) {
                throw new Exception("Charges for respective application not found!");
            }
            # Get Transaction details
            $tranDetails = null;
            if ($chargeDetails->paid_status == 1) {
                $tranDetails = $mPetTran->getTranByApplicationId($approveApplicationDetails->application_id)->first();
                if (!$tranDetails) {
                    throw new Exception("Transaction details not found there is some error in data !");
                }
            }

            # Check for jsk for renewal button
            if ($user->user_type == 'JSK') {                                                                                // Static
                $viewRenewButton = true;
            }

            # return Details 
            $approveApplicationDetails['transactionDetails']    = $tranDetails;
            $chargeDetails['roundAmount']                       = round($chargeDetails['amount']);
            $approveApplicationDetails['charges']               = $chargeDetails;
            $approveApplicationDetails['viewRenewalButton']     = $viewRenewButton;
            return responseMsgs(true, "Listed application details!", remove_null($approveApplicationDetails), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }



    /**
     * | Search active applications according to certain search category
        | Serial No :
        | Working
     */
    public function searchApplication(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filterBy'  => 'required|in:mobileNo,applicantName,applicationNo',
                'parameter' => 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            # Variable assigning
            $key        = $request->filterBy;
            $paramenter = $request->parameter;
            $pages      = $request->perPage ?? 10;
            $refstring  = Str::snake($key);
            $msg        = "Rig active appliction details according to parameter!";

            $mRigActiveRegistration = new RigActiveRegistration();
            $mRigActiveApplicant    = new RigActiveApplicant();

            # Distrubtion of search category
            switch ($key) {
                case ("mobileNo"):                                                                                                                      // Static
                    $activeApplication = $mRigActiveApplicant->getRelatedApplicationDetails($request, $refstring, $paramenter)->paginate($pages);
                    break;
                case ("applicationNo"):
                    $activeApplication = $mRigActiveRegistration->getActiveApplicationDetails($request, $refstring, $paramenter)->paginate($pages);
                    break;
                case ("applicantName"):
                    $activeApplication = $mRigActiveApplicant->getRelatedApplicationDetails($request, $refstring, $paramenter)->paginate($pages);
                default:
                    throw new Exception("Data provided in filterBy is not valid!");
            }
            # Check if data not exist
            $checkVal = collect($activeApplication)->last();
            if (!$checkVal || $checkVal == 0) {
                $msg = "Data Not found!";
            }
            return responseMsgs(true, $msg, remove_null($activeApplication), "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | get license data
     */
    public function getLicnenseDetails(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'registrationId' => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                       = authUser($req);
            $viewRenewButton            = false;
            $applicationId              = $req->registrationId;
            $mRigApprovedRegistration   = new RigApprovedRegistration();
            $mRigRegistrationCharge     = new RigRegistrationCharge();
            $mRigTran                   = new RigTran();

            $approveApplicationDetails = $mRigApprovedRegistration->getRigApprovedApplicationById($applicationId)
                ->where('rig_approved_registrations.status', '<>', 0)                                                       // Static
                ->first();
            if (is_null($approveApplicationDetails)) {
                throw new Exception("application Not found!");
            }
            $chargeDetails = $mRigRegistrationCharge->getChargesbyId($approveApplicationDetails->application_id)
                ->select(
                    'id AS chargeId',
                    'amount',
                    'registration_fee',
                    'paid_status',
                    'charge_category',
                    'charge_category_name'
                )
                ->where('paid_status', 1)
                ->first();
            if (is_null($chargeDetails)) {
                throw new Exception("Charges for respective application not found!");
            }
            # Get Transaction details
            $tranDetails = $mRigTran->getTranByApplicationId($approveApplicationDetails->application_id)->first();
            if (!$tranDetails) {
                throw new Exception("Transaction details not found");
            }

            # Check for jsk for renewal button
            if ($user->user_type == 'JSK') {                                                                                // Static
                $viewRenewButton = true;
            }

            # return Details 
            $approveApplicationDetails['transactionDetails']    = $tranDetails;
            $chargeDetails['roundAmount']                       = round($chargeDetails['amount']);
            $approveApplicationDetails['charges']               = $chargeDetails;
            $approveApplicationDetails['viewRenewalButton']     = $viewRenewButton;
            return responseMsgs(true, "Listed application details!", remove_null($approveApplicationDetails), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }
    /**
     * |dashboard data
     */
    public function rigDashbordDtls(Request $request)
    {
        try {
            $user = authUser($request);
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $userType = $user->user_type;
            $mRigActiveRegistration = new RigActiveRegistration();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');
            $data['recentApplications'] = $mRigActiveRegistration->recentApplication($workflowIds, $roleId, $ulbId);
            if ($userType == 'JSK') {
                $data['recentApplications'] = $mRigActiveRegistration->recentApplicationJsk($userId, $ulbId);
            }
            $data['pendingApplicationCount'] = $mRigActiveRegistration->pendingApplicationCount();
            $data['approvedApplicationCount'] = $mRigActiveRegistration->approvedApplicationCount();
            return responseMsgs(true, "Recent Application", remove_null($data), "011901", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011901", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    /**
     * | Reuploaded rejected document
     * | Function - 36
     * | API - 33
     */
    public function reuploadDocuments(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|digits_between:1,9223372036854775807',
            'image' => 'required|mimes:png,jpeg,pdf,jpg|max:2048'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            // Variable initialization
            $mRigActiveRegistrations = new RigActiveRegistration();
            $Image                   = $req->image;
            $docId                   = $req->id;
            DB::beginTransaction();
            $appId = $this->reuploadDocument($req, $Image, $docId);
            $this->checkFullUpload($appId);
            DB::commit();
            return responseMsgs(true, "Document Uploaded Successfully", "", "050133", 1.0, responseTime(), "POST", "", "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, "Document Not Uploaded", "", "050133", 1.0, "271ms", "POST", "", "");
        }
    }

    public function reuploadDocument($req, $Image, $docId)
    {
        try {
            #initiatialise variable 

            $data = [];
            $docUpload = new DocUpload;
            $relativePath = Config::get('rig.RIG_RELATIVE_PATH.REGISTRATION');
            $mWfActiveDocument = new WfActiveDocument();
            $mRigActiveRegistration = new RigActiveRegistration();
            $user = collect(authUser($req));


            $file = $Image;
            $req->merge([
                'document' => $file
            ]);
            #_Doc Upload through a DMS
            $imageName = $docUpload->upload($req);
            $metaReqs = [
                'moduleId' => Config::get('workflow-constants.ADVERTISMENT_MODULE') ?? 15,
                'unique_id' => $imageName['data']['uniqueId'] ?? null,
                'reference_no' => $imageName['data']['ReferenceNo'] ?? null,
            ];

            // Save document metadata in wfActiveDocuments
            $activeId = $mWfActiveDocument->updateDocuments(new Request($metaReqs), $user, $docId);
            return $activeId;

            // return $data;
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Cheque full upload document or no
     */

    public function checkFullUpload($applicationId)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $mRefRequirement = new RefRequiredDocument();
        $moduleId =  $this->_rigModuleId;
        $totalRequireDocs = $mRefRequirement->totalNoOfDocs($moduleId);
        $appDetails = RigActiveRegistration::find($applicationId);
        $totalUploadedDocs = $mWfActiveDocument->totalUploadedDocs($applicationId, $appDetails->workflow_id, $moduleId);
        if ($totalRequireDocs == $totalUploadedDocs) {
            $appDetails->doc_upload_status = true;
            $appDetails->doc_verify_status = '0';
            $appDetails->parked = false;
            $appDetails->save();
        } else {
            $appDetails->doc_upload_status = '0';
            $appDetails->doc_verify_status = '0';
            $appDetails->save();
        }
    }
    /**
     * | Edit the application Rig details
        | Serial No :
        | Working
        | CAUTION
     */
    public function editRigDetails(RigEditReq $req)
    {
        try {
            $applicationId          = $req->id;
            $confTableName          = $this->_tableName;
            $mRigActiveDetail       = new RigVehicleActiveDetail();
            $mRigActiveRegistration = new RigActiveRegistration();
            $mRIgActiveApllicants   = new RigActiveApplicant();
            $mRigAudit              = new RigAudit();
            $refRelatedDetails      = $this->checkParamForRigUdate($req);
            $applicationDetails     = $refRelatedDetails['applicationDetails'];

            DB::beginTransaction();
            # operate with the data from above calling function 
            $rigDetails           = $mRigActiveDetail->getrigDetailsByApplicationId($applicationId)->first();
            $rigApplicantDtls     = $mRIgActiveApllicants->getRigActiveApplicants($applicationId)->first();
            $oldRigDetails  = json_encode($rigDetails);
            $oldApplication = json_encode($applicationDetails);
            $oldApplicant   = json_encode($rigApplicantDtls);

            $mRigAudit->saveAuditData($oldRigDetails, $confTableName['1']);
            $mRigAudit->saveAuditData($oldApplication, $confTableName['2']);
            $mRigAudit->saveAuditData($oldApplicant, $confTableName['3']);
            $mRigActiveDetail->updateRigDetails($req, $rigDetails);
            $mRIgActiveApllicants->updateRigApplicantsDtls($req, $rigApplicantDtls);
            $mRigActiveRegistration->updateRigApplication($req, $applicationDetails);
            $updateReq = [
                "occurrence_type_id" => $req->petFrom ?? $applicationDetails->occurrence_type_id
            ];
            $mRigActiveRegistration->saveApplicationStatus($applicationDetails->ref_application_id, $updateReq);
            DB::commit();
            return responseMsgs(true, "Rig Details Updated!", [], "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }


    /**
     * | Check Param for update the pet Application details 
        | Serial No : 
        | Working
     */
    public function checkParamForRigUdate($req)
    {
        $user                   = authUser($req);
        $applicationId          = $req->id;
        $confRoles              = $this->_rigWfRoles;
        $mRigActiveRegistration = new RigActiveRegistration();
        $mWfRoleusermap         = new WfRoleusermap();
        $mRigTran               = new RigTran();

        # Collecting application detials
        $applicationdetails = $mRigActiveRegistration->getrigApplicationById($applicationId)->first();
        if (!$applicationdetails) {
            throw new Exception("Application details not found!");
        }
        if ($applicationdetails->renewal == 1) {
            throw new Exception("application cannot be edited in case of renewal!");
        }

        # Validation diff btw citizen and user
        switch ($applicationdetails) {
            case (is_null($applicationdetails->citizen_id) && !is_null($applicationdetails->user_id)):
                $getRoleReq = new Request([                                                 // make request to get role id of the user
                    'userId'        => $user->id,
                    'workflowId'    => $applicationdetails->workflow_id
                ]);
                // $readRoleDtls = $mWfRoleusermap->getRoleByUserWfId($getRoleReq);
                // if (!$readRoleDtls) {
                //     throw new Exception("User Dont have any role!");
                // }

                # Check for jsk 
                // $roleId = $readRoleDtls->wf_role_id;
                // if ($roleId != $confRoles['JSK']) {
                //     throw new Exception("You are not Permited to edit the application!");
                // }
                if ($user->id != $applicationdetails->user_id) {
                    throw new Exception("You are not the right user who applied!");
                }
                if ($applicationdetails->payment_status == 1) {
                    throw new Exception("Payment is done application cannot be updated!");
                }
                break;

            case (is_null($applicationdetails->user_id)):
                if ($user->id != $applicationdetails->citizen_id) {
                    throw new Exception("You are not the right user who applied!");
                }
                if ($applicationdetails->payment_status == 1) {
                    throw new Exception("Payment is done application cannot be updated!");
                }
                break;
        }

        # Checking the transaction details 
        $transactionDetails = $mRigTran->getTranByApplicationId($applicationId)->first();
        if ($transactionDetails) {
            throw new Exception("Transaction data exist application cannot be updated!");
        }
        return [
            "applicationDetails" => $applicationdetails,
        ];
    }

    /**
     *| collection Report
     */
    public function listCollection(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d',
            'toDate' => 'nullable|date_format:Y-m-d|after_or_equal:fromDate',
            'paymentMode'  => 'nullable'
        ]);
        if ($validator->fails()) {
            return  $validator->errors();
        }
        // return $req->all();
        try {
            $perPage = $req->perPage ? $req->perPage : 10;

            $paymentMode = null;
            if (!isset($req->fromDate))
                $fromDate = Carbon::now()->format('Y-m-d');                                                 // if date Is not pass then From Date take current Date
            else
                $fromDate = $req->fromDate;
            if (!isset($req->toDate))
                $toDate = Carbon::now()->format('Y-m-d');                                                  // if date Is not pass then to Date take current Date
            else
                $toDate = $req->toDate;

            if ($req->paymentMode) {
                $paymentMode = $req->paymentMode;
            }
            $mRigPayment = new RigTran();
            $data = $mRigPayment->listCollections($fromDate, $toDate,);                              // Get Shop Payment collection between givrn two dates
            if ($req->paymentMode != 0)
                $data = $data->where('rig_trans.payment_mode', $req->paymentMode);
            if ($req->auth['user_type'] == 'JSK' || $req->auth['user_type'] == 'TC')
                $data = $data->where('rig_trans.emp_dtl_id', $req->auth['id']);

            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                'collectAmount' => $paginator->sum('amount')
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            // $perPage = $req->get('per_page', 10);
            // $list = $data->paginate($perPage);
            // $list['collectAmount'] = $data->sum('amount');
            return responseMsgs(true, "Rig Collection List Fetch Succefully !!!", $list, "055017", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055017", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }
    /**
     * |---------------------------- Get Document Lists To Upload ----------------------------|
     * | Doc Upload for the Workflow
        | Serial No : 0
        | Working
     */
    public function getDocToUpload(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mRigActiveRegistration     = new RigActiveRegistration();
            $petApplicationId           = $req->applicationId;

            $refPetApplication = $mRigActiveRegistration->getRigApplicationById($petApplicationId)->first();                      // Get Pet Details
            if (is_null($refPetApplication)) {
                throw new Exception("Application Not Found for respective ($petApplicationId) id!");
            }
            // check if the respective is working on the front end
            // $this->checkAutheriseUser($req);
            $documentList = $this->getPetDocLists($refPetApplication);
            $petTypeDocs['listDocs'] = collect($documentList)->map(function ($value) use ($refPetApplication) {
                return $this->filterDocument($value, $refPetApplication)->first();
            });
            $totalDocLists = collect($petTypeDocs);
            $totalDocLists['docUploadStatus']   = $refPetApplication->doc_upload_status;
            $totalDocLists['docVerifyStatus']   = $refPetApplication->doc_verify_status;
            $totalDocLists['ApplicationNo']     = $refPetApplication->application_no;
            $totalDocLists['paymentStatus']     = $refPetApplication->payment_status;
            return responseMsgs(true, "", remove_null($totalDocLists), "010203", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    /**
     * | get the renewal application details according to registration Id
        | Serial No :
        | Under Con
     */
    public function getRenewalApplicationDetails(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'registrationId' => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $viewRenewButton            = false;
            $applicationId              = $req->registrationId;
            $mRigRenewalRegistration    = new RigActiveRegistration();
            $mRigTran                   = new RigTran();

            # Application detial 
            $renewalApplicationDetails = $mRigRenewalRegistration->getRigRenewalApplicationById($applicationId)
                ->where('rig_active_registrations.status', '<>', 0)                                                       // Static
                ->where('rig_active_registrations.renewal', 1)                                                       // Static
                ->first();
            if (is_null($renewalApplicationDetails)) {
                throw new Exception("application Not found!");
            }
            # Get Transaction details 
            $tranDetails = $mRigTran->getTranByApplicationId($renewalApplicationDetails->id)->first();
            if (!$tranDetails) {
                throw new Exception("Transaction details not found there is some error in data !");
            }

            # Return Details 
            $renewalApplicationDetails['transactionDetails']    = $tranDetails;
            $renewalApplicationDetails['viewRenewalButton']     = $viewRenewButton;
            return responseMsgs(true, "Listed application details!", remove_null($renewalApplicationDetails), "", "01", responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01",  responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
