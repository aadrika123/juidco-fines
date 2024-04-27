<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePassRequest;
use App\Http\Requests\Auth\OtpChangePass;
use App\Http\Requests\Auth\UserRegistrationRequest;
use App\Http\Requests\InfractionRecordingFormRequest;
use App\Models\AppStatus;
use App\Models\PenaltyRecord;
use App\Models\User;
use App\Models\WfRoleusermap;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends Controller
{
    // use Auth;
    private $_mUser;
    public function __construct()
    {
        $this->_mUser = new User();
    }

    /**
     * | User Login
     */
    public function loginAuth(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'email'     => 'required|email',
                'password'  => 'required',
                'type'      => "nullable|in:mobile"
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $mWfRoleusermap = new WfRoleusermap();
            $mAppStatus = new AppStatus();
            $apiId   = "0101";
            $version = "01";
            $user = $this->_mUser->getUserByEmail($req->email);
            if (!$user)
                throw new Exception("Invalid Credentials");
            if ($user->suspended == true)
                throw new Exception("You are not authorized to log in!");
            if (Hash::check($req->password, $user->password)) {

                $users = $this->_mUser->find($user->id);
                $maAllow = $users->max_login_allow;
                $remain = ($users->tokens->count("id")) - $maAllow;
                $count = 0;
                foreach ($users->tokens->sortBy("id") as  $key => $token) {
                    if ($remain < $key) {
                        break;
                    }
                    $count += 1;
                    $token->expires_at = Carbon::now();
                    $token->update();
                    $token->delete();
                }

                $token = $user->createToken('my-app-token', ['expires_in' => 60])->plainTextToken;
                $roleDetail = $mWfRoleusermap->getRoleDetailsByUserId($user->id);
                $appData = $mAppStatus->where('status', 1)->first();

                if ($req->type == 'mobile') {
                    $data['appStatus'] = $appData->app_status;
                    $data['url']       = $appData->url;
                }

                $data['token'] = $token;
                $data['userDetails'] = $user;
                $data['userDetails']['role_name'] = $roleDetail['role'] ?? "";

                return responseMsgs(true, "You have Logged In Successfully", $data, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
            }
            throw new Exception("Invalid Credentials");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | logout
     */
    public function logout(Request $req)
    {
        try {
            $apiId = "0102";
            $version = "01";
            $token = $req->user()->currentAccessToken();                               #_Current Accessable Token
            $token->expires_at = Carbon::now();
            $token->save();
            return responseMsgs(true, "You have Logged Out", [], $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Changing Password
     */
    public function changePass(ChangePassRequest $request)
    {
        try {
            $apiId = "0103";
            $version = "01";
            $userId = auth()->user()->id;
            $user = User::find($userId);
            $request->password;
            $validPassword = Hash::check($request->password, $user->password);
            if ($validPassword) {
                #_Save New Password
                $user->password = Hash::make($request->newPassword);
                $user->save();

                #_Token Expire
                $token = $request->user()->currentAccessToken();                               #_Current Accessable Token
                $token->expires_at = Carbon::now();
                $token->save();
                return responseMsgs(true, 'Successfully Changed the Password', "", $apiId, $version, responseTime(), $request->getMethod(), $request->deviceId);
            }
            throw new Exception("Old Password dosen't Match!");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | Change Password by OTP 
     * | Api Used after the OTP Validation
     */
    // public function changePasswordByOtp(OtpChangePass $request)
    // {
    //     try {
    //         $apiId = "0104";
    //         $version = "01";
    //         $id = auth()->user()->id;
    //         $user = User::find($id);
    //         $user->password = Hash::make($request->password);
    //         $user->save();

    //         #_Token Expire
    //         $token = $request->user()->currentAccessToken();                               #_Current Accessable Token
    //         $token->expires_at = Carbon::now();
    //         $token->save();
    //         return responseMsgs(true, 'Successfully Changed the Password', "", $apiId, $version, responseTime(), $request->getMethod(), $request->deviceId);
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $request->getMethod(), $request->deviceId);
    //     }
    // }

    /**
     * | For Showing Logged In User Details 
     * | $userId = Get the id of current user 
     */
    public function myProfileDetails(Request $req)
    {
        try {
            $apiId = "0105";
            $version = "01";
            $userId = auth()->user()->id;
            $mUser = new User();
            $details = $mUser->getUserById($userId);
            $usersDetails = [
                'id'            => $details->id,
                'name'          => $details->user_name,
                'mobile'        => $details->mobile,
                'email'         => $details->email,
                'ulb_name'      => $details->ulb_name,
                'signature'     => $details->signature,
                'photo'         => $details->photo,
            ];

            return responseMsgs(true, "Data Fetched", $usersDetails, $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
