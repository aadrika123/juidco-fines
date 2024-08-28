<?php

namespace App\Http\Controllers\Rig;

use App\DocUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rig\RigPaymentReq;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Traits\Workflow\Workflow;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\rig\RigRegistrationReq;
use App\IdGenerator\IdGeneration;
use App\MicroServices\DocumentUpload;
use App\Models\IdGenerationParam;
use App\Models\Master\UlbMaster;
use App\Models\PenaltyDailycollectiondetail;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropFloor;
use App\Models\Property\PropProperty;
use App\Models\Rig\MRigFee;
use App\Models\Rig\RigActiveApplicant;
use App\Models\Rig\RigActiveRegistration;
use App\Models\Rig\RigApprovedRegistration;
use App\Models\Rig\RigChequeDtl;
use App\Models\Rig\RigDailycollection;
use App\Models\Rig\RigDailycollectiondetail;
use App\Models\Rig\RigRazorPayRequest;
use App\Models\Rig\RigRazorPayResponse;
use App\Models\Rig\RigRegistrationCharge;
use App\Models\Rig\RigRejectedRegistration;
use App\Models\Rig\RigTran;
use App\Models\Rig\RigTranDetail;
use App\Models\Rig\RigVehicleActiveDetail;
use App\Models\Rig\TempTransaction;
use App\Models\Rig\WfActiveDocument as RigWfActiveDocument;
use App\Models\Rig\WorkflowTrack;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Models\WfWorkflow;
use App\Models\WfWorkflowrolemap;
use  App\Models\Rig\WfActiveDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Razorpay\Api\Api;
use Barryvdh\DomPDF\Facade\PDF;

class RigPaymentController extends Controller
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
    private $_paymentMode;
    private $_PaymentUrl;
    private $_apiKey;
    private $_offlineVerificationModes;
    private $_offlineMode;

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
        $this->_paymentMode         = Config::get("rig.PAYMENT_MODE");
        $this->_PaymentUrl          = Config::get('constants.95_PAYMENT_URL');
        $this->_apiKey              = Config::get('rig.API_KEY_PAYMENT');
        $this->_offlineVerificationModes    = Config::get("rig.VERIFICATION_PAYMENT_MODES");
        $this->_offlineMode                 = Config::get("rig.OFFLINE_PAYMENT_MODE");
        # Database connectivity
        // $this->_DB_NAME     = "pgsql_property";
        // $this->_DB          = DB::connection($this->_DB_NAME);
        $this->_DB_NAME2    = "pgsql_master";
        $this->_DB2         = DB::connection($this->_DB_NAME2);
    }

    /**
     * | Save Razor Pay Request
     */
    public function initiatePayment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "applicationId" => "required",
        ]);

        if ($validator->fails())
            return validationError($validator);

        try {

            $apiId = "0701";
            $version = "01";
            $keyId        = Config::get('constants.RAZORPAY_KEY');
            $secret       = Config::get('constants.RAZORPAY_SECRET');
            $paymentUrl   = Config::get('constants.PAYMENT_URL');
            $rigModuleId  = $this->_rigModuleId;                                     #static Change it
            $mRazorpayReq = new RigRazorPayRequest();
            $api          = new Api($keyId, $secret);

            $rigDetails = RigActiveRegistration::find($req->applicationId);
            if (!$rigDetails)
                throw new Exception("Application not found");
            $chargeDetails = RigRegistrationCharge::where('application_id', $rigDetails->id)
                ->first();
            if (!$chargeDetails)
                throw new Exception("Application Not Found");
            if ($chargeDetails->payment_status == 1)
                throw new Exception("Payment Already Done");
            if (!$rigDetails)
                throw new Exception("Rig Not Found");

            $myRequest = [
                'amount'          => $chargeDetails->amount,
                'workflowId'      => $rigDetails->workflow_id,
                'id'              => $rigDetails->id,
                'departmentId'    => $rigModuleId
            ];
            $newRequest = $req->merge($myRequest);

            # Api Calling for OrderId
            $refResponse = Http::withHeaders([
                "api-key" => "eff41ef6-d430-4887-aa55-9fcf46c72c99"                             // Static
            ])
                ->withToken($req->bearerToken())
                ->post($paymentUrl . '/api/payment/generate-orderid', $newRequest);               // Static

            $orderData = json_decode($refResponse);
            if ($orderData->status == false) {
                throw new Exception(collect($orderData->message)->first());
            }

            if ($req->authRequired == true)
                $user = authUser($req);

            $mReqs = [
                "order_id"       => $orderData->data->orderId,
                "merchant_id"    => $req->merchantId,
                "related_id"     => $req->applicationId,
                "user_id"        => $user->id ?? 0,
                "workflow_id"    => $chargeDetails->workflow_id ?? 0,
                "amount"         => $chargeDetails->amount,
                "ulb_id"         => $rigDetails->ulb_id,
                "ip_address"     => getClientIpAddress()
            ];
            $data = $mRazorpayReq->store($mReqs);
            $orderData->data->user_id = $user->id ?? 0;

            return responseMsgs(true, "Order id generated", $orderData->data, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Save Razor Pay Response
     */
    public function saveRazorpayResponse(Request $req)
    {
        $idGeneration = new IdGenerationParam();
        try {
            $apiId = "0702";
            $version = "01";
            Storage::disk('public')->put($req->orderId . '.json', json_encode($req->all()));
            $mRazorpayReq        = new RigRazorPayRequest();
            $mRazorpayResponse   = new RigRazorPayResponse();
            $mRigTransaction     = new RigTran();
            $todayDate           = Carbon::now();
            $rigDetails          = RigActiveRegistration::find($req->id);
            $chargeDetails       = RigRegistrationCharge::where('application_id', $req->id)->where('status', 1)->first();
            $section             = 0;
            $payStatus           = 1;

            $receiptIdParam    = Config::get('rig.ID_GENERATION_PARAMS.RECEIPT');

            if ($req->authRequired == true)
                $user      = authUser($req);

            // $idGeneration  = new IdGeneration($receiptIdParam, $rigDetails->ulb_id, $section, 0);
            $transactionNo = $req->transactionNo;

            $paymentData = $mRazorpayReq->getPaymentRecord($req);

            if (collect($paymentData)->isEmpty())
                throw new Exception("Payment Data not available");
            if ($paymentData) {
                $mReqs = [
                    "request_id"      => $paymentData->id,
                    "order_id"        => $req->orderId,
                    "merchant_id"     => $req->mid,
                    "payment_id"      => $req->paymentId,
                    "related_id"      => $req->id,
                    "amount"          => $req->amount,
                    "ulb_id"          => $rigDetails->ulb_id,
                    "ip_address"      => getClientIpAddress(),
                    // "res_ref_no"      => $transactionNo,                         // flag
                    // "response_msg"    => $pinelabData['Response']['ResponseMsg'],
                    // "response_code"   => $pinelabData['Response']['ResponseCode'],
                    // "description"     => $req->description,
                ];

                $data = $mRazorpayResponse->store($mReqs);
            }
            DB::beginTransaction();
            $paymentData->payment_status = 1;
            $paymentData->save();

            # calling function for the modules
            $reqs = [
                "related_id"     => $req->id,
                "tran_no"        => $transactionNo,
                "tran_date"      => Carbon::now(),
                "emp_dtl_id"     => $user->id ?? 0,
                "payment_mode"   => strtoupper('ONLINE'),
                "amount"         => $chargeDetails->amount,
                "penalty_amount" => $chargeDetails->penalty_amount,
                "amount"         => $chargeDetails->amount,
                "verify_status"  => 1,
                "ulb_id"         => $rigDetails->ulb_id,
                "tran_type"      => $rigDetails->application_type,
                "ip_address"     => getClientIpAddress()
            ];

            $tranDtl = $mRigTransaction->store($reqs);
            $rigDetails->payment_status = 1;
            $rigDetails->save();

            $chargeDetails->paid_status = 1;
            $chargeDetails->save();
            DB::commit();
            $data->tran_no = $tranDtl->tran_no;

            return responseMsgs(true, "Data Saved", $data, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }




    /**
     * | Get data for payment Receipt
        | Serial No :
        | Under Con
     */
    public function generatePaymentReceipt(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'transactionNo' => 'required|',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $now            = Carbon::now();
            $toward         = "Rig Machine Registration Fee";
            $mRigTran       = new RigTran();
            $mUlbMater      = new UlbMaster();

            # Get transaction details according to trans no
            $transactionDetails = $mRigTran->getTranDetailsByTranNo($request->transactionNo)->first();
            if (!$transactionDetails) {
                throw new Exception("transaction details not found! for $request->transactionNo");
            }
            # check the transaction related details in related table
            $applicationDetails = $this->getApplicationRelatedDetails($transactionDetails);
            $ulbDetails         =  $mUlbMater->getUlbDetails($transactionDetails->ulb_id);

            $returnData = [
                "transactionNo" => $transactionDetails->tran_no,
                "todayDate"     => $now->format('d-m-Y'),
                "applicationNo" => $applicationDetails->application_no,
                "applicantName" => $applicationDetails->applicant_name,
                "paidAmount"    => $transactionDetails->amount,
                "toward"        => $toward,
                "paymentMode"   => $transactionDetails->payment_mode,
                "ulb"           => $applicationDetails->ulb_name,
                "paymentDate"   => Carbon::parse($transactionDetails->tran_date)->format('d-m-Y'),
                "address"       => $applicationDetails->address,
                "tokenNo"       => $transactionDetails->token_no,
                'type'          => $applicationDetails->type,
                "vehicleNo"     => $applicationDetails->vehicle_no,
                "vehicleFrom"     => $applicationDetails->vehicle_from,
                "vehicleName"     => $applicationDetails->vehicle_name,
                "ulb_address"     => $transactionDetails->address,
                "ulb_email"       => $transactionDetails->email,
                "ulbDetails"      =>  $ulbDetails

            ];
            return responseMsgs(true, 'payment Receipt!', $returnData, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Serch application from every registration table
        | Serial No 
        | Working
     */
    public function getApplicationRelatedDetails($transactionDetails)
    {
        $mRigActiveRegistration     = new RigActiveRegistration();
        $mRigApprovedRegistration   = new RigApprovedRegistration();
        $mRigRejectedRegistration   = new RigRejectedRegistration();

        # first level chain
        $refApplicationDetails = $mRigActiveRegistration->getApplicationById($transactionDetails->related_id)
            ->select(
                'ulb_masters.ulb_name',
                'rig_active_registrations.application_no',
                'rig_active_applicants.applicant_name',
                'rig_active_registrations.address',
                'rig_vehicle_active_details.vehicle_name',
                'rig_vehicle_active_details.vehicle_from',
                'rig_vehicle_active_details.vehicle_no',
            )->first();
        if (!$refApplicationDetails) {
            # Second level chain
            $refApplicationDetails = $mRigApprovedRegistration->getApproveDetailById($transactionDetails->related_id)
                ->select(
                    'ulb_masters.ulb_name',
                    'rig_approved_registrations.application_no',
                    'rig_approve_applicants.applicant_name',
                    'rig_approved_registrations.address',
                    'rig_approve_active_details.vehicle_name',
                    'rig_approve_active_details.vehicle_from',
                    'rig_approve_active_details.vehicle_no',
                )->first();
        }

        if (!$refApplicationDetails) {
            # Fourth level chain
            $refApplicationDetails = $mRigRejectedRegistration->getRejectedApplicationById($transactionDetails->related_id)
                ->select(
                    'ulb_masters.ulb_name',
                    'rig_rejected_registrations.application_no',
                    'rig_rejected_applicants.applicant_name',
                    'rig_rejected_registrations.address',
                )->first();
        }
        # Check the existence of final data
        if (!$refApplicationDetails) {
            throw new Exception("application details not found!");
        }
        return $refApplicationDetails;
    }


    /**
     * | Pay the registration charges in offline mode 
        | Serial no :
        | Under construction 
     */
    public function offlinePayment(RigPaymentReq $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['remarks' => 'nullable',]
        );
        if ($validated->fails())
            return validationError($validated);

        try {

            # Variable declaration
            $section                    = 0;
            $receiptIdParam             = Config::get("rig.PARAM_ID.RECEIPT");
            $user                       = authUser($req);
            $todayDate                  = Carbon::now();
            $epoch                      = strtotime($todayDate);
            $offlineVerificationModes   = $this->_offlineVerificationModes;
            $mRigTran                   = new RigTran();
            $docUpload                  = new DocUpload;
            $relativePath               = Config::get('rig.RIG_RELATIVE_PATH.REGISTRATION');
            $mWfActiveDocument          = new WfActiveDocument();

            # Check the params for checking payment method
            $payRelatedDetails  = $this->checkParamForPayment($req, $req->paymentMode);
            $ulbId              = $payRelatedDetails['applicationDetails']['ulb_id'];
            $wardId             = $payRelatedDetails['applicationDetails']['ward_id'];
            $tranType           = $payRelatedDetails['applicationDetails']['application_type'];
            $tranTypeId         = $payRelatedDetails['chargeCategory'];
            $mobileNo           = $payRelatedDetails['applicationDetails']['mobile_no'];
            $applicantName      = $payRelatedDetails['applicationDetails']['applicant_name'];
            $registrationNo     = $payRelatedDetails['applicationDetails']['registration_id'];
            $ulbName            = $payRelatedDetails['applicationDetails']['ulb_name'];
            $workflowId         = $payRelatedDetails['applicationDetails']['workflow_id'];
            $amount             = $payRelatedDetails['refRoundAmount'];

            DB::beginTransaction();
            # Generate transaction no 
            $idGeneration  = new IdGeneration($receiptIdParam,  $ulbId, $section, 0);
            $transactionNo = $idGeneration->generateId();
            # Water Transactions
            $req->merge([
                'empId'         => $user->id,
                'userType'      => $user->user_type,
                'todayDate'     => $todayDate->format('Y-m-d'),
                'tranNo'        => $transactionNo,
                'ulbId'         => $ulbId,
                'isJsk'         => true,
                'wardId'        => $wardId,
                'tranType'      => $tranType,                                                              // Static
                'tranTypeId'    => $tranTypeId,
                'amount'        => $payRelatedDetails['refRoundAmount'],
                'roundAmount'   => $payRelatedDetails['regAmount'],
                'tokenNo'       => $payRelatedDetails['applicationDetails']['ref_application_id'] . $epoch              // Here 
            ]);

            # Save the Details of the transaction
            $RigTrans = $mRigTran->saveTranDetails($req);

            # Save the Details for the Cheque,DD,nfet
            if (in_array($req['paymentMode'], $offlineVerificationModes)) {
                $req->merge([
                    'chequeDate'    => $req['chequeDate'],
                    'tranId'        => $RigTrans['transactionId'],
                    'applicationNo' => $payRelatedDetails['applicationDetails']['chargeCategory'],
                    'workflowId'    => $payRelatedDetails['applicationDetails']['workflow_id'],
                    'ref_ward_id'   => $payRelatedDetails['applicationDetails']['ward_id']
                ]);
                $this->postOtherPaymentModes($req);
            }

            // $this->saverigRequestStatus($req, $offlineVerificationModes, $payRelatedDetails['rigCharges'], $RigTrans['transactionId'], $payRelatedDetails['applicationDetails']);
            // $payRelatedDetails['applicationDetails']->payment_status = 1;
            // $payRelatedDetails['applicationDetails']->save();

            // # Rerive Data for license
            // $data = [
            //     "RegistrationNo"   => $payRelatedDetails['applicationDetails']['registration_id'],
            //     "AplicantName"     => $payRelatedDetails['applicationDetails']['applicant_name'],
            //     "approveDate"      => $payRelatedDetails['applicationDetails']['approve_date'],
            //     "approveEndDate"   => $payRelatedDetails['applicationDetails']['approve_end_date'],
            //     "vehicleNo"        => $payRelatedDetails['applicationDetails']['vehicle_no'],
            //     "mobileNo"         => $payRelatedDetails['applicationDetails']['mobile_no'],
            //     "applicationNo"    => $payRelatedDetails['applicationDetails']['application_no'],
            //     "applyDate"        => $payRelatedDetails['applicationDetails']['application_apply_date']
            // ];
            // $this->saveLisenceLetter($data, $req, $workflowId, $ulbId);
            DB::commit();

            #_Whatsaap Message
            if (strlen($mobileNo) == 10) {
                $Url = "https://jharkhandegovernance.com/rig/rig-payment-receipt/" . $transactionNo;
                $whatsapp2 = (Whatsapp_Send(
                    $mobileNo,
                    "juidco_rig_payment",
                    [
                        "content_type" => "text",
                        [
                            $applicantName ?? "",
                            $registrationNo,
                            $ulbName,
                            $amount,
                            $Url
                        ]
                    ]
                ));
            }
            $returnData = [
                "transactionNo" => $transactionNo
            ];

            return responseMsgs(true, "Paymet done!", $returnData, "", "01", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
     * |Save lisence Data
     */
    public function saveLisenceLetter($data, $req, $workflowId, $ulbId)
    {
        try {
            $user = collect(authUser($req));

            $docUpload                  = new DocUpload;
            $relativePath               = Config::get('rig.RIG_RELATIVE_PATH.REGISTRATION');
            $mWfActiveDocument          = new WfActiveDocument();

            $filename = $req->id . "-LICENSE" . '.' . 'pdf';
            $pdf = PDF::loadView('Rig_Machine_License', ["data" => $data]);

            $customPaper = [0, 0, 600, 900]; // 11 * 72 = 792, 17 * 72 = 1224
            $pdf->setPaper($customPaper, 'landscape');

            $url = "Uploads/Rig/License/" . $filename;
            $file = $pdf->output();
            Storage::put('public/' . $url, $file);

            // Prepare a temporary file for upload

            $tempPath = tempnam(sys_get_temp_dir(), 'license');
            file_put_contents($tempPath, $file);
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $filename,
                'application/pdf',
                null,
                true
            );

            $req->merge(['document' => $uploadedFile]);

            // Document Upload through DMS
            $imageName = $docUpload->upload($req);

            // Meta data for document upload
            $metaReqs = [
                'moduleId' => Config::get('workflow-constants.ADVERTISMENT_MODULE') ?? 15,
                'activeId' => $req->id,
                'workflowId' => $workflowId,
                'ulbId' =>  $ulbId,
                'relativePath' => $relativePath,
                'document' => $imageName,
                'doc_category' => $req->docCategory,
                'docCode' => 'Lisence',
                'ownerDtlId' => $req->ownerDtlId,
                'unique_id' => $imageName['data']['uniqueId'] ?? null,
                'reference_no' => $imageName['data']['ReferenceNo'] ?? null,
            ];

            // Save document metadata in wfActiveDocuments
            $mWfActiveDocument->postDocuments(new Request($metaReqs), $user);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }


    /**
     * | Save the status in active consumer table, transaction, 
        | Serial No :
        | Working
     */
    public function saveRigRequestStatus($request, $offlinePaymentVerModes, $charges, $waterTransId, $activeConRequest)
    {
        $mRigTranDetail         = new RigTranDetail();
        $mRigActiveRegistration = new RigActiveRegistration();
        $mRigTran               = new RigTran();
        $applicationId          = $activeConRequest->ref_application_id;

        if (in_array($request['paymentMode'], $offlinePaymentVerModes)) {
            $charges->paid_status = 2;                                                      // Static
            $refReq = [
                "payment_status" => 2,                                                      // Update Application Payment Status // Static
            ];
            $tranReq = [
                "verify_status" => 2
            ];                                                                              // Update Charges Paid Status // Static
            $mRigTran->saveStatusInTrans($waterTransId, $tranReq);
            $mRigActiveRegistration->saveApplicationStatus($applicationId, $refReq);
        } else {
            $charges->paid_status = 1;                                                      // Update Charges Paid Status // Static
            $refReq = [
                "payment_status"    => 1,
                "current_role_id"   => $activeConRequest->initiator_role_id
            ];
            $mRigActiveRegistration->saveApplicationStatus($applicationId, $refReq);
        }
        $charges->save();                                                                   // ❕❕ Save Charges ❕❕

        $refTranDetails = [
            "id"            => $applicationId,
            "refChargeId"   => $charges->id,
            "roundAmount"   => $request->roundAmount,
            "tranTypeId"    => $request->tranTypeId
        ];
        # Save Trans Details                                                   
        $mRigTranDetail->saveTransDetails($waterTransId, $refTranDetails);
    }


    /**
     * | Check the details and the function for the payment 
     * | return details for payment process
     * | @param req
        | Serial No: 
        | Under Construction
     */
    public function checkParamForPayment($req, $paymentMode)
    {
        $applicationId          = $req->id;
        $confPaymentMode        = $this->_paymentMode;
        $confApplicationType    = $this->_applicationType;
        $mRigActiveRegistration = new RigActiveRegistration();
        $mRigRegistrationCharge = new RigRegistrationCharge();
        $mRigTran               = new RigTran();

        # Application details and Validation
        $applicationDetail = $mRigActiveRegistration->getRigApplicationById($applicationId)
            ->where('rig_vehicle_active_details.status', "<>", 0)
            ->where('rig_active_applicants.status', "<>", 0)
            ->first();
        if (is_null($applicationDetail)) {
            throw new Exception("Application details not found for ID:$applicationId!");
        }
        if ($applicationDetail->payment_status != 0) {
            throw new Exception("payment is updated for application");
        }
        if ($applicationDetail->citizen_id && $applicationDetail->doc_upload_status == false) {
            throw new Exception("All application related document not uploaded!");
        }

        # Application type hence the charge type
        switch ($applicationDetail->renewal) {
            case (0):
                $chargeCategory = $confApplicationType['NEW_APPLY'];
                break;
            case (1):
                $chargeCategory = $confApplicationType['RENEWAL'];
                break;
        }

        # Charges for the application
        $regisCharges = $mRigRegistrationCharge->getChargesbyId($applicationId)
            ->where('charge_category', $chargeCategory)
            ->where('paid_status', 0)
            ->first();

        if (is_null($regisCharges)) {
            throw new Exception("Charges not found!");
        }
        if (in_array($regisCharges->paid_status, [1, 2])) {
            throw new Exception("Payment has been done!");
        }
        if ($paymentMode == $confPaymentMode['1']) {
            if ($applicationDetail->citizen_id != authUser($req)->id) {
                throw new Exception("You are not he Autherized User!");
            }
        }

        # Transaction details
        $transDetails = $mRigTran->getTranDetails($applicationId, $chargeCategory)->first();
        if ($transDetails) {
            throw new Exception("Transaction has been Done!");
        }

        return [
            "applicationDetails"    => $applicationDetail,
            "rigCharges"            => $regisCharges,
            "chargeCategory"        => $chargeCategory,
            "chargeId"              => $regisCharges->id,
            "regAmount"             => $regisCharges->amount,
            "refRoundAmount"        => round($regisCharges->amount)
        ];
    }

    /**
     * | Post Other Payment Modes for Cheque,DD,Neft
     * | @param req
        | Serial No : 0
        | Working
        | Common function
     */
    public function postOtherPaymentModes($req)
    {
        $paymentMode        = $this->_offlineMode;
        $moduleId           = $this->_rigModuleId;
        $mTempTransaction   = new TempTransaction();
        $mrigChequeDtl      = new RigChequeDtl();

        if ($req['paymentMode'] != $paymentMode[3]) {                                   // Not Cash
            $chequeReqs = [
                'user_id'           => $req['userId'],
                'application_id'    => $req['id'],
                'transaction_id'    => $req['tranId'],
                'cheque_date'       => $req['chequeDate'],
                'bank_name'         => $req['bankName'],
                'branch_name'       => $req['branchName'],
                'cheque_no'         => $req['chequeNo']
            ];
            $mrigChequeDtl->postChequeDtl($chequeReqs);
        }

        $tranReqs = [
            'transaction_id'    => $req['tranId'],
            'application_id'    => $req['id'],
            'module_id'         => $moduleId,
            'workflow_id'       => $req['workflowId'],
            'transaction_no'    => $req['tranNo'],
            'application_no'    => $req['applicationNo'],
            'amount'            => $req['amount'],
            'payment_mode'      => strtoupper($req['paymentMode']),
            'cheque_dd_no'      => $req['chequeNo'],
            'bank_name'         => $req['bankName'],
            'tran_date'         => $req['todayDate'],
            'user_id'           => $req['userId'],
            'ulb_id'            => $req['ulbId'],
            'ward_no'           => $req['ref_ward_id']
        ];
        $mTempTransaction->tempTransaction($tranReqs);
    }

    /*
      |List of unverified cash transaction
     */
    public function listUnverifiedCashPayment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'fromDate' => 'nullable|date_format:Y-m-d',
            'toDate' => $req->fromDate != NULL ? 'required|date_format:Y-m-d|after_or_equal:fromDate' : 'nullable|date_format:Y-m-d',
        ]);
        if ($validator->fails()) {
            return responseMsgs(false, $validator->errors()->first(), [], "055024", "1.0", responseTime(), "POST", $req->deviceId);
        }
        try {
            $RigPayment = new RigTran();
            $data = $RigPayment->listUnverifiedCashPayment($req);
            $data = $data->whereBetween('rig_trans.tran_date', [$req->fromDate, $req->toDate])
                ->get();

            $list = $data;
            return responseMsgs(true, "List Uncleared Cash Payment", $list, "055024", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055024", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }

    /**
      | verified cash payments
     */
    public function verifiedCashPayment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return responseMsgs(false, $validator->errors()->first(), [], "055025", "1.0", responseTime(), "POST", $req->deviceId);
        }
        try {
            RigTran::where('id', $req->id)->update(['verify_status' => '1']);
            return responseMsgs(true, "Payment Verified Successfully !!!",  '', "055025", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "055025", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }

    #=================================================================================================================================
    #==============================================           CASH VERIFICATION          =============================================
    #=================================================================================================================================

    /**
     * | Unverified Cash Verification List
     */
    public function listCashVerification(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'date' => 'required|date',
            'userId' => 'nullable|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0703";
            $version = "01";
            $user = authUser($req);
            $mRigTransaction = new RigTran();
            $userId =  $req->userId;
            $date = date('Y-m-d', strtotime($req->date));

            if (isset($userId)) {
                $data = $mRigTransaction->cashDtl($date)
                    ->where('rig_trans.ulb_id', $user->ulb_id)
                    ->where('emp_dtl_id', $userId)
                    ->get();
            }

            if (!isset($userId)) {
                $data = $mRigTransaction->cashDtl($date)
                    ->where('rig_trans.ulb_id', $user->ulb_id)
                    ->get();
            }

            $collection = collect($data->groupBy("emp_dtl_id")->values());

            $data = $collection->map(function ($val) use ($date) {
                $total =  $val->sum('amount');
                return [
                    "id" => $val[0]['id'],
                    "user_id" => $val[0]['emp_dtl_id'],
                    "officer_name" => $val[0]['user_name'],
                    "mobile" => $val[0]['mobile'],
                    "amount" => $total,
                    "date" => Carbon::parse($date)->format('d-m-Y'),
                ];
            });
            $data = (array_values(objtoarray($data)));
            return responseMsgs(true, "Cash Verification List", $data, $apiId, $version, responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), "POST", $req->deviceId);
        }
    }

    /**
     * | Tc Collection Dtl
     */
    public function cashVerificationDtl(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "date" => "required|date",
            "userId" => "required|int",
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0704";
            $version = "01";
            $mRigTransaction = new RigTran();
            $userId =  $req->userId;
            $date = date('Y-m-d', strtotime($req->date));
            $details = $mRigTransaction->cashDtl($date, $userId)
                ->where('emp_dtl_id', $userId)
                ->get();
             $details;

            if (collect($details)->isEmpty())
                throw new Exception("No Application Found for this id");

            $data['tranDtl'] = collect($details)->values();
            $data['Cash'] = collect($details)->where('payment_mode', 'CASH')->sum('amount');
            $data['totalAmount'] =  $details->sum('amount');
            $data['numberOfTransaction'] =  $details->count();
            $data['date'] = Carbon::parse($date)->format('d-m-Y');
            $data['tcId'] = $userId;

            return responseMsgs(true, "Cash Verification Details", remove_null($data), $apiId, $version, responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), "POST", $req->deviceId);
        }
    }

    public function verifyCash(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "date"          => "required|date",
            "tcId"          => "required|int",
            "id"            => "required|array",
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0705";
            $version = "01";
            $user = authUser($req);
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $mRigTransaction           = new RigTran();
            $mRigDailycollection       = new RigDailycollection();
            $mRigDailycollectiondetail = new RigDailycollectiondetail();
            $receiptIdParam                = Config::get('constants.ID_GENERATION_PARAMS.CASH_VERIFICATION_ID');
            DB::beginTransaction();
            $idGeneration  = new IdGeneration($receiptIdParam, $user->ulb_id, 000, 0);
            $receiptNo = $idGeneration->generate();

            $totalAmount = $mRigTransaction->whereIn('id', $req->id)->sum('amount');

            $mReqs = [
                "receipt_no"     => $receiptNo,
                "user_id"        => $userId,
                "tran_date"      => Carbon::parse($req->date)->format('Y-m-d'),
                "deposit_date"   => Carbon::now(),
                "deposit_amount" => $totalAmount,
                "tc_id"          => $req->tcId,
            ];

            $collectionDtl =  $mRigDailycollection->store($mReqs);
            //Update collection details table

            foreach ($req->id as $id) {
                $collectionDtlsReqs = [
                    "collection_id"  => $collectionDtl->id,
                    "transaction_id" => $id,
                ];
                $mRigDailycollectiondetail->store($collectionDtlsReqs);
            }

            //Update transaction table
            $mRigTransaction->whereIn('id', $req->id)
                ->update(['verify_status' => 1]);

            DB::commit();
            return responseMsgs(true, "Cash Verified", ["receipt_no" => $receiptNo], $apiId, $version, responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), "POST", $req->deviceId);
        }
    }

    # ===================== Transaction Deactivation ========================== #
    /**
     * | Deactivate Payment
     */
    public function deactivatePayment(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["tranNo" => "required"]
        );

        if ($validator->fails())
            return validationError($validator);
        try {

            DB::beginTransaction();
            $tranDtls = RigTran::where('tran_no', $request->tranNo)
                ->where('payment_mode', 'CASH')
                ->first();
            if (!$tranDtls)
                throw new Exception("No Transaction Found");
            if ($tranDtls->verify_status == 1) {
                throw new Exception("Transaction Already Verified");
            }
            $tranDtls->status = 0;
            $tranDtls->save();

            $RigDetails = RigActiveRegistration::find($tranDtls->related_id);
            $RigDetails->payment_status = false;
            $RigDetails->save();

            $RigRegistrationCharge = RigRegistrationCharge::where('application_id', $tranDtls->related_id)->first();

            if ($RigRegistrationCharge) {
                $RigRegistrationCharge->paid_status = false;
                $RigRegistrationCharge->save();
            }

            $RigTranDetail = RigTranDetail::where('application_id', $tranDtls->related_id)->first();
            if ($RigTranDetail) {
                $RigTranDetail->status = false;
                $RigTranDetail->save();
            }

            DB::commit();
            return responseMsgs(true, "Payment Deactivated", [], "1003", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "1003", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }
}
