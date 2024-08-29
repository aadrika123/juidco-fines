<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class RigTran extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        return RigTran::create($req);
    }
    /**
     * | Get transaction by application No
     */
    public function getTranByApplicationId($applicationId)
    {
        return RigTran::where('related_id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get transaction details accoring to related Id and transaction type
     */
    public function getTranDetails($relatedId)
    {
        return RigTran::where('related_id', $relatedId)
            // ->where('tran_type_id', $tranType)
            ->where('status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get transaction details according to transaction no
     */
    public function getTranDetailsByTranNo($tranNo)
    {
        return RigTran::select(
            'rig_trans.id AS refTransId',
            'rig_trans.*',
            'ulb_masters.ulb_name',
            'ulb_masters.email',
            'ulb_masters.address'
        )
            ->where('rig_trans.tran_no', $tranNo)
            ->where('rig_trans.status', 1)
            ->join('ulb_masters', 'ulb_masters.id', 'rig_trans.ulb_id')
            ->orderByDesc('rig_trans.id');
    }

    /**
     * | Save the transaction details 
     */
    public function saveTranDetails($req)
    {
        $paymentMode = Config::get("rig.PAYMENT_MODE");

        $mPetTran = new RigTran();
        $mPetTran->related_id   = $req['id'];
        $mPetTran->ward_id      = $req['wardId'];
        $mPetTran->ulb_id       = $req['ulbId'];
        $mPetTran->tran_date    = $req['todayDate'];
        $mPetTran->tran_no      = $req['tranNo'];
        $mPetTran->payment_mode = $req['paymentMode'];
        $mPetTran->tran_type    = $req['tranType'];
        $mPetTran->amount       = $req['amount'];
        $mPetTran->emp_dtl_id   = $req['empId'] ?? null;
        $mPetTran->ip_address   = $req['ip'] ?? null;
        $mPetTran->user_type    = $req['userType'];
        $mPetTran->is_jsk       = $req['isJsk'] ?? false;
        $mPetTran->citizen_id   = $req['citId'] ?? null;
        $mPetTran->tran_type_id = $req['tranTypeId'];
        $mPetTran->round_amount = $req['roundAmount'];
        $mPetTran->token_no     = $req['tokenNo'];

        # For online payment
        if ($req['paymentMode'] == $paymentMode['1']) {
            $mPetTran->pg_response_id = $req['pgResponseId'];                               // Online response id
            $mPetTran->pg_id = $req['pgId'];                                                // Payment gateway id
        }
        $mPetTran->save();

        return [
            'transactionNo' => $req['tranNo'],
            'transactionId' => $mPetTran->id
        ];
    }

    /**
     * | Update request for transaction table
     */
    public function saveStatusInTrans($id, $refReq)
    {
        RigTran::where('id', $id)
            ->update($refReq);
    }

    /**
     * | List Uncleared cheque or DD
     */
    public function listUnverifiedCashPayment($req)
    {
        return  DB::table('rig_trans')
            ->select(
                'rig_trans.id',
                'rig_trans.tran_no',
                'rig_trans.tran_date',
                'rig_trans.amount',
                't1.application_no',
                'rig_active_applicants.applicant_name',
                'rig_active_applicants.mobile_no',
                DB::raw("CASE 
                WHEN rig_trans.verify_status = '0' THEN 'Not Verified'
                WHEN rig_trans.verify_status = '1' THEN 'Verified'
                END AS cashVerifiedStatus"),
            )
            ->join('rig_active_registrations as t1', 'rig_trans.related_id', '=', 't1.id')
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_trans.related_id')
            ->where('payment_mode', '=', 'CASH');
    }

    public function listCollections($fromDate, $toDate,)
    {
        return RigTran::select(
            'rig_trans.id as transactionId',
            'rig_active_registrations.id',
            'rig_trans.amount',
            'rig_active_registrations.application_no',
            'rig_trans.tran_no',
            'rig_trans.tran_date',
            'rig_active_applicants.applicant_name as ownerName',
            'rig_active_registrations.application_type',
            'ulb_ward_masters.ward_name',
        )
            ->join('rig_active_registrations', 'rig_active_registrations.id', 'rig_trans.related_id')
            ->join('rig_active_applicants', 'rig_active_applicants.application_id', 'rig_trans.related_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'rig_active_registrations.ward_id')
            ->where('rig_trans.tran_date', '>=', $fromDate)
            ->where('rig_trans.tran_date', '<=', $toDate)
            ->where('rig_trans.status', 1);
    }

    /**
     * | Details for Cash Verification
     */
    public function cashDtl($date)
    {
        return RigTran::select('rig_trans.*', 'users.user_name', 'users.id as user_id', 'mobile')
            ->join('users', 'users.id', 'rig_trans.emp_dtl_id')
            ->where('rig_trans.status', 1)
            ->where('verify_status', 0)
            ->where('tran_date', $date);
    }

    /**
     * | Cheque Dtl And Transaction Dtl
     */
    public function chequeTranDtl($ulbId)
    {
        return RigTran::select(
            'rig_cheque_dtls.id',
            DB::raw("TO_CHAR(tran_date, 'DD-MM-YYYY') as tran_date"),
            'rig_trans.tran_no',
            'rig_trans.verify_status',
            'payment_mode',
            'amount',
            DB::raw("TO_CHAR(rig_cheque_dtls.cheque_date, 'DD-MM-YYYY') as cheque_date"),
            "rig_cheque_dtls.bank_name",
            "rig_cheque_dtls.branch_name",
            "rig_cheque_dtls.cheque_no",
            DB::raw("TO_CHAR(clear_bounce_date, 'DD-MM-YYYY') as clear_bounce_date"),
            "users.name as user_name",
            'rig_cheque_dtls.status'
        )
            ->join('rig_cheque_dtls', 'rig_cheque_dtls.transaction_id', '=', 'rig_trans.id')
            ->leftJoin('users', 'users.id', 'rig_cheque_dtls.user_id')
            ->whereIn('payment_mode', ['CHEQUE', 'DD'])
            ->where('rig_trans.ulb_id', $ulbId)
            ->orderby('rig_cheque_dtls.id', 'Desc');
    }
}
