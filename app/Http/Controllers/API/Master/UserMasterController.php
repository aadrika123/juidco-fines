<?php

namespace App\Http\Controllers\API\Master;

use App\DocUpload;
use App\Http\Controllers\Controller;
use App\Mail\VerifyEmail;
use App\Models\UlbWardMaster;
use App\Models\User;
use App\Models\WfRole;
use App\Models\WfRoleusermap;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * =======================================================================================================
 * ===================         Created By : Umesh Kumar        ==========================================
 * ===================         Created On : 06-10-2023          ==========================================
 * =======================================================================================================
 * | Status : Open
 */
class UserMasterController extends Controller
{
    private $_mUsers;

    public function __construct()
    {
        $this->_mUsers = new User();
    }

    /**
     * |  Add User 
     */
    public function createUser(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'firstName'               => 'required|string',
            'middleName'              => 'nullable|string',
            'lastName'                => 'required|string',
            'designation'             => 'required|string',
            'mobileNo'                => 'required|numeric|digits:10',
            'address'                 => 'nullable|string',
            'employeeCode'            => 'required|string',
            'signature'               => 'nullable|mimes:jpeg,png,jpg|max:2048',
            'profile'                 => 'nullable|mimes:jpeg,png,jpg|max:2048',
            'email'                   => 'required|email',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0901";
            $version = "01";
            $authUser = authUser();
            $metaReqs = [];
            $docUpload = new DocUpload;
            $isGroupExists = $this->_mUsers->checkExisting($req);
            if (collect($isGroupExists)->isNotEmpty())
                throw new Exception("User Already Existing");

            if ($req->file('signature')) {
                $refImageName = Str::random(5);
                $file = $req->file('signature');
                $imageName = $docUpload->upload($refImageName, $file, 'FinePenalty/Users');
                $metaReqs = array_merge($metaReqs, [
                    'signature' => 'FinePenalty/Users/' . $imageName,
                ]);
            }

            if ($req->file('profile')) {
                $refImageName = Str::random(5);
                $file = $req->file('profile');
                $imageName = $docUpload->upload($refImageName, $file, 'FinePenalty/Users');
                $metaReqs = array_merge($metaReqs, [
                    'profile_image' => 'FinePenalty/Users/' . $imageName,
                ]);
            }

            $metaReqs = array_merge($metaReqs, [
                'first_name'     => ucfirst($req->firstName),
                'middle_name'    => ucfirst($req->middleName),
                'last_name'      => ucfirst($req->lastName),
                'user_name'      => ucfirst($req->firstName) . ' ' . ucfirst($req->middleName) . ' ' . ucfirst($req->lastName),
                'mobile'         => $req->mobileNo,
                'email'          => strtolower($req->email),
                'ulb_id'         => $authUser->ulb_id,
                'address'        => $req->address,
                'designation'    => $req->designation,
                'employee_code'  => $req->employeeCode,
                'created_by'     => $authUser->id,
                'password'       => Hash::make(ucfirst($req->firstName) . '@' . substr($req->mobileNo, 7, 3)),
                'ip_address'     => getClientIpAddress(),
            ]);

            $user = $this->_mUsers->store($metaReqs);
            $token = Password::createToken($user);
            $user->update(["remember_token" => $token]);

            // $url = "http://203.129.217.246/fines";
            // $url = "http://192.168.0.159:5000/fines";
            // $resetLink = $url . "/set-password/{$token}/{$user->id}";
            // $emailContent = "Hello,\n\nYou have requested to set your password. Click the link below to reset it:\n\n{$resetLink}\n\nIf you didn't request this password reset, you can ignore this email.";
            // $htmlEmailContent = "<p>Hello,</p><p>You have requested to set your password. Click the link below to reset it:</p><a href='{$resetLink}'>Reset Password</a><p>If you didn't request this password reset, you can ignore this email.</p>";
            // Mail::raw($emailContent, function ($message) use ($user) {
            //     $message->to($user->email);
            //     $message->subject('Password Reset');
            // });

            return responseMsgs(true, "Your Password is First Name @ Last 3 digit of your mobile No.", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Update User
     */
    public function updateUser(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'userId'                  => 'required',
            'firstName'               => 'required|string',
            'lastName'                => 'required|string',
            'designation'             => 'required|string',
            'mobileNo'                => 'required|digits:10',
            'address'                 => 'required|string',
            'employeeCode'            => 'required|string',
            'signature'               => 'nullable|mimes:jpeg,png,jpg|max:2048',
            'profile'                 => 'nullable|mimes:jpeg,png,jpg|max:2048',
            'email'                   => 'required|email',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0902";
            $version = "01";
            $user = authUser();
            $docUpload = new DocUpload;
            $getUser = $this->_mUsers::findOrFail($req->userId);
            $isExists = $this->_mUsers->checkExisting($req);

            $metaReqs = [
                'first_name'     => $req->firstName,
                'middle_name'    => $req->middleName,
                'last_name'      => $req->lastName,
                'user_name'      => $req->firstName . ' ' . $req->middleName . ' ' . $req->lastName,
                'mobile'         => $req->mobileNo,
                'email'          => strtolower($req->email),
                'address'        => $req->address,
                'designation'    => $req->designation,
                'employee_code'  => $req->employeeCode,
            ];

            if ($req->file('signature')) {
                $refImageName = Str::random(5);
                $file = $req->file('signature');
                $imageName = $docUpload->upload($refImageName, $file, 'FinePenalty/Users');
                $metaReqs = array_merge($metaReqs, [
                    'signature' => 'FinePenalty/Users/' . $imageName,
                ]);
            }

            if ($req->file('profile')) {
                $refImageName = Str::random(5);
                $file = $req->file('profile');
                $imageName = $docUpload->upload($refImageName, $file, 'FinePenalty/Users');
                $metaReqs = array_merge($metaReqs, [
                    'profile_image' => 'FinePenalty/Users/' . $imageName,
                ]);
            }
            $getUser->update($metaReqs); // Store in Violations table
            return responseMsgs(true, "User Updated Successfully", $metaReqs, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",                  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Get User BY Id
     */
    public function getUserById(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'userId' => 'required|numeric'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0903";
            $version = "01";
            $getData = $this->_mUsers->recordDetails($req)->where('id', $req->userId)->first();
            if (collect($getData)->isEmpty())
                throw new Exception("Data Not Found");

            return responseMsgs(true, "View User", $getData, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Get User's List
     */
    public function getUserList(Request $req)
    {
        try {
            $apiId = "0904";
            $version = "01";
            $perPage = $req->perPage ?? 10;
            $getData = $this->_mUsers->recordDetails($req)->get();

            $filteredData = $getData->filter(function ($item) {
                return $item['user_type'] !== 'ADMIN';
            });

            return responseMsgs(true, "View All User's Record", $filteredData->values(), $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",                             $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * Delete User By Id
     */
    public function deleteUser(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'userId' => 'required'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0905";
            $version = "01";
            $mWfRoleusermap = new WfRoleusermap();
            $user = $this->_mUsers::findOrFail($req->userId);
            $roleMaps = $mWfRoleusermap->where('user_id', $req->userId)
                ->where('is_suspended', false)
                ->orderByDesc('id')
                ->first();

            $user->update(['suspended' => true]);
            if ($roleMaps)
                $roleMaps->update(['is_suspended' => true]);

            return responseMsgs(true, "User Deleted", "",    $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Set Password
     */
    public function setPassword(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required',
            'password' => 'required',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0906";
            $version = "01";
            //check user suspended status
            $userDetail = User::where('id', $req->id)
                ->where('suspended', false)
                ->first();
            if (!$userDetail)
                throw new Exception("User Not Found");

            $bearer = $req->header()['authorization'][0];
            $token = explode(' ', $bearer)[1];

            if ($userDetail->remember_token != $token)
                throw new Exception("You Are Not Authenticated");

            $userDetail->password = Hash::make($req->password);
            $userDetail->save();

            return responseMsgs(true, "Password Reset Succesfully", "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",            $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Ward List
     */
    public function wardList(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ulbId' => 'nullable',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0907";
            $version = "01";
            $ulbId = $req->ulbId ?? authUser($req)->ulb_id;
            if (!$ulbId)
                throw new Exception("Please Provide Ulb");

            $mUlbWardMaster = new UlbWardMaster();
            $wardList = $mUlbWardMaster->getWardList($ulbId);

            return responseMsgs(true, "Ward List", $wardList, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "",  $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Role Assign
     */
    public function roleAssign(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'userId' => 'required|int',
            'roleId' => 'required|int',
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0908";
            $version = "01";
            $mWfRoleusermap = new WfRoleusermap();
            $mWfRole = new WfRole();
            $mUser = $this->_mUsers;

            $roleDtl = $mWfRole->find($req->roleId);
            $userDtl = $mUser->find($req->userId);
            if (!$roleDtl)
                throw new Exception("Role Not Available");

            if (!$userDtl)
                throw new Exception("User Not Available");

            $roleMap =  $mWfRoleusermap->where('user_id', $req->userId)
                ->orderByDesc('id')
                ->first();

            $mreq = [
                "wf_role_id" => $req->roleId,
                "user_id"    => $req->userId,
                "created_by" => authUser()->id,
            ];

            DB::beginTransaction();

            if ($roleMap)
                $roleMap->update(['is_suspended' => true]);

            $mWfRoleusermap->store($mreq);
            $userDtl->update(["user_type" => $roleDtl->user_type]);

            DB::commit();
            return responseMsgs(true, "Role Assigned to the user", "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",           $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | getOfficers
     */
    public function  getOfficers(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'userType' => 'required|in:EO,EC',
            'ulbId'    => 'required|int'
        ]);
        if ($validator->fails())
            return validationError($validator);
        try {
            $apiId = "0909";
            $version = "01";
            $docUrl = Config::get('constants.DOC_URL');
            $ECRole = Config::get('constants.ROLES.ENFORCEMENTCELL');
            $EORole = Config::get('constants.ROLES.ENFORCEMENTOFFICER');
            $mUser = $this->_mUsers;
            $ulbId = $req->ulbId;

            $data = $mUser
                ->select(
                    'users.id',
                    'name as user_name',
                    'mobile',
                    'email',
                    'user_type',
                    'address',
                    DB::raw(
                        "CASE 
                                WHEN photo IS NULL THEN ''
                                    else 
                                concat('$docUrl/',photo)
                            END as photo"
                    )
                )
                ->where('ulb_id', $ulbId)
                ->join('wf_roleusermaps', 'wf_roleusermaps.user_id', 'users.id')
                ->where('wf_roleusermaps.is_suspended', false);

            //Enforcement Cell
            if ($req->userType == 'EC')
                $data  = $data->where('wf_roleusermaps.wf_role_id', $ECRole)
                    ->get();

            //Enforcement Officer
            if ($req->userType == 'EO')
                $data  = $data->where('wf_roleusermaps.wf_role_id', $EORole)
                    ->get();

            if (collect($data)->isEmpty())
                throw new Exception("Data Not Found");

            DB::commit();
            return responseMsgs(true, "Officer Detail", $data, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "",           $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
