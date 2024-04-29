<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Section;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SectionController extends Controller
{
    private $_mSections;

    public function __construct()
    {
        $this->_mSections = new Section();
    }

    /**
     * |  Create Violation 
     */
    public function createSection(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "departmentId"          => 'required|int',
            'violationSection'      => 'required|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0301";
            $version = "01";
            $user = authUser($req);
            $isGroupExists = $this->_mSections->checkExisting($req);
            if (collect($isGroupExists)->isNotEmpty())
                throw new Exception("Section Already Existing");

            $metaReqs = [
                'department_id' => $req->departmentId,
                'violation_section' => strtoupper($req->violationSection),
                'created_by'        => $user->id,
                // 'ulb_id'            => $user->ulb_id
            ];
            $this->_mSections->store($metaReqs); // Store in Violations table
            return responseMsgs(true, "Records Added Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    // Edit records
    public function updateSection(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'sectionId'             => 'required|int',
            'departmentId'          => 'required|int',
            'violationSection'        => 'required|string'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0302";
            $version = "01";
            $getData = $this->_mSections::findOrFail($req->sectionId);
            $isExists = $this->_mSections->checkExisting($req);
            if ($isExists && $isExists->where('id', '!=', $req->sectionId)->isNotEmpty())  // pending
                throw new Exception("Section Already Existing");
            $metaReqs = [
                'department_id' => $req->departmentId,
                'violation_section'   => strtoupper($req->violationSection)
            ];
            $getData->update($metaReqs); // Store in Violations table
            return responseMsgs(true, "Records Updated Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Get Violation BY Id
     */
    public function getSectionById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'sectionId' => 'required|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0303";
            $version = "01";
            $getData = $this->_mSections->recordDetails($req)->where('sections.id', $req->sectionId)->first();
            if (collect($getData)->isEmpty())
                throw new Exception("Data Not Found");
            return responseMsgs(true, "View Records", $getData, $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
    /**
     * Get Violation List
     */
    public function getSectionList(Request $req)
    {
        try {
            $apiId = "0304";
            $version = "01";
            $user    = authUser($req);
            $getData = $this->_mSections->recordDetails($req)
                ->where('departments.ulb_id', $user->ulb_id)
                ->get();
            return responseMsgs(true, "View All Records", $getData, $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Delete Violation By Id
     */
    public function deleteSection(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'sectionId' => 'required'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0305";
            $version = "01";
            $sectionDetail = $this->_mSections::findOrFail($req->sectionId);
            $sectionDetail->update(['status' => 0]);
            return responseMsgs(true, "Deleted Successfully", "", $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version,  responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Get Section List By Department Id
     */
    public function getSectionListById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'departmentId' => 'required'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0306";
            $version = "01";
            $mChallanCategories = new Section();
            $getData = $mChallanCategories->getList($req);
            return responseMsgs(true, "View Section List", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
