<?php

use App\Http\Controllers\API\Master\CountryController;
use App\Http\Controllers\API\Master\DepartmentController;
use App\Http\Controllers\API\Master\SectionController;
use App\Http\Controllers\API\Master\UserMasterController;
use App\Http\Controllers\API\Master\UserTypeController;
use App\Http\Controllers\API\Master\ViolationController;
use App\Http\Controllers\API\Master\ViolationSectionController;
use App\Http\Controllers\API\Master\WfRoleMasterController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\DeactivationController;
use App\Http\Controllers\Penalty\PaymentController;
use App\Http\Controllers\Penalty\PenaltyRecordController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware([])->group(function () {

    /**
     * | 
         Controller No : 1
     */
    Route::controller(UserController::class)->group(function () {
        Route::post('login', 'loginAuth')->withoutMiddleware('auth:sanctum');                #_Login ------------------- 0101
        Route::post('logout', 'logout');                                                     #_Logout ------------------ 0102
        Route::post('change-password', 'changePass');                                        #_Change Password --------- 0103
        // Route::post('otp/change-password', 'changePasswordByOtp');                        #_Forget Password --------- 0104
        Route::post('my-profile-details', 'myProfileDetails');                               #_Profile Details --------- 0105
    });

    /**
     * | API Department CRUD operation
         Controller No : 2
     */
    Route::controller(DepartmentController::class)->group(function () {
        Route::post('fines/department/crud/save', 'createDepartment');                              #_Save -------------------- 0201
        Route::post('fines/department/crud/edit', 'updateDepartment');                              #_Edit  ------------------- 0202
        Route::post('department/crud/get', 'getDepartmentById');                              #_Get By Id --------------- 0203
        Route::post('fines/department/crud/list', 'getDepartmentList');                             #_Get All ----------------- 0204
        Route::post('fines/department/crud/delete', 'deleteDepartment');                            #_Delete ------------------ 0205
        /**
         to be removed from front and backend
         */
        Route::post('fines/department/list', 'getDepartmentList');                                 #_Get All ------------------ 0205
    });

    /**
     * | API Section CRUD operation
         Controller No : 3
     */
    Route::controller(SectionController::class)->group(function () {
        Route::post('fines/section/crud/save', 'createSection');                                   #_Save -------------------- 0301
        Route::post('section/crud/edit', 'updateSection');                                   #_Edit  ------------------- 0302
        Route::post('section/crud/get', 'getSectionById');                                   #_Get By Id --------------- 0303
        Route::post('fines/section/crud/list', 'getSectionList');                                  #_Get All ----------------- 0304
        Route::post('section/crud/delete', 'deleteSection');                                 #_Delete ------------------ 0305
        Route::post('fines/section/list', 'getSectionListById');                                   #_Get All ----------------- 0306
    });

    /**
     * | API Violation CRUD operation
         Controller No : 4
     */
    Route::controller(ViolationController::class)->group(function () { 
        Route::post('fines/violation/crud/save', 'createViolation');                                                  #_Save -------------------------------- 0401
        Route::post('violation/crud/edit', 'updateViolation');                                                  #_Edit  ------------------------------- 0402
        Route::post('fines/violation/crud/get', 'ViolationById');                                                     #_Get By Id --------------------------- 0403
        Route::post('fines/violation/crud/list', 'getViolation');                                                     #_Get All ----------------------------- 0404
        Route::post('fines/violation/crud/delete', 'deleteViolation');                                                #_Delete ------------------------------ 0405
        Route::post('fines/violation/by-department', 'violationByDepartment');                                        #_Violation By Department-------------- 0406
        Route::post('fines/violation/onspot', 'onSpotViolation');                                                     #_Get All ----------------------------- 0407
        Route::post('fines/v2/violation/crud/list', 'getViolation')->withoutMiddleware(['auth:sanctum','expireBearerToken']);               #_Violation List Outside MiddleWare --- 0404

    });

    /**
     * | API Violation Section CRUD operation
         Controller No : 5
     */
    Route::controller(ViolationSectionController::class)->group(function () {
        Route::post('violation-section/crud/save', 'createViolationSection');                #_Save -------------------- 0501
        Route::post('violation-section/crud/edit', 'updateViolationSection');                #_Edit  ------------------- 0502
        Route::post('violation-section/crud/get', 'getSectionById');                         #_Get By Id --------------- 0503
        Route::post('violation-section/crud/list', 'getSectionList');                        #_Get All ----------------- 0504
        Route::post('violation-section/crud/delete', 'deleteSection');                       #_Delete ------------------ 0505
        Route::post('user-list', 'getUserList');                                             #_Get All ----------------- 0506
        Route::post('challan-category/list', 'getCategoryList');                             #_Get All ----------------- 0507
    });


    // ---------------------------------------------------------------- Master API End ---------------------------------------------------------------
    /**
     * | API Penalty Record Application Form
         Controller No : 6
     */
    Route::controller(PenaltyRecordController::class)->group(function () {
        Route::post('fines/penalty-record/crud/save', 'store');                                      #_Save ---------------- 0601
        Route::post('fines/penalty-record/crud/show', 'show');                                       #_Get By Id ----------- 0602
        Route::post('fines/penalty-record/crud/active-all', 'activeAll');                            #_Get Active All ------ 0603
        Route::post('fines/penalty-record/crud/delete', 'delete');                                   #_Delete -------------- 0604
        Route::post('fines/penalty-record/crud/search', 'searchByApplicationNo');                    #_Search -------------- 0605

        Route::post('fines/penalty-record/get-uploaded-document', 'getUploadedDocuments');                                       #_get uploaded documents ---------- 0606
        Route::post('fines/penalty-record/inbox', 'inbox');                                                                      #_inbox details ------------------- 0607
        Route::post('fines/penalty-record/detail', 'penaltyDetails');                                                            #_penalty details ----------------- 0608
        Route::post('fines/penalty-record/approve', 'approvePenalty');                                                           #_penalty approval ---------------- 0609
        Route::post('fines/penalty-record/recent-applications', 'recentApplications');                                           #_get recent applications --------- 0610
        Route::post('fines/penalty-record/recent-challans', 'recentChallans');                                                   #_get recent challans ------------- 0611
        Route::post('fines/penalty-record/challan-search', 'searchChallan');                                                     #_get search challans ------------- 0612
        Route::post('fines/penalty-record/get-challan', 'challanDetails')->withoutMiddleware(['auth:sanctum','expireBearerToken']);                    #_get challans details ------------ 0613
        Route::post('fines/penalty-record/offline-challan-payment', 'offlinechallanPayment');                                    #_challan payment  ---------------- 0614
        Route::post('fines/penalty-record/payment-receipt', 'paymentReceipt')->withoutMiddleware(['auth:sanctum','expireBearerToken']);                #_get payment receipt details ----- 0615
        Route::post('fines/penalty-record/on-spot-challan', 'onSpotChallan');                                                    #_get on-spot challans ------------ 0616
        Route::post('fines/report/violation-wise', 'violationData');                                                             #_violations wise report ---------- 0617
        Route::post('fines/report/challan-wise', 'challanData');                                                                 #_challan wise report ------------- 0618
        Route::post('fines/report/collection-wise', 'collectionData');                                                           #_collection wise report ---------- 0619
        Route::post('fines/report/comparison', 'comparisonReport');                                                              #_comparison report --------------- 0620
        Route::post('fines/v2/penalty-record/get-challan', 'mobileChallanDetails');                                              #_get challans details mobile ----- 0621
        Route::post('fines/v2/penalty-record/crud/show', 'showV2');                                                              #_penalty record ------------------ 0622
        Route::post('fines/penalty-record/citizen-challan-search', 'citizenSearchChallan')->withoutMiddleware(['auth:sanctum','expireBearerToken']);   #_get search challans ------------- 0623
        Route::post('fines/penalty-record/get-tran-no', 'getTranNo')->withoutMiddleware(['auth:sanctum','expireBearerToken']);                         #_Get Tran No --------------------- 0624
        Route::post('fines/mini-dashboard', 'miniLiveDashboard');                                                                  #_Mini Live Dashboard --------------------- 0625
       
        Route::post('testWhatsapp', 'testWhatsapp')->withoutMiddleware(['auth:sanctum','expireBearerToken']);                         #_Whatsaap Test --------------------- 0624
    });

    /**
     * | Api List for Online Payment
        Controller No : 7
     */
    Route::controller(PaymentController::class)->group(function () {
        Route::post('fines/razorpay/initiate-payment', 'initiatePayment');                                        #_Initiate Online Payment ----------------- 0701
        Route::post('fines/razorpay/save-response', 'saveRazorpayResponse')->withoutMiddleware(['auth:sanctum','expireBearerToken']);   #_Save Response of Online Payment --------- 0702
        Route::post('fines/cash-verification-list', 'listCashVerification');                                      #_List of Cash Verification --------------- 0703
        Route::post('fines/cash-verification-dtl', 'cashVerificationDtl');                                        #_Cash Verification Detail ---------------- 0704
        Route::post('fines/verify-cash', 'verifyCash');                                                           #_Verify Cash ----------------------------- 0705
        Route::post('fines/citizen-online-payment', 'initiatePayment')->withoutMiddleware(['auth:sanctum','expireBearerToken']);        #_Initiate Online Payment By Citizen ------ 0701
    });

    /**
     * | API Wf Role CRUD operation
         Controller No : 8
     */
    Route::controller(WfRoleMasterController::class)->group(function () {
        Route::post('wfrole/crud/save', 'createRole');                              #_Save -------------------- 0801
        Route::post('wfrole/crud/edit', 'updateRole');                              #_Edit  ------------------- 0802
        Route::post('wfrole/crud/get', 'getRoleById');                              #_Get By Id --------------- 0803
        Route::post('wfrole/crud/list', 'getRoleList');                             #_Get All ----------------- 0804
        Route::post('wfrole/crud/delete', 'deleteRole');                            #_Delete ------------------ 0805
    });

    /**
     * | API User_Master CRUD operation
         Controller No : 9
     */
    Route::controller(UserMasterController::class)->group(function () {
        Route::post('user/crud/create', 'createUser');                                          #_Save -------------------- 0901
        Route::post('user/crud/edit', 'updateUser');                                            #_Edit  ------------------- 0902
        Route::post('user/crud/get', 'getUserById');                                            #_Get By Id --------------- 0903
        Route::post('user/crud/list', 'getUserList');                                           #_Get All ----------------- 0904
        Route::post('user/crud/delete', 'deleteUser');                                          #_Delete ------------------ 0905
        Route::post('user/set-password', 'setPassword')->withoutMiddleware(['auth:sanctum','expireBearerToken']);     #_Set Password ------------ 0906
        // Route::post('fines/ward-list', 'wardList');                                                   #_Ward List --------------- 0907
        Route::post('user/role-assign', 'roleAssign');                                          #_Role Assignment --------- 0908
        Route::post('fines/user/enf-officer', 'getOfficers');      #_Get Officer Details ----- 0909
    });

    /**
     * | Deactivation Conroller
         Controller No : 10
     */
    Route::post('fines/deactivate-application', [DeactivationController::class, 'deactivateApplication'])->withoutMiddleware(['auth:sanctum','expireBearerToken']);   #_Deactivate Application ------------1001
    Route::post('fines/deactivate-challan', [DeactivationController::class, 'deactivateChallan'])->withoutMiddleware(['auth:sanctum','expireBearerToken']);           #_Deactivate Application ------------1002
    Route::post('fines/deactivate-payment', [DeactivationController::class, 'deactivatePayment'])->withoutMiddleware(['auth:sanctum','expireBearerToken']);           #_Deactivate Application ------------1003


});
