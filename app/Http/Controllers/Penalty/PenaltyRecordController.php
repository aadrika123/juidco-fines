<?php

namespace App\Http\Controllers\Penalty;

use App\DocUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\InfractionRecordingFormRequest;
use App\IdGenerator\IdGeneration;
use App\Models\AdvActiveAgency;
use App\Models\AdvActivePrivateland;
use App\Models\AdvActiveSelfadvertisement;
use App\Models\AdvActiveVehicle;
use App\Models\AdvAgency;
use App\Models\AdvMarTransaction;
use App\Models\AdvPrivateland;
use App\Models\AdvSelfadvertisement;
use App\Models\AdvVehicle;
use App\Models\MarActiveBanquteHall;
use App\Models\MarActiveDharamshala;
use App\Models\MarActiveHostel;
use App\Models\MarActiveLodge;
use App\Models\MarDharamshala;
use App\Models\MarHostel;
use App\Models\MarLodge;
use App\Models\MarToll;
use App\Models\MarTollPayment;
use App\Models\Master\Section;
use App\Models\Master\UlbMaster;
use App\Models\Master\Violation;
use App\Models\MMarket;
use App\Models\Payment\RazorpayResponse;
use App\Models\PenaltyChallan;
use App\Models\PenaltyDocument;
use App\Models\PenaltyFinalRecord;
use App\Models\PenaltyRecord;
use App\Models\PenaltyTransaction;
use App\Models\PetActiveRegistration;
use App\Models\PetApprovedRegistration;
use App\Models\PetTran;
use App\Models\Rig\RigActiveRegistration;
use App\Models\Rig\RigTran;
use App\Models\Shop;
use App\Models\ShopPayment;
use App\Models\StBooking;
use App\Models\StCancelledBooking;
use App\Models\WfRoleusermap;
use App\Models\WfWorkflow;
use App\Models\WfWorkflowrolemap;
use App\Models\WtBooking;
use App\Models\WtCancellation;
use App\Pipelines\FinePenalty\SearchByApplicationNo;
use App\Pipelines\FinePenalty\SearchByChallan;
use App\Pipelines\FinePenalty\SearchByMobile;
use App\Traits\Fines\FinesTrait;
use App\Traits\Workflow\Workflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use Exception;

/**
 * =======================================================================================================
 * ===================         Created By : Umesh Kumar         ==========================================
 * ===================         Created On : 20-09-2023          ==========================================
 * =======================================================================================================
 * ===================         Modified By : Mrinal Kumar       ==========================================
 * ===================         Modified On : 22-09-2023         ==========================================
 * =======================================================================================================
 * | Status : Closed
 */

class PenaltyRecordController extends Controller
{

    use FinesTrait;
    use Workflow;
    private $mPenaltyRecord;

    public function __construct()
    {
        $this->mPenaltyRecord = new PenaltyRecord();
    }

    /**
     * | Penalty Application Apply
     * | API Id : 0601
     * | Query Run Time: ~305ms
     */
    public function store(InfractionRecordingFormRequest $req)
    {
        try {
            $apiId            = "0601";
            $version          = "01";
            $mSection         = new Section();
            $mViolation       = new Violation();
            $mWfWorkflow      = new WfWorkflow();
            $mPenaltyDocument = new PenaltyDocument();
            $wfMasterId         = Config::get('constants.WF_MASTER_ID');
            $applicationIdParam = Config::get('constants.ID_GENERATION_PARAMS.APPLICATION');
            $user = authUser($req);
            $ulbId = $user->ulb_id;

            $violationDtl = $mViolation->violationById($req->violationId);
            if (!$violationDtl)
                throw new Exception("Provide Valid Violation Id");

            $penaltyAmount = $violationDtl->penalty_amount;

            if ($req->categoryTypeId == 1)
                $penaltyAmount = $this->checkRickshawCondition($req);              #_Check condition for E-Rickshaw

            $wfWorkflow    =  $mWfWorkflow->getWorklow($ulbId, $wfMasterId);
            if (!$wfWorkflow)
                throw new Exception("Workflow not mapped to respective ulb");
            $refInitiatorRoleId = $this->getInitiatorId($wfWorkflow->id);                                // Get Current Initiator ID
            $initiatorRole = collect(DB::select($refInitiatorRoleId))->first();
            $sectionId = $violationDtl->section_id;
            $section = $mSection->sectionById($sectionId)->violation_section;

            $req->merge([
                'penaltyAmount' => $penaltyAmount,
                'initiatorRoleId' => $initiatorRole->role_id,
                'workflowId' => $wfWorkflow->id,
                'ulbId' => $ulbId,
            ]);

            DB::beginTransaction();
            $idGeneration = new IdGeneration($applicationIdParam, $ulbId, $section, 0);
            $applicationNo = $idGeneration->generate();
            $metaReqs = $this->generateRequest($req, $applicationNo);
            $metaReqs['challan_type'] = "Via Verification";
            $metaReqs['user_id'] = $user->id;

            $data = $this->mPenaltyRecord->store($metaReqs);
            if ($req->file('photo')) {
                // $req->challanType = "Via Verification";
                $req->merge(["challanType" => "Via Verification"]);
                $metaReqs['documents'] = $mPenaltyDocument->storeDocument($req, $data->id, $data->application_no);
            }

            DB::commit();
            return responseMsgs(true, "Records Added Successfully", $data, $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Get Record By Id
     * | API Id : 0602
     */
    public function show(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|numeric'
        ]);

        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0602";
            $version = "01";
            $docUrl = Config::get('constants.DOC_URL');
            $docUpload = new DocUpload;
            $docByReference = "";
            $penaltyDetails = $this->mPenaltyRecord->recordDetail()
                //query for chalan no
                ->where('penalty_applied_records.id', $req->id)
                ->first();

            if (!$penaltyDetails)
                throw new Exception("Data Not Found");

            $document = PenaltyDocument::select(
                'id',
                'document_name',
                'reference_no'
                // DB::raw("concat('$docUrl/',penalty_documents.document_path) as geo_tagged_image")
            )
                ->where('penalty_documents.applied_record_id', $penaltyDetails->id)
                ->where('penalty_documents.challan_type', 'Via Verification')
                ->get();

            if (collect($document)->isNotEmpty())
                $docByReference = $docUpload->getDocUrl($document);           #_Calling BLL for Document Path from DMS

            $data['penaltyDetails'] = $penaltyDetails;
            $data['document'] = $document;

            return responseMsgs(true, "View Records", $data, $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Retrieve Only Active Records
     * | API Id : 0603
     */
    public function activeAll(Request $req)
    {
        try {
            $apiId = "0603";
            $version = "01";
            $perPage = $req->perPage ?? 10;
            $user    = authUser($req);
            $ulbId = $user->ulb_id;

            $recordData = $this->mPenaltyRecord->recordDetail()
                ->where('penalty_applied_records.status', 1)
                ->where('penalty_applied_records.ulb_id', $ulbId);

            $penaltyDetails = app(Pipeline::class)
                ->send($recordData)
                ->through([
                    SearchByApplicationNo::class,
                    SearchByMobile::class
                ])->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "View All Active Records", $penaltyDetails, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Delete Record
     * | API Id : 0604
     */
    public function delete(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0604";
            $version = "01";
            $metaReqs =  [
                'status' => 0
            ];
            $delete = $this->mPenaltyRecord::findOrFail($req->id);
            $delete->update($metaReqs);

            return responseMsgs(true, "Deleted Successfully", $req->id, $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Search by Application No
     * | API Id : 0605
     */
    public function searchByApplicationNo(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationNo' => 'required|string'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0605";
            $version = "01";
            $getData = $this->mPenaltyRecord->searchByAppNo($req);
            $perPage = $req->perPage ? $req->perPage : 10;
            $list    = $getData->paginate($perPage);

            return responseMsgs(true, "View Searched Records", $list, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }


    /**
     * ========================================================================================================
     * ===================         Modified By : Mrinal Kumar       ===========================================
     * ===================         Modified On : 22-09-2023         ===========================================
     * ========================================================================================================
     */

    /**
     * | Get Uploaded Document
     * | API Id : 0606
     */
    public function getUploadedDocuments(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|numeric'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0606";
            $version = "01";
            $mPenaltyDocument = new PenaltyDocument();
            $docUpload = new DocUpload;
            $applicationDtls = $this->mPenaltyRecord->find($req->applicationId);
            if (!$applicationDtls)
                throw new Exception("Application Not Found for this application Id");

            $document = $mPenaltyDocument->getDocument($applicationDtls);  // get record by id
            if (collect($document)->isEmpty())
                throw new Exception("Data Not Found");

            $show = $docUpload->getDocUrl($document);  // get record by id

            return responseMsgs(true, "View Records", $show, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Inbox List
     * | API Id : 0607
     */
    public function inbox(Request $req)
    {
        try {
            $apiId = "0607";
            $version = "01";
            $user = authUser($req);
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $mWfRoleusermap = new WfRoleusermap();
            $mPenaltyRecord = new PenaltyRecord();
            $perPage = $req->perPage ?? 10;

            $roleId = $mWfRoleusermap->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $list = $mPenaltyRecord->recordDetail()
                ->where('penalty_applied_records.status', 1)
                ->where('penalty_applied_records.ulb_id', $ulbId)
                ->whereIn('workflow_id', $workflowIds)
                ->whereIn('penalty_applied_records.current_role', $roleId);

            $inbox = app(Pipeline::class)
                ->send(
                    $list
                )
                ->through([
                    SearchByApplicationNo::class,
                    SearchByMobile::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "", remove_null($inbox), $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Penalty Details by Id
     * | API Id : 0608
     */
    public function penaltyDetails(Request $req)
    {
        $validator = Validator::make($req->all(), ['applicationId' => 'required|int']);
        if ($validator->fails())
            return validationError($validator);

        try {
            $apiId = "0608";
            $version = "01";
            $details = array();
            $mPenaltyRecord = new PenaltyRecord();
            // $mWorkflowTracks = new WorkflowTrack();
            // $mForwardBackward = new WorkflowMap();
            $details = $mPenaltyRecord->recordDetail()
                ->where('penalty_applied_records.status', 1)
                ->where('penalty_applied_records.id', $req->applicationId)
                ->first();

            if (!$details)
                throw new Exception("Application Not Found");

            // Data Array
            $basicDetails = $this->generateBasicDetails($details);
            $basicElement = [
                'headerTitle' => "Basic Details",
                'data' => $basicDetails
            ];

            $penaltyDetails = $this->generatePenaltyDetails($details);         // (Penalty Details) Trait function to get Penalty Details
            $penaltyElement = [
                'headerTitle' => "Violation Details",
                "data" => $penaltyDetails
            ];

            $addressDetails = $this->generateAddressDetails($details);
            $addressElement = [
                'headerTitle' => "Address Details",
                'data' => $addressDetails
            ];

            $fullDetailsData['application_no'] = $details->application_no;
            $fullDetailsData['payment_status'] = false;
            $fullDetailsData['challan_status'] = false;
            $fullDetailsData['apply_date'] = date('d-m-Y', strtotime($details->created_at));
            $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([$basicElement, $addressElement, $penaltyElement]);

            // Card Details
            $cardElement = $this->generateCardDtls($details);
            $fullDetailsData['fullDetailsData']['cardArray'] = $cardElement;

            // $levelComment = $mWorkflowTracks->getTracksByRefId($mRefTable, $req->applicationId);
            // $fullDetailsData['levelComment'] = $levelComment;

            $metaReqs['customFor'] = 'PENALTY';
            $metaReqs['wfRoleId'] = $details->current_role;
            $metaReqs['workflowId'] = $details->workflow_id;
            $req->request->add($metaReqs);

            // $forwardBackward = $mForwardBackward->getRoleDetails($req);
            // $fullDetailsData['roleDetails'] = collect($forwardBackward)['original']['data'];

            $fullDetailsData['timelineData'] = collect($req);

            return responseMsgs(true, "Penalty Details", $fullDetailsData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Approve Penalty
     * | API Id : 0609
     */
    public function approvePenalty(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|numeric'
        ]);
        if ($validator->fails())
            return validationError($validator);

        try {
            $apiId = "0609";
            $version = "01";
            $user = authUser($req);
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $mSection = new Section();
            $mViolation = new Violation();
            $mPenaltyRecord = new PenaltyRecord();
            $mPenaltyChallan = new PenaltyChallan();
            $mPenaltyFinalRecord = new PenaltyFinalRecord();
            $challanIdParam = Config::get('constants.ID_GENERATION_PARAMS.CHALLAN');

            $penaltyRecord = $mPenaltyRecord->recordDetail()
                ->where('penalty_applied_records.status', 1)
                ->where('penalty_applied_records.id', $req->id)
                ->first();

            if (!$penaltyRecord)
                throw new Exception("Record Not Found");

            $violationDtl = $mViolation->violationById($req->violationId);
            if (!$violationDtl)
                throw new Exception("Provide Valid Violation Id");

            $section       = $mSection->sectionById($violationDtl->section_id)->violation_section;
            $penaltyAmount = $violationDtl->penalty_amount;

            $finalRecordReqs = [
                'full_name'                   => $req->fullName,
                'mobile'                      => $req->mobile,
                'email'                       => $req->email,
                'holding_no'                  => $req->holdingNo,
                'street_address'              => $req->streetAddress,
                'city'                        => $req->city,
                'region'                      => $req->region,
                'postal_code'                 => $req->postalCode,
                'violation_id'                => $req->violationId,
                'amount'                      => $penaltyAmount,
                'previous_violation_offence'  => $req->previousViolationOffence,
                'applied_record_id'           => $req->id,
                'version_no'                  => 0,
                'application_no'              => $penaltyRecord->application_no,
                'current_role'                => $penaltyRecord->current_role,
                'workflow_id'                 => $penaltyRecord->workflow_id,
                'ulb_id'                      => $penaltyRecord->ulb_id,
                'challan_type'                => $penaltyRecord->challan_type,
                'category_type_id'            => $penaltyRecord->category_type_id,
                'ward_id'                     => $penaltyRecord->ward_id,
                'approved_by'                 => $userId,
                'guardian_name'               => $req->guardianName,
                'violation_place'             => $req->violationPlace,
                'remarks'                     => $req->remarks,
                'vehicle_no'                  => $req->vehicleNo,
                'applied_by'                  => $penaltyRecord->user_id,
            ];

            DB::beginTransaction();
            $idGeneration = new IdGeneration($challanIdParam, $penaltyRecord->ulb_id, $section, 0);
            $challanNo = $idGeneration->generate();
            $finalRecord = $mPenaltyFinalRecord->store($finalRecordReqs);
            $challanReqs = [
                'challan_no'        => $challanNo,
                'challan_date'      => Carbon::now(),
                'challan_time'      => Carbon::now(),
                'payment_date'      => $req->paymentDate,
                'penalty_record_id' => $finalRecord->id,
                'amount'            => $finalRecord->amount,
                'total_amount'      => $finalRecord->amount,
                'challan_type'      => $penaltyRecord->challan_type,
            ];

            $challanRecord = $mPenaltyChallan->store($challanReqs);
            $penaltyRecord->status = 2;
            $penaltyRecord->amount = $finalRecord->amount;
            $penaltyRecord->save();

            $data['id'] = $challanRecord->id;
            $data['challanNo'] = $challanRecord->challan_no;
            DB::commit();

            #_Whatsaap Message
            if (strlen($finalRecord->mobile) == 10) {

                if ($section == 602) {
                    $whatsapp2 = (Whatsapp_Send(
                        $finalRecord->mobile,
                        "juidco_fines_challan602",
                        [
                            "content_type" => "text",
                            [
                                $finalRecord->full_name ?? "Violator",
                                $challanRecord->challan_no,
                                $section,
                                $challanRecord->total_amount,
                                (($challanRecord->challan_date)->addDay(14))->format('d-m-Y'),
                                $challanRecord->id
                            ]
                        ]
                    ));
                } else {
                    $whatsapp2 = (Whatsapp_Send(
                        $finalRecord->mobile,
                        "juidco_fines_challan",
                        [
                            "content_type" => "text",
                            [
                                $finalRecord->full_name ?? "Violator",
                                $challanRecord->challan_no,
                                $section,
                                $challanRecord->total_amount,
                                (($challanRecord->challan_date)->addDay(14))->format('d-m-Y'),
                                $challanRecord->id
                            ]
                        ]
                    ));
                }
            }

            return responseMsgs(true, "", $data, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Recent Applications
     * | API Id : 0610
     */
    public function recentApplications(Request $req)
    {
        try {
            $apiId = "0610";
            $version = "01";
            $todayDate = now()->toDateString();
            $user = authUser($req);
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $mPenaltyRecord = new PenaltyRecord();

            $challanDtl =   $mPenaltyRecord->recordDetail()
                ->whereDate('penalty_applied_records.created_at', $todayDate)
                ->where('penalty_applied_records.user_id', $userId)
                ->orderbyDesc('penalty_applied_records.id')
                ->take(10)
                ->get();

            return responseMsgs(true, "Recent Applications", $challanDtl, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",              $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Recent Challans
     * | API Id : 0611
     */
    public function recentChallans(Request $req)
    {
        try {
            $apiId = "0611";
            $version = "01";
            $todayDate = Carbon::now();
            $mPenaltyChallan = new PenaltyChallan();
            $user = authUser($req);
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $challanDtl = $mPenaltyChallan->recentChallanDetails()
                ->where('challan_date', $todayDate)
                ->where('penalty_final_records.applied_by', $userId)
                // ->where('penalty_final_records.ulb_id', $ulbId)
                ->take(10)
                ->get();

            return responseMsgs(true, "Recent Challans", $challanDtl, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",          $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Search Challans
     * | API Id : 0612
     */
    public function searchChallan(Request $req)
    {
        try {
            $apiId = "0612";
            $version = "01";
            $challanExpiredDate = Carbon::now()->addDay(14)->toDateString();
            $perPage = $req->perPage ?? 10;
            $mPenaltyChallan = new PenaltyChallan();
            $user = authUser($req);
            $ulbId = $user->ulb_id;
            $challanDtl = $mPenaltyChallan->details()
                ->where('penalty_final_records.ulb_id', $ulbId);

            if ($req->challanType)
                $challanDtl = $challanDtl->where('penalty_final_records.challan_type', $req->challanType);

            $challanList = app(Pipeline::class)
                ->send($challanDtl)
                ->through([
                    SearchByApplicationNo::class,
                    SearchByMobile::class,
                    SearchByChallan::class
                ])->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "", $challanList,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Get Challan Details
     * | API Id : 0613
            review again
     */
    public function challanDetails(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'challanId' => 'required|numeric'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0613";
            $version = "01";
            $docUrl = Config::get('constants.DOC_URL');
            $todayDate = Carbon::now();
            $mPenaltyChallan = new PenaltyChallan();
            $mUlbMasters = new UlbMaster();
            $perPage = $req->perPage ?? 10;
            $docUpload = new DocUpload;
            $docByReference = "";
            // $user = authUser($req);

            $finalRecord = PenaltyChallan::select(
                'penalty_final_records.*',
                'penalty_final_records.id as application_id',
                'penalty_challans.*',
                'penalty_challans.id',
                'violations.violation_name',
                'sections.violation_section',
                'tran_no',
                'ward_name',
                'users.user_name',
                DB::raw(
                    "TO_CHAR(penalty_challans.challan_date,'DD-MM-YYYY') as challan_date,
                    TO_CHAR(penalty_challans.payment_date,'DD-MM-YYYY') as payment_date,
                    CASE 
                        WHEN signature IS NULL THEN ''
                            else
                        concat('$docUrl/',signature)
                END as signature",
                )
            )
                ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_challans.penalty_record_id')
                ->leftjoin('penalty_transactions', 'penalty_transactions.challan_id', 'penalty_challans.id')
                ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'penalty_final_records.ward_id')
                ->join('users', 'users.id', 'penalty_final_records.approved_by')
                ->join('violations', 'violations.id', 'penalty_final_records.violation_id')
                ->join('sections', 'sections.id', 'violations.section_id')
                ->where('penalty_challans.id', $req->challanId)
                ->first();

            if (!$finalRecord)
                throw new Exception("Final Record Not Found");

            $appliedRecordId =  $finalRecord->applied_record_id ?? $finalRecord->application_id;

            $document = PenaltyDocument::select(
                'reference_no'
                // DB::raw("concat('$docUrl/',penalty_documents.document_path) as geo_tagged_image")
            )
                ->where('penalty_documents.applied_record_id', $appliedRecordId)
                ->where('penalty_documents.challan_type', $finalRecord->challan_type)
                ->first();

            $data = collect($finalRecord)->merge($document);

            if ($document)
                $docByReference = $docUpload->getSingleDocUrl($document);           #_Calling BLL for Document Path from DMS

            if ($data->isEmpty())
                throw new Exception("No Data Found againt this challan.");

            if ($data['violation_section'] == 602)
                $data['challan_print_type'] = 1;
            else
                $data['challan_print_type'] = 0;

            $ulbDetails = $mUlbMasters->getUlbDetails($data['ulb_id']);

            $totalAmountInWord = getHindiIndianCurrency($data['total_amount']);
            $data['amount_in_words']  = $totalAmountInWord . ' मात्र';
            $data['geo_tagged_image'] = $docByReference['doc_path'] ?? "";
            $data['ulbDetails'] = $ulbDetails;

            return responseMsgs(true, "", $data,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Offline Challan Payment
     * | API Id : 0614
        condition of section fails if section is in words
     */
    public function offlinechallanPayment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'challanId'     => 'required|int',
            'paymentMode'   => 'required',
            'applicationId' => 'nullable|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0614";
            $version = "01";
            $mSection = new Section();
            $mViolation = new Violation();
            $mPenaltyTransaction = new PenaltyTransaction();
            $receiptIdParam = Config::get('constants.ID_GENERATION_PARAMS.RECEIPT');
            $challanDetails = PenaltyChallan::find($req->challanId);
            $penaltyDetails = PenaltyFinalRecord::find($challanDetails->penalty_record_id);
            $todayDate = Carbon::now();
            $user = authUser($req);

            if (!$penaltyDetails)
                throw new Exception("Application Not Found");
            if ($penaltyDetails->payment_status == 1)
                throw new Exception("Payment Already Done");
            if (!$challanDetails)
                throw new Exception("Challan Not Found");

            $ulbDtls = UlbMaster::find($penaltyDetails->ulb_id);
            $violationDtl  = $mViolation->violationById($penaltyDetails->violation_id);
            $sectionId     = $violationDtl->section_id;
            $section       = $mSection->sectionById($sectionId)->violation_section;

            DB::beginTransaction();
            $idGeneration  = new IdGeneration($receiptIdParam, $penaltyDetails->ulb_id, $section, 0);
            $transactionNo = $idGeneration->generate();
            $reqs = [
                "application_id" => $req->applicationId,
                "challan_id"     => $req->challanId,
                "tran_no"        => $transactionNo,
                "tran_date"      => $todayDate,
                "tran_by"        => $user->id,
                "payment_mode"   => strtoupper($req->paymentMode),
                "amount"         => $challanDetails->amount,
                "penalty_amount" => $challanDetails->penalty_amount,
                "total_amount"   => $challanDetails->total_amount,
                "ulb_id"         => $penaltyDetails->ulb_id,
            ];
            $tranDtl = $mPenaltyTransaction->store($reqs);
            $penaltyDetails->payment_status = 1;
            $penaltyDetails->save();

            $challanDetails->payment_date = $todayDate;
            $challanDetails->save();
            DB::commit();

            #_Whatsaap Message
            if (strlen($penaltyDetails->mobile) == 10) {

                $whatsapp2 = (Whatsapp_Send(
                    $penaltyDetails->mobile,
                    "juidco_fines_payment",
                    [
                        "content_type" => "text",
                        [
                            $penaltyDetails->full_name ?? "Violator",
                            $tranDtl->total_amount,
                            $challanDetails->challan_no,
                            $tranDtl->tran_no,
                            $ulbDtls->toll_free_no ?? 0000000000
                        ]
                    ]
                ));
            }

            return responseMsgs(true, "", $tranDtl,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Payment Receipt
     * | API Id : 0615
     */
    public function paymentReceipt(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'transactionNo' => 'required',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0615";
            $version = "01";
            $mUlbMasters = new UlbMaster();
            $mPenaltyTransaction = new PenaltyTransaction();
            $todayDate = Carbon::now();
            $tranDtl = $mPenaltyTransaction->tranDtl()
                ->where('tran_no', $req->transactionNo)
                ->first();
            if (collect($tranDtl)->isEmpty())
                throw new Exception("No Transaction Found");

            $ulbDetails = $mUlbMasters->getUlbDetails($tranDtl->ulb_id);

            $totalAmountInWord = getHindiIndianCurrency($tranDtl->total_amount);
            $tranDtl->amount_in_words = $totalAmountInWord . ' मात्र';
            $tranDtl->ulbDetails = $ulbDetails;

            return responseMsgs(true, "", $tranDtl,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | On Spot Challan
     * | API Id : 0616
     */
    public function onSpotChallan(InfractionRecordingFormRequest $req)
    {
        try {
            $apiId = "0616";
            $version = "01";
            $mSection            = new Section();
            $mViolation          = new Violation();
            $mWfWorkflow         = new WfWorkflow();
            $mPenaltyChallan     = new PenaltyChallan();
            $mPenaltyDocument    = new PenaltyDocument();
            $mPenaltyFinalRecord = new PenaltyFinalRecord();
            $wfMasterId          = Config::get('constants.WF_MASTER_ID');
            $challanIdParam      = Config::get('constants.ID_GENERATION_PARAMS.CHALLAN');
            $applicationIdParam  = Config::get('constants.ID_GENERATION_PARAMS.APPLICATION');
            $user = authUser($req);
            $ulbId = $user->ulb_id;
            $violationDtl = $mViolation->violationById($req->violationId);
            if (!$violationDtl)
                throw new Exception("Provide Valid Violation Id");
            $penaltyAmount = $violationDtl->penalty_amount;
            if ($req->categoryTypeId == 1)
                $penaltyAmount = $this->checkRickshawCondition($req);

            $wfWorkflow    =  $mWfWorkflow->getWorklow($ulbId, $wfMasterId);
            if (!$wfWorkflow)
                throw new Exception("Workflow not mapped to respective ulb");

            $refInitiatorRoleId = $this->getInitiatorId($wfWorkflow->id);                                // Get Current Initiator ID
            $initiatorRole = collect(DB::select($refInitiatorRoleId))->first();
            $sectionId     = $violationDtl->section_id;
            $section       = $mSection->sectionById($sectionId)->violation_section;

            $req->merge([
                'penaltyAmount' => $penaltyAmount,
                'initiatorRoleId' => $initiatorRole->role_id,
                'workflowId' => $wfWorkflow->id,
                'ulbId' => $ulbId,
            ]);

            DB::beginTransaction();
            $idGeneration  = new IdGeneration($applicationIdParam, $ulbId, $section, 0);
            $applicationNo = $idGeneration->generate();
            $metaReqs      = $this->generateRequest($req, $applicationNo);
            $metaReqs['approved_by'] = $user->id;
            $metaReqs['applied_by']  = $user->id;
            $metaReqs['challan_type'] = "On Spot";
            $finalRecord =  $mPenaltyFinalRecord->store($metaReqs);
            $idGeneration = new IdGeneration($challanIdParam, $finalRecord->ulb_id, $section, 0);
            $challanNo = $idGeneration->generate();

            if ($req->file('photo')) {
                // $req->challanType = "On Spot";
                $req->merge(["challanType" => "On Spot"]);
                $metaReqs['documents'] = $mPenaltyDocument->storeDocument($req, $finalRecord->id, $finalRecord->application_no);
            }

            $challanReqs = [
                'challan_no'        => $challanNo,
                'challan_date'      => Carbon::now(),
                'challan_time'      => Carbon::now(),
                'payment_date'      => $req->paymentDate,
                'penalty_record_id' => $finalRecord->id,
                'amount'            => $finalRecord->amount,
                'total_amount'      => $finalRecord->amount,
                'challan_type'      => "On Spot",
            ];

            $challanRecord = $mPenaltyChallan->store($challanReqs);
            $futureDate = $challanRecord->challan_date->format('Y-m-d');
            $futureDate = $futureDate;

            $data['id'] = $challanRecord->id;
            $data['challanNo'] = $challanRecord->challan_no;
            DB::commit();

            #_Whatsaap Message
            if (strlen($finalRecord->mobile) == 10) {

                if ($section == 602) {
                    $whatsapp2 = (Whatsapp_Send(
                        $finalRecord->mobile,
                        "juidco_fines_challan602 ",
                        [
                            "content_type" => "text",
                            [
                                $finalRecord->full_name ?? "Violator",
                                $challanRecord->challan_no,
                                $section,
                                $challanRecord->total_amount,
                                (($challanRecord->challan_date)->addDay(14))->format('d-m-Y'),
                                $challanRecord->id
                            ]
                        ]
                    ));
                } else {
                    $whatsapp2 = (Whatsapp_Send(
                        $finalRecord->mobile,
                        "juidco_fines_challan",
                        [
                            "content_type" => "text",
                            [
                                $finalRecord->full_name ?? "Violator",
                                $challanRecord->challan_no,
                                $section,
                                $challanRecord->total_amount,
                                (($challanRecord->challan_date)->addDay(14))->format('d-m-Y'),
                                $challanRecord->id
                            ]
                        ]
                    ));
                }
            }

            return responseMsgs(true, "You have successfully generated challan against " . $finalRecord->full_name ?? "Violator", $data,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Violation Wise Report
     * | API Id : 0617
      shift query to model
     */
    public function violationData(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'required|date',
            'uptoDate' => 'required|date',
            'violationId' => 'nullable|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0617";
            $version = "01";
            $user = authUser($req);
            $ulbId = $user->ulb_id;
            $perPage = $req->perPage ?? 10;
            $todayDate =  $req->date ?? now()->toDateString();
            $data = PenaltyFinalRecord::select(
                'full_name',
                'mobile',
                'violation_id',
                'violation_place',
                'challan_no',
                'violation_name',
                'sections.violation_section',
                'penalty_challans.total_amount',
                'penalty_challans.id as challan_id'
            )
                ->join('violations', 'violations.id', 'penalty_final_records.violation_id')
                ->join('sections', 'sections.id', '=', 'violations.section_id')
                ->join('penalty_challans', 'penalty_challans.penalty_record_id', 'penalty_final_records.id')
                ->whereBetween('penalty_final_records.created_at', [$req->fromDate . ' 00:00:00', $req->uptoDate . ' 23:59:59'])
                ->where('penalty_challans.status', 1)
                ->where('penalty_final_records.ulb_id', $ulbId)
                ->orderbyDesc('penalty_final_records.id');

            if ($req->violationId) {
                $data = $data->where("violation_id", $req->violationId);
            }
            $data = $data
                ->paginate($perPage);

            return responseMsgs(true, "Violation Wise Challan Data", $data,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",                 $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Generated Challan Report
     * | API Id : 0618
       shift query to model
     */
    public function challanData(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate'        => 'required|date',
            'uptoDate'        => 'required|date',
            'userId'          => 'nullable|int',
            'challanCategory' => 'nullable|int',
            'challanType'     => 'nullable',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0618";
            $version = "01";
            $user = authUser($req);
            $userId = $req->userId;
            if ($req->type == 'mobile')
                $userId = $user->id;
            $ulbId = $user->ulb_id;
            $perPage = $req->perPage ?? 10;
            $todayDate =  $req->date ?? now()->toDateString();
            $data = PenaltyFinalRecord::select(
                'full_name',
                'penalty_final_records.mobile',
                'violation_place',
                'challan_no',
                'violation_name',
                'sections.violation_section',
                'penalty_challans.id as challan_id',
                'penalty_challans.total_amount',
                'penalty_challans.challan_date',
                'penalty_final_records.challan_type',
                'penalty_final_records.payment_status',
                'users.name as user_name',
                'category_type as challan_category',
            )
                ->join('violations', 'violations.id', 'penalty_final_records.violation_id')
                ->join('sections', 'sections.id', '=', 'violations.section_id')
                ->join('penalty_challans', 'penalty_challans.penalty_record_id', 'penalty_final_records.id')
                ->join('users', 'users.id', 'penalty_final_records.approved_by')
                ->join('challan_categories', 'challan_categories.id', 'penalty_final_records.category_type_id')
                ->whereBetween('penalty_challans.challan_date', [$req->fromDate, $req->uptoDate])
                ->where('penalty_challans.status', 1)
                ->where('penalty_final_records.ulb_id', $ulbId)
                ->orderbyDesc('penalty_challans.id');

            if ($req->challanType)
                $data = $data->where("penalty_challans.challan_type", $req->challanType);

            if ($req->challanCategory)
                $data = $data->where("category_type_id", $req->challanCategory);

            if ($userId)
                $data = $data->where("approved_by", $userId);

            $data = $data
                ->paginate($perPage);

            return responseMsgs(true, "Challan Generated Report", $data,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",              $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Collection Wise Report
     * | API Id : 0619
     shift query to model
     */
    public function collectionData(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate'        => 'required|date',
            'uptoDate'        => 'required|date',
            'paymentMode'     => 'nullable',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0619";
            $version = "01";
            $user = authUser($req);
            $userId = $req->userId;
            if ($req->type == 'mobile')
                $userId = $user->id;
            $ulbId = $user->ulb_id;
            $perPage = $req->perPage ?? 10;
            $data = PenaltyTransaction::select(
                '*'
                // 'full_name',
                // 'penalty_final_records.mobile',
                // 'violation_place',
                // 'challan_no',
                // 'violation_name',
                // 'penalty_challans.total_amount',
                // 'penalty_final_records.challan_type',
            )
                ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_transactions.application_id')
                ->join('violations', 'violations.id', 'penalty_final_records.violation_id')
                ->join('sections', 'sections.id', '=', 'violations.section_id')
                ->join('penalty_challans', 'penalty_challans.id', 'penalty_transactions.challan_id')
                ->where('penalty_final_records.ulb_id', $ulbId)
                ->where('penalty_transactions.status', 1)
                ->whereBetween('tran_date', [$req->fromDate, $req->uptoDate]);

            if ($req->challanType)
                $data = $data->where("challan_type", $req->challanType);

            if ($req->challanCategory)
                $data = $data->where("category_type_id", $req->challanCategory);

            if ($userId)
                $data = $data
                    ->where(function ($query) use ($userId) {
                        $query->where("approved_by", $userId)
                            ->orWhere(function ($query) use ($userId) {
                                $query->where("penalty_final_records.applied_by", $userId);
                            });
                    })
                    // ->where("penalty_final_records.applied_by", $userId)
                ;

            $data = $data
                ->paginate($perPage);

            if ($req->type == 'mobile')
                $totalAmount = PenaltyTransaction::select('penalty_transactions.total_amount')
                    ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_transactions.application_id')
                    ->where('penalty_transactions.status', 1)
                    ->whereBetween('tran_date', [$req->fromDate, $req->uptoDate])
                    ->where(function ($query) use ($userId) {
                        $query->where("approved_by", $userId)
                            ->orWhere(function ($query) use ($userId) {
                                $query->where("penalty_final_records.applied_by", $userId);
                            });
                    })
                    ->get();
            else
                $totalAmount = PenaltyTransaction::select('penalty_transactions.total_amount')
                    ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_transactions.application_id')
                    ->where('penalty_transactions.status', 1)
                    ->whereBetween('tran_date', [$req->fromDate, $req->uptoDate])
                    ->where('penalty_transactions.ulb_id', $ulbId)
                    // ->where(function ($query) use ($userId) {
                    //     $query->where("approved_by", $userId)
                    //         ->orWhere(function ($query) use ($userId) {
                    //             $query->where("penalty_final_records.applied_by", $userId);
                    //         });
                    // })
                    ->get();



            $totalAmount = collect($totalAmount)->sum('total_amount');
            $newData['data'] =  $data->items();
            $newData['total'] =  $data->total();
            $newData['total_amount'] =  $totalAmount;

            return responseMsgs(true, "", $newData,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Comparison Report
     * | API Id : 0620
     */
    public function comparisonReport(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId'        => 'required',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0620";
            $version = "01";
            $mPenaltyRecord = new PenaltyRecord();
            $mPenaltyFinalRecord = new PenaltyFinalRecord();
            $user = authUser($req);
            $ulbId = $user->ulb_id;
            $finalRecord = $mPenaltyFinalRecord->recordDetail()
                ->selectRaw('total_amount')
                ->selectRaw('users.name as user_name')
                ->join('penalty_challans', 'penalty_challans.penalty_record_id', 'penalty_final_records.id')
                ->join('users', 'users.id', 'penalty_final_records.approved_by')
                ->where('penalty_final_records.ulb_id', $ulbId)
                ->where('penalty_final_records.id', $req->applicationId)
                ->first();
            if (!$finalRecord)
                throw new Exception("Final Record Not Found");

            $appliedRecord = $mPenaltyRecord->recordDetail()
                ->selectRaw('penalty_applied_records.amount')
                ->selectRaw('users.name as user_name')
                ->join('penalty_final_records', 'penalty_final_records.applied_record_id', 'penalty_applied_records.id')
                ->join('penalty_challans', 'penalty_challans.penalty_record_id', 'penalty_final_records.id')
                ->join('users', 'users.id', 'penalty_applied_records.user_id')
                ->where('penalty_applied_records.id', $finalRecord->applied_record_id)
                ->first();
            if (!$appliedRecord)
                throw new Exception("Applied Record Not Found");

            $data = $this->comparison($finalRecord, $appliedRecord);

            return responseMsgs(true, "Comparison Report", $data,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Generate Request for table penalty_applied_records
        static category_type
     */
    public function generateRequest($req, $applicationNo)
    {
        return [
            'full_name'                  => $req->fullName,
            'mobile'                     => $req->mobile,
            'email'                      => $req->email,
            'holding_no'                 => $req->holdingNo,
            'street_address'             => $req->address1,
            'city'                       => $req->city,
            'region'                     => $req->region,
            'postal_code'                => $req->postalCode,
            'violation_id'               => $req->violationId,
            'amount'                     => $req->penaltyAmount,
            'previous_violation_offence' => $req->previousViolationOffence ?? 0,
            'application_no'             => $applicationNo,
            'current_role'               => $req->initiatorRoleId,
            'workflow_id'                => $req->workflowId,
            'ulb_id'                     => $req->ulbId,
            'guardian_name'              => $req->guardianName,
            'violation_place'            => $req->violationPlace,
            'challan_type'               => $req->challanType,
            'category_type_id'           => $req->categoryTypeId ?? 2,
            'ward_id'                    => $req->wardId,
            'trade_license_no'           => $req->tradeLicenseNo,
        ];
    }

    /**
     * | Check Condition for E-Rickshaw
     */
    public function checkRickshawCondition($req)
    {
        $rickshawFine =  Config::get('constants.E_RICKSHAW_FINES');
        $appliedRecord =  PenaltyRecord::where('vehicle_no', $req->vehicleNo)
            ->where('status', 1)
            ->count();

        $finalRecord = PenaltyFinalRecord::where('vehicle_no', $req->vehicleNo)
            ->where('status', '<>', 1)
            ->count();

        $totalRecord = $appliedRecord + $finalRecord;

        if ($totalRecord == 5)
            throw new Exception("E-Rickshaw has been Seized");
        return $fine = $rickshawFine[$totalRecord];
    }

    /**
     * | Get Challan Details
     * | API Id : 0621
     */
    public function mobileChallanDetails(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'challanId' => 'required|numeric'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0621";
            $version = "01";
            $docUrl = Config::get('constants.DOC_URL');
            $todayDate = Carbon::now();
            $mPenaltyChallan = new PenaltyChallan();
            $perPage = $req->perPage ?? 10;
            $user = authUser($req);
            $docUpload = new DocUpload;
            $docByReference = "";

            $finalRecord = PenaltyChallan::select(
                'penalty_final_records.*',
                'penalty_final_records.id as application_id',
                'penalty_challans.*',
                'penalty_challans.id',
                'violations.violation_name',
                'sections.violation_section',
                'penalty_transactions.payment_mode',
                'tran_no',
                'ward_name',
                DB::raw(
                    "TO_CHAR(penalty_challans.challan_date,'DD-MM-YYYY') as challan_date,
                    TO_CHAR(penalty_challans.payment_date,'DD-MM-YYYY') as payment_date",
                )
            )
                ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_challans.penalty_record_id')
                ->leftjoin('penalty_transactions', 'penalty_transactions.challan_id', 'penalty_challans.id')
                ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'penalty_final_records.ward_id')
                ->join('violations', 'violations.id', 'penalty_final_records.violation_id')
                ->join('sections', 'sections.id', 'violations.section_id')
                ->where('penalty_challans.id', $req->challanId)
                ->first();

            if (!$finalRecord)
                throw new Exception("Final Record Not Found");

            $appliedRecordId =  $finalRecord->applied_record_id ?? $finalRecord->application_id;

            $document = PenaltyDocument::select(
                'id',
                'document_name',
                'reference_no',
                // DB::raw("concat('$docUrl/',penalty_documents.document_path) as geo_tagged_image")
            )
                ->where('penalty_documents.applied_record_id', $appliedRecordId)
                ->where('penalty_documents.challan_type', $finalRecord->challan_type)
                ->get();

            if (collect($document)->isNotEmpty())
                $docByReference = $docUpload->getDocUrl($document);           #_Calling BLL for Document Path from DMS

            $data['challanDetails'] = $finalRecord;
            $data['document'] = $docByReference;

            $totalAmountInWord = getHindiIndianCurrency($finalRecord->total_amount);
            $data['challanDetails']['amount_in_words'] = $totalAmountInWord . ' मात्र';

            return responseMsgs(true, "", $data,  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Get Record By Id
     * | API Id : 0622
     * | Version 2 of ApiId = 0602
     */
    public function showV2(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|numeric'
        ]);

        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0622";
            $version = "01";
            $penaltyDetails = $this->mPenaltyRecord->recordDetail()
                ->where('penalty_applied_records.id', $req->id)
                ->first();

            if (!$penaltyDetails)
                throw new Exception("Data Not Found");

            return responseMsgs(true, "View Records", $penaltyDetails,  $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Citizen Challan Search
     * | Api Id : 0623
     */
    public function citizenSearchChallan(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationNo' => 'nullable|required_without_all:mobile,challanNo',
            'challanNo'     => 'nullable|required_without_all:applicationNo,mobile',
            'mobile'        => 'nullable|required_without_all:applicationNo,challanNo',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0623";
            $version = "01";
            $perPage = $req->perPage ?? 10;
            $mPenaltyChallan = new PenaltyChallan();
            $challanDtl = $mPenaltyChallan->details();

            if (request()->has('applicationNo'))
                $challanDtl
                    ->where('application_no', request()->input('applicationNo'));

            if (request()->has('mobile'))
                $challanDtl
                    ->where('mobile', request()->input('mobile'));

            if (request()->has('challanNo'))
                $challanDtl
                    ->where('challan_no', request()->input('challanNo'));

            $challanList = $challanDtl->paginate($perPage);

            return responseMsgs(true, "", $challanList,       $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Get Tran No By Order Id
     * | Api Id : 0624
     */
    public function getTranNo(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'orderId'   => 'required',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0624";
            $version = "01";
            $perPage = $req->perPage ?? 10;

            $mRazorpayResponse =  new RazorpayResponse();
            $transactionDtl = $mRazorpayResponse->getTranNo($req);

            if (collect($transactionDtl)->isEmpty())
                throw new Exception("No Transaction Found");

            return responseMsgs(true, "", $transactionDtl,    $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Mini Dashboard
     */
    public function miniLiveDashboard(Request $req)
    {
        $ulbId = $req->ulbId;
        $todayDate = Carbon::now();
        $penaltyTransaction = PenaltyTransaction::whereDate('created_at', $todayDate)->where('status', 1);
        $penaltyChallan     = PenaltyChallan::where('challan_date', $todayDate)
            ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_challans.penalty_record_id')
            ->where('penalty_challans.status', 1);

        $wtTransaction   = WtBooking::where('payment_date', $todayDate)->where('status', 1);
        $wtBooking       = WtBooking::where('booking_date', $todayDate)->where('status', 1);
        $wtTodayDelivery = WtBooking::where('delivery_date', $todayDate)->where('status', 1);
        $wtDelivered     = WtBooking::where('delivery_date', $todayDate)->where('delivery_track_status', 2)->where('status', 1);
        $wtCancelledTrip    = WtBooking::where('delivery_track_status', 1)->where('status', 1);
        $wtCancelledBooking = WtCancellation::whereDate('cancel_date', $todayDate)->where('status', 1);

        $stTransaction     = StBooking::where('payment_date', $todayDate)->where('status', 1);
        $stBooking         = StBooking::where('booking_date', $todayDate)->where('status', 1);
        $stTodayDelivery   = StBooking::where('cleaning_date', $todayDate)->where('status', 1);
        $stDelivered       = StBooking::where('cleaning_date', $todayDate)->where('delivery_track_status', 2)->where('status', 1);
        $stCancelledTrip    = StBooking::where('delivery_track_status', 1)->where('status', 1);
        $stCancelledBooking = StCancelledBooking::where('cancel_date', $todayDate)->where('status', 1);


        $rigTransaction        = RigTran::where('tran_date', $todayDate)->where('status', 1);
        $refRigTRansaction     = RigTran::where('tran_date', $todayDate)->where('status', 1);
        $rigNewRegistration    = RigActiveRegistration::where('application_apply_date', $todayDate)->where('status', '<>', 0);
        $rigRenewal            = RigActiveRegistration::where('application_apply_date', $todayDate)->where('status', "<>", 0);

        $petNewRegistration     = PetActiveRegistration::where('application_apply_date', $todayDate)->where('renewal', 0)->where('status', 1);
        $petRenewalLicense      = PetActiveRegistration::where('application_apply_date', $todayDate)->where('renewal', 1)->where('status', 1);
        $petApprovedLicense     = PetApprovedRegistration::where('approve_date', $todayDate)->where('status', 1);
        $petCollectionAmt       = PetTran::where('tran_date', $todayDate)->where('status', 1);
        # shop and toll
        $shopAdd                 = Shop::where('apply_date', $todayDate)->where('status', 1);
        $tollAdd                 = MarToll::where('apply_date', $todayDate)->where('status', 1);
        $totalMarket             = MMarket::where('is_active', 1);
        $shopCollectionAmt       = ShopPayment::where('payment_date', $todayDate)->where('is_active', true);
        $tollCollectionAmt       = MarTollPayment::where('payment_date', $todayDate)->where('is_active', true);
        # Advetsiment
        $AdvActiveSelfadvertisement       = AdvActiveSelfadvertisement::where('application_date', $todayDate);
        $AdvSelfadvertisement             = AdvSelfadvertisement::where('application_date', $todayDate);
        $AdvActiveVehicle                 = AdvActiveVehicle::where('application_date', $todayDate);
        $AdvVehicle                       = AdvVehicle::where('application_date', $todayDate);
        $AdvActivePrivateland             = AdvActivePrivateland::where('application_date', $todayDate);
        $AdvPrivateland                   = AdvPrivateland::where('application_date', $todayDate);
        $AdvActiveAgency                  = AdvActiveAgency::where('application_date', $todayDate);
        $AdvAgency                        = AdvAgency::where('application_date', $todayDate);
        $advertisementTransaction         = AdvMarTransaction::where('transaction_date', $todayDate)->where('module_type', 'Advertisement');
        # Market
        $marActiveLodge                  = MarActiveLodge::where('application_date', $todayDate);
        $marLodge                        = MarLodge::where('application_date', $todayDate);
        $marActiveHostel                 = MarActiveHostel::where('application_date', $todayDate);
        $marHostel                       = MarHostel::where('application_date', $todayDate);
        $marActiveDharamshala            = MarActiveDharamshala::where('application_date', $todayDate);
        $marDharamshala                  = MarDharamshala::where('application_date', $todayDate);
        $marActiveBanquteHall            = MarActiveBanquteHall::where('application_date', $todayDate);
        $marketTransaction               = AdvMarTransaction::where('transaction_date', $todayDate)->where('module_type', 'Market');



        if ($ulbId) {
            $penaltyTransaction =  $penaltyTransaction->where('ulb_id', $ulbId);
            $wtTransaction      =  $wtTransaction->where('ulb_id', $ulbId);
            $stTransaction      =  $stTransaction->where('ulb_id', $ulbId);

            $wtBooking          =  $wtBooking->where('ulb_id', $ulbId);
            $stBooking          =  $stBooking->where('ulb_id', $ulbId);

            $wtDelivered         =  $wtDelivered->where('ulb_id', $ulbId);
            $stDelivered         =  $stDelivered->where('ulb_id', $ulbId);

            $wtTodayDelivery    = $wtTodayDelivery->where('ulb_id', $ulbId);
            $stTodayDelivery    = $stTodayDelivery->where('ulb_id', $ulbId);

            $wtCancelledBooking    = $wtCancelledBooking->where('ulb_id', $ulbId);
            $stCancelledBooking    = $stCancelledBooking->where('ulb_id', $ulbId);

            $wtCancelledTrip    = $wtCancelledTrip->where('ulb_id', $ulbId);
            $stCancelledTrip    = $stCancelledTrip->where('ulb_id', $ulbId);

            $penaltyChallan     =  $penaltyChallan->where('penalty_final_records.ulb_id', $ulbId);

            $rigTransaction     =  $rigTransaction->where('ulb_id', $ulbId);
            $refRigTRansaction    =  $refRigTRansaction->where('ulb_id', $ulbId);
            $rigNewRegistration =  $rigNewRegistration->where('ulb_id', $ulbId);
            $rigRenewal         =  $rigRenewal->where('ulb_id', $ulbId);

            $petNewRegistration     = $petNewRegistration->where('ulb_id', $ulbId);
            $petRenewalLicense      = $petRenewalLicense->where('ulb_id', $ulbId);
            $petApprovedLicense     = $petApprovedLicense->where('ulb_id', $ulbId);
            $petCollectionAmt       = $petCollectionAmt->where('ulb_id', $ulbId);

            $shopAdd                 = $shopAdd->where('ulb_id', $ulbId);
            $tollAdd                 = $tollAdd->where('ulb_id', $ulbId);
            $shopCollectionAmt       = $shopCollectionAmt->where('ulb_id', $ulbId);
            $tollCollectionAmt       = $tollCollectionAmt->where('ulb_id', $ulbId);
            $totalMarket             = $totalMarket->where('ulb_id', $ulbId);
            # Advertisement
            $AdvActiveSelfadvertisement   = $AdvActiveSelfadvertisement->where('ulb_id', $ulbId);
            $AdvSelfadvertisement         = $AdvSelfadvertisement->where('ulb_id', $ulbId);
            $AdvActiveVehicle             = $AdvActiveVehicle->where('ulb_id', $ulbId);
            $AdvVehicle                   = $AdvVehicle->where('ulb_id', $ulbId);
            $AdvActivePrivateland         = $AdvActivePrivateland->where('ulb_id', $ulbId);
            $AdvPrivateland               = $AdvPrivateland->where('ulb_id', $ulbId);
            $AdvActiveAgency              = $AdvActiveAgency->where('ulb_id', $ulbId);
            $AdvAgency                    = $AdvAgency->where('ulb_id', $ulbId);
            $advertisementTransaction     = $advertisementTransaction->where('ulb_id', $ulbId);
            # Market
            $marActiveLodge                  = $marActiveLodge->where('ulb_id', $ulbId);
            $marLodge                        = $marLodge->where('ulb_id', $ulbId);
            $marActiveHostel                 = $marActiveHostel->where('ulb_id', $ulbId);
            $marHostel                       = $marHostel->where('ulb_id', $ulbId);
            $marActiveDharamshala            = $marActiveDharamshala->where('ulb_id', $ulbId);
            $marDharamshala                  = $marDharamshala->where('ulb_id', $ulbId);
            $marActiveBanquteHall            = $marActiveBanquteHall->where('ulb_id', $ulbId);
            $marDharamshala                  = $marDharamshala->where('ulb_id', $ulbId);
            $marketTransaction               = $marketTransaction->where('ulb_id', $ulbId);
        }

        $penaltyChallanCount  =  $penaltyChallan->count();
        $penaltyCollectionAmt = $penaltyTransaction->sum('total_amount');
        $wtCollectionAmt = $wtTransaction->sum('payment_amount');
        $stCollectionAmt = $stTransaction->sum('payment_amount');

        $wtBooking          =  $wtBooking->count();
        $stBooking          =  $stBooking->count();

        $wtDelivered        =  $wtDelivered->count();
        $stDelivered        =  $stDelivered->count();

        $wtTodayDelivery = $wtTodayDelivery->count();
        $stTodayDelivery = $stTodayDelivery->count();

        $wtCancelledBooking = $wtCancelledBooking->count();
        $stCancelledBooking = $stCancelledBooking->count();

        $wtCancelledTrip    = $wtCancelledTrip->count();
        $stCancelledTrip    = $stCancelledTrip->count();

        $totalChallanAmount    =  $penaltyChallan->sum('total_amount');
        $unpaidPenaltyAmount   =  $penaltyChallan->whereNull('payment_date')->sum('total_amount');

        # Rig Registration  
        $rigCollection        =   $rigTransaction->sum('amount');
        $rigRegistration      =   $rigNewRegistration->where('application_type', 'New_Apply')->count();
        $rigRenewal           =   $rigRenewal->where('application_type', 'Renewal')->count();

        # Rig collection amount
        $rigNewRegsitrationAmt  = $rigTransaction->where('tran_type', 'New_Apply')->sum('amount');
        $rigRenewRegistration   = $refRigTRansaction->where('tran_type', 'Renewal')->sum('amount');

        # Pet
        $petNewRegistration     = $petNewRegistration->count();
        $petRenewalLicense      = $petRenewalLicense->count();
        $petApprovedLicense     = $petApprovedLicense->count();
        $petCollectionAmt       = $petCollectionAmt->sum('amount');

        # Mar Shop and Toll
        $shopAdd     = $shopAdd->count();
        $tollAdd     = $tollAdd->count();
        $totalMarket     = $totalMarket->count();
        $shopCollectionAmt       = $shopCollectionAmt->sum('amount');
        $tollCollectionAmt       = $tollCollectionAmt->sum('amount');

        #  Advertisement
        $AdvActiveSelfadvertisement   = $AdvActiveSelfadvertisement->count();
        $AdvSelfadvertisement         = $AdvSelfadvertisement->count();
        $AdvActiveVehicle             = $AdvActiveVehicle->count();
        $AdvVehicle                   = $AdvVehicle->count();
        $AdvActivePrivateland         = $AdvActivePrivateland->count();
        $AdvPrivateland               = $AdvPrivateland->count();
        $AdvActiveAgency              = $AdvActiveAgency->count();
        $AdvAgency                    = $AdvAgency->count();
        $advertisementTransaction      = $advertisementTransaction->sum('amount');

        # Market
        $marActiveLodge                  = $marActiveLodge->count();
        $marLodge                        = $marLodge->count();
        $marActiveHostel                 = $marActiveHostel->count();
        $marHostel                       = $marHostel->count();
        $marActiveDharamshala            = $marActiveDharamshala->count();
        $marDharamshala                  = $marDharamshala->count();
        $marActiveBanquteHall            = $marActiveBanquteHall->count();
        $marketTransaction               = $marketTransaction->sum('amount');


        $data['fines_collection']   = $penaltyCollectionAmt;
        $data['challan_count']      = $penaltyChallanCount;
        $data['unpaid_penalty_amount']  = $unpaidPenaltyAmount;
        $data['total_penalty_amount']  = $totalChallanAmount;

        $data['wt_today_delivery']  = $wtTodayDelivery;
        $data['st_today_delivery']  = $stTodayDelivery;

        $data['wt_cancelled_delivery']  = $wtCancelledBooking + $wtCancelledTrip;
        $data['st_cancelled_delivery']  = $stCancelledBooking + $stCancelledTrip;

        $data['wt_collection']      = $wtCollectionAmt;
        $data['wt_booking']         = $wtBooking;
        $data['wt_delivered']       = $wtDelivered;
        $data['st_collection']      = $stCollectionAmt;
        $data['st_booking']         = $stBooking;
        $data['st_trip_count']      = $stDelivered;

        $data['rig_collection']     = round($rigCollection);
        $data['rig_new_reg_count']  = $rigRegistration;
        $data['rig_renewal_count']  = $rigRenewal;
        $data['rig_new_reg_amt']    = $rigNewRegsitrationAmt;
        $data['rig_renew_reg_amt']  = $rigRenewRegistration;

        $data['pet_new_registration']      = $petNewRegistration;
        $data['pet_renewal_license']       = $petRenewalLicense;
        $data['pet_approved_license']      = $petApprovedLicense;
        $data['pet_collection_amt']        = $petCollectionAmt;


        $data['shopp_add']                      = $shopAdd;
        $data['toll_add']                       = $tollAdd;
        $data['total_market']                   = $totalMarket;
        $data['shop_collection']                = $shopCollectionAmt;
        $data['toll_collection_amount']         = $tollCollectionAmt;
        $data['total_collection_amount']        = $tollCollectionAmt + $shopCollectionAmt;


        $data['adv_new_registration']               = $AdvActiveSelfadvertisement + $AdvActiveVehicle + $AdvActivePrivateland + $AdvActiveAgency;
        $data['adv_approve_registration']           = $AdvSelfadvertisement + $AdvVehicle + $AdvPrivateland + $AdvAgency;
        $data['mar_new_registration']               = $marActiveLodge + $marActiveHostel + $marActiveDharamshala + $marActiveBanquteHall;
        $data['mar_approve_registration']           = $marLodge + $marHostel + $marDharamshala + $marDharamshala;
        $data['total_market_collection']           = $marketTransaction;
        $data['total_adv_transactions']         = $advertisementTransaction;
        $data['total_collection']   = $penaltyCollectionAmt + $wtCollectionAmt + $stCollectionAmt + $rigCollection + $petCollectionAmt + $data['total_collection_amount'];

        return responseMsgs(true, "Mini Dashboard Data", $data, "0625", "01", responseTime(), $req->getMethod(), $req->deviceId);
    }

    /**
     * | Top Ulb Collection
        include today date as where condition
     */
    public function topUlbCollection(Request $req)
    {
        $todayDate = Carbon::now();
        // $penaltyTransaction = PenaltyTransaction::select(
        //     'ulb_id',
        //     'total_amount',
        //     DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
        // )
        //     // ->whereDate('created_at', $todayDate)
        //     ->where('status', 1)
        //     ->join('ulb_masters', 'ulb_masters.id', 'penalty_transactions.ulb_id');

        $penaltyTransaction = DB::table('ulb_masters')
            ->leftJoin('penalty_transactions', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'penalty_transactions.ulb_id')
                    ->where('penalty_transactions.tran_date', $todayDate)
                    ->where('penalty_transactions.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(penalty_transactions.total_amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount');

        // $wtTransaction = WtBooking::select(
        //     'ulb_id',
        //     'payment_amount as total_amount',
        //     DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
        // )
        //     // ->where('payment_date', $todayDate)
        //     ->where('status', 1)
        //     ->join('ulb_masters', 'ulb_masters.id', 'wt_bookings.ulb_id');
        $wtTransaction = DB::connection('pgsql_tanker')->table('ulb_masters')
            ->leftJoin('wt_bookings', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'wt_bookings.ulb_id')
                    ->where('wt_bookings.payment_date', $todayDate)
                    ->where('wt_bookings.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(wt_bookings.payment_amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount');

        // $stTransaction = StBooking::select(
        //     'ulb_id',
        //     'payment_amount as total_amount',
        //     DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
        // )
        //     // ->where('payment_date', $todayDate)
        //     ->where('status', 1)
        //     ->join('ulb_masters', 'ulb_masters.id', 'st_bookings.ulb_id');

        $stTransaction = DB::connection('pgsql_tanker')->table('ulb_masters')
            ->leftJoin('st_bookings', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'st_bookings.ulb_id')
                    ->where('st_bookings.payment_date', $todayDate)
                    ->where('st_bookings.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(st_bookings.payment_amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount');

        // $rigTransaction = RigTran::select(
        //     'ulb_id',
        //     'amount as total_amount',
        //     DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
        // )
        //     // ->where('tran_date', $todayDate)
        //     ->where('status', 1)
        //     ->join('ulb_masters', 'ulb_masters.id', 'rig_trans.ulb_id');

        $rigTransaction = DB::table('ulb_masters')
            ->leftJoin('rig_trans', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'rig_trans.ulb_id')
                    ->where('rig_trans.tran_date', $todayDate)
                    ->where('rig_trans.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(rig_trans.amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount');

        # Pet
        $petTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('pet_trans', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'pet_trans.ulb_id')
                    ->where('pet_trans.tran_date', $todayDate)
                    ->where('pet_trans.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(pet_trans.amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount')
            ->get();

        # advertisement
        $advertisementTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('adv_mar_transactions', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'adv_mar_transactions.ulb_id')
                    ->where('adv_mar_transactions.transaction_date', $todayDate)
                    ->where('adv_mar_transactions.status', 1)
                    ->where('adv_mar_transactions.module_type', 'Advertisement');
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(adv_mar_transactions.amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount')
            ->get();
        # market
        $marketTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('adv_mar_transactions', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'adv_mar_transactions.ulb_id')
                    ->where('adv_mar_transactions.transaction_date', $todayDate)
                    ->where('adv_mar_transactions.status', 1)
                    ->where('adv_mar_transactions.module_type', 'Market');
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(adv_mar_transactions.amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount')
            ->get();
        # shop
        $shopTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('mar_shop_payments', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'mar_shop_payments.ulb_id')
                    ->where('mar_shop_payments.payment_date', $todayDate)
                    ->where('mar_shop_payments.is_active', true);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(mar_shop_payments.amount), 0) as total_amount')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('total_amount')
            ->get();

        $waterSepticCollection = $wtTransaction->union($stTransaction)->get();
        $finesRigCollection = $penaltyTransaction->union($rigTransaction)->get();
        $combinedCollection = $waterSepticCollection->concat($finesRigCollection)->concat($petTransaction)->concat($advertisementTransaction)->concat($marketTransaction)->concat($shopTransaction);

        // return  $combinedCollection;

        $allCollection = collect($combinedCollection)->groupBy('ulb_name');
        // Map through each entity and calculate the sum of total_amount
        $sums = $allCollection->map(function ($entries) {
            return collect($entries)->sum('total_amount');
        });

        // Sort the sums in descending order and take the top 10
        $sortedSums = $sums->sortDesc()->take(10);

        $filtered = collect($sortedSums)->map(function ($value, $key) {
            return [
                'label' => $key,
                'value' => $value,
            ];
        });

        $data = json_decode($filtered, true);
        $data = array_values($data);

        return responseMsgs(true, "Top Ulb Collection", $data, "0626", "01", responseTime(), $req->getMethod(), $req->deviceId);
    }

    /**
     * | Today Collection Data
     */
    public function todayUlbCollection(Request $req)
    {
        $todayDate = Carbon::now();

        $finestopULBCollection = DB::table('ulb_masters')
            ->leftJoin('penalty_transactions', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'penalty_transactions.ulb_id')
                    ->where('penalty_transactions.tran_date', $todayDate)
                    ->where('penalty_transactions.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(penalty_transactions.total_amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();

        $rigMachinetopULBCollection = DB::table('ulb_masters')
            ->leftJoin('rig_trans', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'rig_trans.ulb_id')
                    ->where('rig_trans.tran_date', $todayDate)
                    ->where('rig_trans.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(rig_trans.amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();

        $waterTankertopULBCollection = DB::connection('pgsql_tanker')->table('ulb_masters')
            ->leftJoin('wt_bookings', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'wt_bookings.ulb_id')
                    ->where('wt_bookings.payment_date', $todayDate)
                    ->where('wt_bookings.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(wt_bookings.payment_amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();

        $septicTankertopULBCollection = DB::connection('pgsql_tanker')->table('ulb_masters')
            ->leftJoin('st_bookings', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'st_bookings.ulb_id')
                    ->where('st_bookings.payment_date', $todayDate)
                    ->where('st_bookings.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(st_bookings.payment_amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();

        # Pet
        $petTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('pet_trans', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'pet_trans.ulb_id')
                    ->where('pet_trans.tran_date', $todayDate)
                    ->where('pet_trans.status', 1);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(pet_trans.amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();
        # advertisement
        $advertisementTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('adv_mar_transactions', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'adv_mar_transactions.ulb_id')
                    ->where('adv_mar_transactions.transaction_date', $todayDate)
                    ->where('adv_mar_transactions.status', 1)
                    ->where('adv_mar_transactions.module_type', 'Advertisement');
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(adv_mar_transactions.amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();
        # market
        $marketTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('adv_mar_transactions', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'adv_mar_transactions.ulb_id')
                    ->where('adv_mar_transactions.transaction_date', $todayDate)
                    ->where('adv_mar_transactions.status', 1)
                    ->where('adv_mar_transactions.module_type', 'Market');
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(adv_mar_transactions.amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();
        #shop
        $shopTransaction = DB::connection('pgsql_advertisements')->table('ulb_masters')
            ->leftJoin('mar_shop_payments', function ($join) use ($todayDate) {
                $join->on('ulb_masters.id', '=', 'mar_shop_payments.ulb_id')
                    ->where('mar_shop_payments.payment_date', $todayDate)
                    ->where('mar_shop_payments.is_active', true);
            })
            ->select(
                'ulb_masters.id as ulb_id',
                DB::raw("split_part(ulb_masters.ulb_name, ' ', 1) as ulb_name"),
                DB::raw('COALESCE(SUM(mar_shop_payments.amount), 0) as daily_collection')
            )
            ->groupBy('ulb_masters.id', 'ulb_masters.ulb_name')
            ->orderByDesc('daily_collection')
            ->take(5)
            ->get();
        $data['fines']            = $finestopULBCollection;
        $data['water_tanker']     = $waterTankertopULBCollection;
        $data['septic_tanker']    = $septicTankertopULBCollection;
        $data['septic_tanker']    = $septicTankertopULBCollection;
        $data['rig_machine']      = $rigMachinetopULBCollection;
        $data['rig_machine']      = $rigMachinetopULBCollection;
        $data['pet_registration'] = $petTransaction;
        $data['advertisement']    = $advertisementTransaction;
        $data['marketTransaction'] = $marketTransaction;
        $data['shopTransaction']   = $shopTransaction;

        return responseMsgs(true, "Today Top ULB Collection", $data, "0627", "01", responseTime(), $req->getMethod(), $req->deviceId);
    }

    /**
     * | Test Whatsaap
     */
    public function testWhatsapp(Request $req)
    {
        $message = [
            "Mrinal",
            "500",
            "water tanker",
            "WT787878",
            "1800123456",
        ];
        return Whatsapp_Send(8797770238, "wt_booking_initiated", [
            "content_type" => "text",
            $message
        ]);
    }
}
