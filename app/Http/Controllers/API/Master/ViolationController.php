<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Department;
use App\Models\Master\Section;
use App\Models\Master\Violation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\req;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ViolationController extends Controller
{
    private $_mViolations;

    public function __construct()
    {
        DB::enableQueryLog();
        $this->_mViolations = new Violation();
    }

    /**
     * |  Create Violation 
     */
    public function createViolation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'departmentId'      => 'required|integer',
            'sectionId'         => 'required|integer',
            'violationName'     => 'required|string',
            'penaltyAmount'     => 'required|integer',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0401";
            $version = "01";
            $isGroupExists = $this->_mViolations->checkExisting($req);
            if (collect($isGroupExists)->isNotEmpty())
                throw new Exception("Violation Name Already Existing");
            $user = authUser($req);
            $metaReqs = [
                'violation_name'  => $req->violationName,
                'section_id'      => $req->sectionId,
                'department_id'   => $req->departmentId,
                'penalty_amount'  => $req->penaltyAmount,
                'user_id'         => $user->id,
            ];
            $this->_mViolations->store($metaReqs); // Store in Violations table
            return responseMsgs(true, "Records Added Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    // Edit records
    public function updateViolation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'violationId'       => 'required|int',
            'departmentId'      => 'required|integer',
            'sectionId'         => 'required|integer',
            'violationName'     => 'required|string',
            'penaltyAmount'     => 'required|integer',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0402";
            $version = "01";
            $getData = $this->_mViolations::findOrFail($req->violationId);
            $isExists = $this->_mViolations->checkExisting($req);
            if ($isExists && $isExists->where('id', '!=', $req->violationId)->isNotEmpty())
                throw new Exception("Violation Name Already Existing");
            $metaReqs = [
                'violation_name'   => $req->violationName,
                'section_id'       => $req->sectionId ?? $getData->id,
                'department_id'    => $req->departmentId ?? $getData->id,
                'penalty_amount'   => $req->penaltyAmount,
                'updated_at'       => Carbon::now()
            ];
            $getData->update($metaReqs); // Store in Violations table
            return responseMsgs(true, "Records Updated Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Get Violation BY Id
     */
    public function ViolationById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0403";
            $version = "01";
            $getData = $this->_mViolations->recordDetails()->where('violations.id', $req->id)->first();
            if (collect($getData)->isEmpty())
                throw new Exception("Data Not Found");
            return responseMsgs(true, "View Records", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
    /**
     * Get Violation List
     */
    public function getViolation(Request $req)
    {
        try {
            $apiId = "0404";
            $version = "01";
            $ulbId = $req->ulbId ?? authUser($req)->ulb_id;
            $getData = $this->_mViolations->recordDetails()
                ->where('departments.ulb_id', $ulbId)
                ->get();
            return responseMsgs(true, "View All Records", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Delete Violation By Id
     */
    public function deleteViolation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0405";
            $version = "01";
            $delete = $this->_mViolations::findOrFail($req->id);
            $delete->update(['status' => 0]);
            return responseMsgs(true, "Deleted Successfully", "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }


    /**
     * | Get Violation List By Department Id
     */
    public function violationByDepartment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'departmentId' => 'required',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0406";
            $version = "01";
            $mChallanCategories = new Violation();
            $getData = $mChallanCategories->getList($req);
            return responseMsgs(true, "View Violation List", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    public function onSpotViolation(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'violationId' => 'required|array',
            'violationId.*' => 'required|int',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0407";
            $version = "01";
            Violation::where('on_spot', true)->update(['on_spot' => false]);
            $idsToUpdate = $req->violationId;
            $status = true;
            DB::transaction(function () use ($idsToUpdate, $status) {
                Violation::whereIn('id', $idsToUpdate)->update(['on_spot' => $status]);
            });

            return responseMsgs(true, "Updated Successfully", $status, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
