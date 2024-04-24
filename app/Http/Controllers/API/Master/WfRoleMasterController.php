<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use App\Models\WfRole;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * =======================================================================================================
 * ===================         Created By : Umesh Kumar        ==========================================
 * ===================         Created On : 06-10-2023          ==========================================
 * =======================================================================================================
 * | Status : Open
 */

class WfRoleMasterController extends Controller
{
    private $_mWfRoles;

    public function __construct()
    {
        $this->_mWfRoles = new WfRole();
    }

    /**
     * |  Create WfRole 
     */
    public function createRole(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'roleName'        => 'required|string'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0801";
            $version = "01";
            $user = authUser($req);
            $words = explode(' ', $req->roleName);
            $acronym = '';
            if (sizeof($words) > 1) {
                foreach ($words as $word) {
                    $acronym .= strtoupper(substr($word, 0, 1));
                }
            } else
                $acronym = strtoupper($req->roleName);
            $isGroupExists = $this->_mWfRoles->checkExisting($req);
            if (collect($isGroupExists)->isNotEmpty())
                throw new Exception("WfRole Already Existing");
            $metaReqs = [
                'role_name'   => $req->roleName,
                'user_type'   => $acronym,
                'created_by'  => $user->id,
            ];
            $this->_mWfRoles->store($metaReqs); // Store in Violations table
            return responseMsgs(true, "Role Added Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",                $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    // Edit WfRole By Id
    public function updateRole(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'roleId'           => 'required|numeric',
            'roleName'         => 'required|string'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0802";
            $version = "01";
            $user = authUser($req);
            $words = explode(' ', $req->roleName);
            $acronym = '';
            if (sizeof($words) > 1) {
                foreach ($words as $word) {
                    $acronym .= strtoupper(substr($word, 0, 1));
                }
            } else
                $acronym = strtoupper($req->roleName);
            $getData = $this->_mWfRoles::findOrFail($req->roleId);
            $isExists = $this->_mWfRoles->checkExisting($req);
            if ($isExists && $isExists->where('id', '!=', $req->roleId)->isNotEmpty())
                throw new Exception("WfRole Already Existing");
            $metaReqs = [
                'role_name'   => $req->roleName,
                'user_type'   => $acronym,
                'created_by'  => $user->id,
                'updated_at'  => Carbon::now()
            ];
            $getData->update($metaReqs);
            return responseMsgs(true, "Role Updated Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",                  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Get WfRole BY Id
     */
    public function getRoleById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'roleId' => 'required|numeric'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0803";
            $version = "01";
            $getData = $this->_mWfRoles->recordDetails()->where('id', $req->roleId)->first();
            return $getData;
            if (collect($getData)->isEmpty())
                throw new Exception("Data Not Found");
            return responseMsgs(true, "View Role", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
    /**
     * Get WfRole List
     */
    public function getRoleList(Request $req)
    {
        try {
            $apiId = "0804";
            $version = "01";
            $getData = $this->_mWfRoles->recordDetails()->get();
            return responseMsgs(true, "View All Role's Records", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",               $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Delete WfRole By Id
     */
    public function deleteRole(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'roleId' => 'required'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0805";
            $version = "01";
            $role = $this->_mWfRoles::findOrFail($req->roleId);
            $role->update([
                'is_suspended' => true,
            ]);
            return responseMsgs(true, "Role Deleted", "",    $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
