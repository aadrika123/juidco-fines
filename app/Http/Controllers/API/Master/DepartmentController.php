<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Department;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    private $_mDepartments;

    public function __construct()
    {
        $this->_mDepartments = new Department();
    }

    /**
     * |  Create Violation 
     */
    public function createDepartment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'departmentName'        => 'required|string'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0201";
            $version = "01";
            $user = authUser($req);
            $isGroupExists = $this->_mDepartments->checkExisting($req);
            if (collect($isGroupExists)->isNotEmpty())
                throw new Exception("Department Already Existing");

            $metaReqs = [
                'department_name' => strtoupper($req->departmentName),
                'created_by'      => $user->id,
                'ulb_id'          => $user->ulb_id
            ];
            $this->_mDepartments->store($metaReqs); // Store in Violations table
            return responseMsgs(true, "Records Added Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $metaReqs, $apiId, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    // Edit records
    public function updateDepartment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'departmentId'          => 'required|int',
            'departmentName'        => 'required|string'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0202";
            $version = "01";
            $getData = $this->_mDepartments::findOrFail($req->departmentId);
            $isExists = $this->_mDepartments->checkExisting($req);
            if ($isExists && $isExists->where('id', '!=', $req->departmentId)->isNotEmpty())
                throw new Exception("Department Already Existing");
            $metaReqs = [
                'department_name' => strtoupper($req->departmentName),
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
    public function getDepartmentById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'departmentId' => 'required|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0203";
            $version = "01";
            $getData = $this->_mDepartments->recordDetails()->where('departments.id', $req->departmentId)->first();
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
    public function getDepartmentList(Request $req)
    {
        try {
            $apiId = "0204";
            $version = "01";
            $user = authUser($req);
            $getData = $this->_mDepartments->recordDetails()
                ->where('ulb_id', $user->ulb_id)
                ->get();
            return responseMsgs(true, "View All Records", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Delete Violation By Id
     */
    public function deleteDepartment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'departmentId' => 'required'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0205";
            $version = "01";
            $departmentDtl = $this->_mDepartments::findOrFail($req->departmentId);
            $departmentDtl->update([
                'status' => 0,
                "updated_at" => Carbon::now()
            ]);
            return responseMsgs(true, "Deleted Successfully", [], $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
