<?php

namespace App\Http\Controllers;

use App\Models\PenaltyChallan;
use App\Models\PenaltyFinalRecord;
use App\Models\PenaltyRecord;
use App\Models\PenaltyTransaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeactivationController extends Controller
{

    /**
     * | Deactivate Application
     * | Delete the Final Record Data & Challan
     * | Request : applicationNo of Final Record
     */
    public function deactivateApplication(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["applicationNo" => "required"]
        );

        if ($validator->fails())
            return validationError($validator);
        try {
            DB::beginTransaction();
            $penaltyDtls = PenaltyFinalRecord::where('application_no', $request->applicationNo)->where('status', 1)->first();
            if (!$penaltyDtls)
                throw new Exception("Application Not Found");
            $penaltyDtls->status = 0;
            $penaltyDtls->save();

            $penaltyChalan = PenaltyChallan::where('penalty_record_id', $penaltyDtls->id)->where('status', 1)->first();
            $penaltyChalan->status = 0;
            $penaltyChalan->save();

            if ($penaltyChalan->payment_date) {
                $tranDtls = PenaltyTransaction::where('challan_id', $penaltyChalan->id)->where('status', 1)->first();
                $tranDtls->status = 0;
                $tranDtls->save();
            }

            if ($penaltyDtls->applied_record_id) {
                $penaltyAppliedRecord = PenaltyRecord::find($penaltyDtls->applied_record_id);
                $penaltyAppliedRecord->status = 0;
                $penaltyAppliedRecord->save();
            }

            DB::commit();
            return responseMsgs(true, "Application Deactivated", [], "1001", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "1001", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Deactivate Challan
     * | Delete the challan and if challan is onSpot then delete the challan also
     * | Request : challanNo
     */
    public function deactivateChallan(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["challanNo" => "required"]
        );

        if ($validator->fails())
            return validationError($validator);
        try {
            DB::beginTransaction();
            $challanDtls = PenaltyChallan::where('challan_no', $request->challanNo)->first();
            if (!$challanDtls)
                throw new Exception("Challan Not Found");
            $challanDtls->status = 0;
            $challanDtls->save();

            if ($challanDtls->payment_date) {
                $tranDtls = PenaltyTransaction::where('challan_id', $challanDtls->id)->where('status', 1)->first();
                $tranDtls->status = 0;
                $tranDtls->save();
            }

            if ($challanDtls->challan_type == 'On Spot') {
                $finalRecordDtls =  PenaltyFinalRecord::find($challanDtls->penalty_record_id);
                $finalRecordDtls->status = 0;
                $finalRecordDtls->save();
            }

            DB::commit();
            return responseMsgs(true, "Challan Deactivated", [], "1002", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "1002", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

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
            $tranDtls = PenaltyTransaction::where('tran_no', $request->tranNo)
                ->first();
            if (!$tranDtls)
                throw new Exception("No Transaction Found");
            $tranDtls->status = 0;
            $tranDtls->save();

            $challanDtls = PenaltyChallan::find($tranDtls->challan_id);
            $challanDtls->payment_date = null;
            $challanDtls->save();

            $penaltyDtls = PenaltyFinalRecord::find($tranDtls->application_id);
            $penaltyDtls->payment_status = false;
            $penaltyDtls->save();

            DB::commit();
            return responseMsgs(true, "Payment Deactivated", [], "1003", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "1003", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }
}
