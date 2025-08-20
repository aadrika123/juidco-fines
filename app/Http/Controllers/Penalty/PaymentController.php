<?php

namespace App\Http\Controllers\Penalty;

use App\Http\Controllers\Controller;
use App\IdGenerator\IdGeneration;
use App\Models\IdGenerationParam;
use App\Models\PenaltyChallan;
use App\Models\PenaltyFinalRecord;
use App\Models\PenaltyTransaction;
use App\Models\Master\Section;
use App\Models\Master\UlbMaster;
use App\Models\Master\Violation;
use App\Models\Payment\RazorpayReq;
use App\Models\Payment\RazorpayResponse;
use App\Models\PenaltyDailycollection;
use App\Models\PenaltyDailycollectiondetail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Razorpay\Api\Api;

/**
 * =======================================================================================================
 * ===================         Created By : Mrinal Kumar        ==========================================
 * ===================         Created On : 06-10-2023          ==========================================
 * =======================================================================================================
 * ===================         Updated By : Arshad Hussain        ==========================================
 * | Status : Open
 */

class PaymentController extends Controller
{

    /**
     * | Save Razor Pay Request
     */
    public function initiatePayment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "amount"        => "required|numeric",
            "challanId"     => "required|int",
            "applicationId" => "nullable|int",
            "workflowId"    => "nullable|int",
        ]);

        if ($validator->fails())
            return validationError($validator);

        try {

            $apiId = "0701";
            $version = "01";
            $keyId        = Config::get('constants.RAZORPAY_KEY');
            $secret       = Config::get('constants.RAZORPAY_SECRET');
            $mRazorpayReq = new RazorpayReq();
            $api          = new Api($keyId, $secret);

            $challanDetails = PenaltyChallan::find($req->challanId);
            $penaltyDetails = PenaltyFinalRecord::find($challanDetails->penalty_record_id);
            if (!$penaltyDetails)
                throw new Exception("Application Not Found");
            if ($penaltyDetails->payment_status == 1)
                throw new Exception("Payment Already Done");
            if (!$challanDetails)
                throw new Exception("Challan Not Found");
            $orderData = $api->order->create(array('amount' => $challanDetails->total_amount * 100, 'currency' => 'INR',));

            if ($req->authRequired == true)
                $user = authUser($req);

            $mReqs = [
                "order_id"       => $orderData['id'],
                "merchant_id"    => $req->merchantId,
                "challan_id"     => $req->challanId,
                "application_id" => $req->applicationId,
                "user_id"        => $user->id ?? 0,
                "workflow_id"    => $penaltyDetails->workflow_id ?? 0,
                "amount"         => $challanDetails->total_amount,
                "ulb_id"         => $penaltyDetails->ulb_id ?? $user->ulb_id,
                "ip_address"     => getClientIpAddress()
            ];
            $data = $mRazorpayReq->store($mReqs);

            return responseMsgs(true, "Order id is", ['order_id' => $data->order_id], $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
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
            $mSection            = new Section();
            $mViolation          = new Violation();
            $mRazorpayReq        = new RazorpayReq();
            $mRazorpayResponse   = new RazorpayResponse();
            $mPenaltyTransaction = new PenaltyTransaction();
            $todayDate           = Carbon::now();
            $penaltyDetails    = PenaltyFinalRecord::find($req->applicationId);
            $challanDetails    = PenaltyChallan::where('penalty_record_id', $req->applicationId)->where('status', 1)->first();

            $receiptIdParam    = Config::get('constants.ID_GENERATION_PARAMS.RECEIPT');

            if ($req->authRequired == true)
                $user      = authUser($req);

            $ulbDtls = UlbMaster::find($penaltyDetails->ulb_id);
            $violationDtl  = $mViolation->violationById($penaltyDetails->violation_id);
            $sectionId     = $violationDtl->section_id;
            $section       = $mSection->sectionById($sectionId)->violation_section;
            $idGeneration  = new IdGeneration($receiptIdParam, $penaltyDetails->ulb_id, $section, 0);
            $transactionNo = $idGeneration->generate();

            $paymentData = $mRazorpayReq->getPaymentRecord($req);

            if (collect($paymentData)->isEmpty())
                throw new Exception("Payment Data not available");
            if ($paymentData) {
                $mReqs = [
                    "request_id"      => $paymentData->id,
                    "order_id"        => $req->orderId,
                    "merchant_id"     => $req->mid,
                    "payment_id"      => $req->paymentId,
                    "challan_id"      => $req->challanId,
                    "application_id"  => $req->applicationId,
                    "amount"          => $req->amount,
                    "ulb_id"          => $penaltyDetails->ulb_id,
                    "ip_address"      => getClientIpAddress(),
                    // "res_ref_no"      => $transactionNo,                         // flag
                    // "response_msg"    => $pinelabData['Response']['ResponseMsg'],
                    // "response_code"   => $pinelabData['Response']['ResponseCode'],
                    // "description"     => $req->description,
                ];

                $data = $mRazorpayResponse->store($mReqs);
            }

            if ($req->status == "AUTHORIZED") {                           // Success Response code(AUTHORIZED)
                $paymentData->payment_status = 1;
                $paymentData->save();

                # calling function for the modules
                $reqs = [
                    "application_id" => $req->applicationId,
                    "challan_id"     => $challanDetails->id,
                    "tran_no"        => $transactionNo,
                    "tran_date"      => $req->date,
                    "tran_by"        => $user->id ?? 0,
                    "payment_mode"   => strtoupper('ONLINE'),
                    "amount"         => $challanDetails->amount,
                    "penalty_amount" => $challanDetails->penalty_amount,
                    "total_amount"   => $challanDetails->total_amount,
                    "ulb_id"         => $penaltyDetails->ulb_id,
                    "verify_status"  => 1,
                ];
                DB::beginTransaction();
                $tranDtl = $mPenaltyTransaction->store($reqs);
                $penaltyDetails->payment_status = 1;
                $penaltyDetails->save();

                $challanDetails->payment_date = $req->date;
                $challanDetails->save();
                DB::commit();
                $data->tran_no = $tranDtl->tran_no;

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
            } else
                throw new Exception("Payment Cancelled");
            return responseMsgs(true, "Data Saved", $data, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
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
            $mPenaltyTransaction = new PenaltyTransaction();
            $userId =  $req->userId;
            $date = date('Y-m-d', strtotime($req->date));

            if (isset($userId)) {
                $data = $mPenaltyTransaction->cashDtl($date)
                    ->where('penalty_transactions.ulb_id', $user->ulb_id)
                    ->where('user_id', $userId)
                    ->get();
            }

            if (!isset($userId)) {
                $data = $mPenaltyTransaction->cashDtl($date)
                    ->where('penalty_transactions.ulb_id', $user->ulb_id)
                    ->get();
            }

            $collection = collect($data->groupBy("user_id")->values());

            $data = $collection->map(function ($val) use ($date) {
                $total =  $val->sum('total_amount');
                return [
                    "id" => $val[0]['id'],
                    "user_id" => $val[0]['user_id'],
                    "officer_name" => $val[0]['user_name'],
                    "mobile" => $val[0]['mobile'],
                    "penalty_amount" => $total,
                    "date" => Carbon::parse($date)->format('d-m-Y'),
                ];
            });

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
            $mPenaltyTransaction = new PenaltyTransaction();
            $userId =  $req->userId;
            $date = date('Y-m-d', strtotime($req->date));
            $details = $mPenaltyTransaction->cashDtl($date, $userId)
                ->where('tran_by', $userId)
                ->get();

            if (collect($details)->isEmpty())
                throw new Exception("No Application Found for this id");

            $data['tranDtl'] = collect($details)->values();
            $data['Cash'] = collect($details)->where('payment_mode', 'CASH')->sum('total_amount');
            $data['totalAmount'] =  $details->sum('total_amount');
            $data['numberOfTransaction'] =  $details->count();
            $data['date'] = Carbon::parse($date)->format('d-m-Y');
            $data['tcId'] = $userId;

            return responseMsgs(true, "Cash Verification Details", remove_null($data), $apiId, $version, responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), "POST", $req->deviceId);
        }
    }
    /**
     * | For Verification of cash
        save data in collection detail is pending and update verify status in transaction table
     */
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
            $mPenaltyTransaction           = new PenaltyTransaction();
            $mPenaltyDailycollection       = new PenaltyDailycollection();
            $mPenaltyDailycollectiondetail = new PenaltyDailycollectiondetail();
            $receiptIdParam                = Config::get('constants.ID_GENERATION_PARAMS.CASH_VERIFICATION_ID');
            DB::beginTransaction();
            $idGeneration  = new IdGeneration($receiptIdParam, $user->ulb_id, 000, 0);
            $receiptNo = $idGeneration->generate();

            $totalAmount = $mPenaltyTransaction->whereIn('id', $req->id)->sum('total_amount');

            $mReqs = [
                "receipt_no"     => $receiptNo,
                "user_id"        => $userId,
                "tran_date"      => Carbon::parse($req->date)->format('Y-m-d'),
                "deposit_date"   => Carbon::now(),
                "deposit_amount" => $totalAmount,
                "tc_id"          => $req->tcId,
            ];

            $collectionDtl =  $mPenaltyDailycollection->store($mReqs);
            //Update collection details table

            foreach ($req->id as $id) {
                $collectionDtlsReqs = [
                    "collection_id"  => $collectionDtl->id,
                    "transaction_id" => $id,
                ];
                $mPenaltyDailycollectiondetail->store($collectionDtlsReqs);
            }

            //Update transaction table
            $mPenaltyTransaction->whereIn('id', $req->id)
                ->update(['verify_status' => 1]);

            DB::commit();
            return responseMsgs(true, "Cash Verified", ["receipt_no" => $receiptNo], $apiId, $version, responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), "POST", $req->deviceId);
        }
    }

    /**
     * | Initiate Online Challan Payment
     * | Function handles payment initiation process for challan penalties
     */
    public function initiateOnlineChallanPayment(Request $request)
    {
        try {
            # Get authenticated user details
            $refUser       = authUser($request);

            # Configuration Constants
            $fineModuleId  = Config::get('constants.MODULE_ID');
            $paymentUrl    = Config::get('constants.PAYMENT_URL');

            # Razorpay request handler
            $mRazorpayReq = new RazorpayReq();

            DB::beginTransaction();

            # Fetch challan details
            $challanDetails = PenaltyChallan::find($request->challanId);
            if (!$challanDetails) {
                throw new Exception("Challan Not Found");
            }

            # Fetch penalty details
            $penaltyDetails = PenaltyFinalRecord::find($challanDetails->penalty_record_id);
            if (!$penaltyDetails) {
                throw new Exception("Application Not Found");
            }

            # Check if already paid
            if ($penaltyDetails->payment_status == 1) {
                throw new Exception("Payment Already Done");
            }

            # Prepare request for Payment API
            $myRequest = new Request([
                'amount'        => $request->amount,
                'workflowId'    => 0, // Static
                'id'            => $request->challanId,
                'departmentId'  => $fineModuleId,
                'ulbId'         => $penaltyDetails->ulb_id,
                'auth'          => $refUser
            ]);

            # Call Payment Gateway API to generate order id
            $refResponse = Http::withHeaders([
                "api-key" => "eff41ef6-d430-4887-aa55-9fcf46c72c99"
            ])
                ->withToken($request->bearerToken())
                ->post($paymentUrl . '/api/payment/generate-orderid', $myRequest);

            $data = json_decode($refResponse);

            if (!$data) {
                throw new Exception("Payment Order Id Not Generated");
            }

            # If payment gateway returns failure
            if ($data->status == false) {
                return responseMsgs(false, collect($data->message)->first()[0] ?? $data->message, json_decode($refResponse), "110162", "1.0", "", 'POST', $request->deviceId ?? "");
            }
            # Store payment request in DB
            $mReqs = [
                "order_id"       => $data->data->orderId,
                "merchant_id"    => $request->merchantId,
                "challan_id"     => $request->challanId,
                "application_id" => $request->applicationId,
                "user_id"        => $refUser->id ?? 0,
                "workflow_id"    => $penaltyDetails->workflow_id ?? 0,
                "amount"         => $challanDetails->total_amount,
                "ulb_id"         => $penaltyDetails->ulb_id ?? $refUser->ulb_id,
                "ip_address"     => getClientIpAddress()
            ];
            $mRazorpayReq->store($mReqs);

            DB::commit();

            # Prepare response data
            $temp = [
                'name'          => $refUser->user_name,
                'mobile'        => $refUser->mobile,
                'email'         => $refUser->email,
                'userId'        => $refUser->id,
                'ulbId'         => $refUser->ulb_id ?? $myRequest->ulbId,
                'orderId'       => $data->data->orderId,
                'departmentId'  => $fineModuleId,
                'amount'        => $request->amount,
                'demandFrom'    => $request->demandFrom,
                'demandUpto'    => $request->demandUpto,
                'applicationId' => $request->applicationId,
                'challanId'     => $request->challanId,
                'payment_method' => "ONLINE",
                'workflow_id'   => 0, // Static
            ];
            return responseMsgs(true, "Payment OrderId Generated Successfully !!!", $temp, "",  "01",  ".ms",  "POST",  $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "", "03", ".ms", "POST", $request->deviceId);
        }
    }

    public function paymentSuccessOrFailurev1(Request $req)
    {
        try {
            // Validate required parameters first
            if (empty($req->orderId)) {
                throw new \Exception('Missing orderId');
            }

            // Fetch payment request record (outside transaction)
            $RazorPayRequest = DB::table('razorpay_reqs')
                ->where('order_id', $req->orderId)
                ->first();

            if (!$RazorPayRequest) {
                throw new \Exception('Invalid payment request.');
            }

            // Extract additional info
            $refDate = explode("--", $RazorPayRequest->demand_from_upto);
            $startingYear = $refDate[0] ?? null;
            $paymentUpto  = $refDate[1] ?? null;

            // Prepare request data for makePayment
            $req->merge([
                "isNotWebHook" => true,
                "paymentModes" => $req->paymentMode,
                "paymentMode"  => "ONLINE",
                "paidUpto"     => $paymentUpto,
                "paidAmount"   => $req->amount,
                "request_id"   => $RazorPayRequest->id,
                "consumerId"   => $RazorPayRequest->consumer_id,
                "ulb_id"       => $req->ulbId,
                "user_id"      => $req->userId,
            ]);

            // Call API BEFORE transaction
            $paymentResponse = $this->makePaymentv1($req);

            if (
                !isset($paymentResponse->original["status"]) ||
                $paymentResponse->original["status"] !== true
            ) {
                throw new \Exception('Payment processing failed.');
            }

            $data = $paymentResponse->original["data"] ?? [];

            // DB transaction for saving records
            DB::beginTransaction();

            try {
                $mRazorpayResponse = new RazorpayResponse();
                $mRazorpayResponse->saveResponseData($req, $data);

                DB::table('razorpay_reqs')
                    ->where('id', $RazorPayRequest->id)
                    ->update(['payment_status' => 1]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // rethrow for outer catch
            }

            return responseMsgs(true, 'Payment processed successfully', $data, '110168', 1, responseTime(), 'POST', $req->deviceId);
        } catch (\Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '110168', 1, "", 'POST', $req->deviceId);
        }
    }

    /**
     * | Save Razor Pay Response
     */
    public function paymentSuccessOrFailure(Request $req)
    {
        $idGeneration = new IdGenerationParam();
        try {
            $apiId = "0702";
            $version = "01";
            Storage::disk('public')->put($req->orderId . '.json', json_encode($req->all()));
            $mSection            = new Section();
            $mViolation          = new Violation();
            $mRazorpayReq        = new RazorpayReq();
            $mRazorpayResponse   = new RazorpayResponse();
            $mPenaltyTransaction = new PenaltyTransaction();
            $todayDate           = Carbon::now();
            $penaltyDetails    = PenaltyFinalRecord::find($req->id);
            $challanDetails    = PenaltyChallan::where('penalty_record_id', $req->id)->where('status', 1)->first();

            $receiptIdParam    = Config::get('constants.ID_GENERATION_PARAMS.RECEIPT');

            // if ($req->authRequired == true)
            //     $user      = authUser($req);

            $ulbDtls = UlbMaster::find($penaltyDetails->ulb_id);
            $violationDtl  = $mViolation->violationById($penaltyDetails->violation_id);
            $sectionId     = $violationDtl->section_id;
            $section       = $mSection->sectionById($sectionId)->violation_section;
            $idGeneration  = new IdGeneration($receiptIdParam, $penaltyDetails->ulb_id, $section, 0);
            $transactionNo = $idGeneration->generate();

            $paymentData = $mRazorpayReq->getPaymentRecordv1($req);

            if (collect($paymentData)->isEmpty())
                throw new Exception("Payment Data not available");
            DB::beginTransaction();
            if ($paymentData) {
                $mReqs = [
                    "request_id"      => $paymentData->id,
                    "order_id"        => $req->orderId,
                    "merchant_id"     => $req->mid,
                    "payment_id"      => $req->paymentId,
                    "challan_id"      => $req->challanId,
                    "application_id"  => $req->applicationId,
                    "amount"          => $req->amount,
                    "ulb_id"          => $penaltyDetails->ulb_id,
                    "ip_address"      => getClientIpAddress(),
                    // "res_ref_no"      => $transactionNo,                         // flag
                    // "response_msg"    => $pinelabData['Response']['ResponseMsg'],
                    // "response_code"   => $pinelabData['Response']['ResponseCode'],
                    // "description"     => $req->description,
                ];

                $data = $mRazorpayResponse->store($mReqs);
            }
            $paymentData->payment_status = 1;
            $paymentData->save();

            # calling function for the modules
            $reqs = [
                "application_id" => $req->id,
                "challan_id"     => $challanDetails->id,
                "tran_no"        => $transactionNo,
                "tran_date"      => $req->date ?? Carbon::now()->format('Y-m-d'),
                "tran_by"        => $req->userId ?? 0,
                "citzen_id"        => $req->userId ?? 0,
                "payment_mode"   => strtoupper('ONLINE'),
                "amount"         => $challanDetails->amount,
                "penalty_amount" => $challanDetails->penalty_amount,
                "total_amount"   => $challanDetails->total_amount,
                "ulb_id"         => $penaltyDetails->ulb_id,
                "verify_status"  => 1,
            ];

            $tranDtl = $mPenaltyTransaction->store($reqs);
            $penaltyDetails->payment_status = 1;
            $penaltyDetails->save();

            $challanDetails->payment_date = $req->date ?? Carbon::now()->format('Y-m-d');
            $challanDetails->save();
            DB::commit();
            $data->tran_no = $tranDtl->tran_no;

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
            return responseMsgs(true, "Data Saved", $data, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Get citizen payment history to show 
     * | Using user Id for displaying data
        | Selail No : 12
        | Use 
        | Show the payment from jsk also
     */
    public function getCitizenPaymentHistory(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'pages' => 'required|int'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $citizen        = authUser($request);
            $citizenId      = $citizen->id ?? 36;
            $mfineTran     = new PenaltyTransaction();
            $refUserType    = Config::get("constants.USER_TYPE");

            if ($citizen->user_type != $refUserType["Citizen"]) {
                throw new Exception("You're user type is not citizen!");
            }
            $transactionDetails = $mfineTran->getTransByCitizenId($citizenId)
                ->paginate($request->pages);
            return responseMsgs(true, "List of transactions", remove_null($transactionDetails), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $request->deviceId);
        }
    }
}
