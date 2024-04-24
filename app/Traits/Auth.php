<?php

namespace App\Traits;

use App\Models\Menu\WfRolemenu;
use App\Models\User;
use App\Models\Workflows\WfRoleusermap;
use App\Repository\Menu\Concrete\MenuRepo;
use Illuminate\Http\Request;
use App\MicroServices\DocUpload;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Razorpay\Api\Collection;

/**
 * Trait for saving and editing the Users and Citizen register also
 * Created for reducing the duplication for the Saving and editing codes
 * --------------------------------------------------------------------------------------------------------
 * Created by-Anshu Kumar
 * Updated by-Sam kerketta
 * Created On-16-07-2022 
 * --------------------------------------------------------------------------------------------------------
 */

trait Auth
{

    /**
     * Saving User Credentials 
     */
    public function saving($user, $request)
    {
        $docUpload = new DocUpload;
        $imageRelativePath = 'Uploads/User/Photo';
        $signatureRelativePath = 'Uploads/User/Signature';
        $user->name = $request->name;
        $user->mobile = $request->mobile;
        $user->email = $request->email;
        $user->alternate_mobile = $request->altMobile;
        $user->address = $request->address;
        $user->ulb_id = authUser()->ulb_id;
        if ($request->userType) {
            $user->user_type = $request->userType;
        }
        if ($request->description) {
            $user->description = $request->description;
        }
        if ($request->workflowParticipant) {
            $user->workflow_participant = $request->workflowParticipant;
        }
        if ($request->photo) {
            $filename = explode('.', $request->photo->getClientOriginalName());
            $document = $request->photo;
            $imageName = $docUpload->upload($filename[0], $document, $imageRelativePath);
            $user->photo_relative_path = $imageRelativePath;
            $user->photo = $imageName;
        }
        if ($request->signature) {
            $filename = explode('.', $request->signature->getClientOriginalName());
            $document = $request->signature;
            $imageName = $docUpload->upload($filename[0], $document, $signatureRelativePath);
            $user->sign_relative_path = $signatureRelativePath;
            $user->signature = $imageName;
        }

        $token = Str::random(80);                       //Generating Random Token for Initial
        $user->remember_token = $token;
    }

    /**
     * Saving Extra User Credentials
     */
    public function savingExtras($user, $request)
    {
        if ($request->suspended) {
            $user->suspended = $request->suspended;
        }
        if ($request->superUser) {
            $user->super_user = $request->superUser;
        }
    }
}
