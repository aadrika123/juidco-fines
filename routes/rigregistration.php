<?php

use App\Http\Controllers\Rig\RigPaymentController;
use App\Http\Controllers\Rig\RigRegistrationController;
use App\Http\Controllers\Rig\RigWorkflowController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| rig Module Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an Rig module.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

/**
 * | ----------------------------------------------------------------------------------
 * | Rig Registration Module Routes |
 * |-----------------------------------------------------------------------------------
 * | Created On- 29-04-2024
 * | Created For- The Routes defined for the Rig Registration System Module
 * | Created By- Arshad Hussain
 */

Route::post('/rig-connection', function () {
    return ('Welcome to  rig machine route file');                                                                // 00
});

/**
 * | Grouped Route for middleware
 */
Route::middleware([])->group(function () {
    /**
     * | Rig Machine Registration Operation and more fundamental oprations
        | Serial No : 01
        | Status : Open
     */
    Route::controller(RigRegistrationController::class)->group(function () {
        Route::post('application/apply-rig-registration', 'applyRigRegistration');                              // Citizen
        Route::post('application/get-registration-list', 'getApplicationList');                                 // Citizen
        Route::post('application/get-details', 'getApplicationDetails');                                        // Citizen
        Route::post('application/delete', 'deleteRigApplication');   # ❗❗❗                                           // Citizen / Admin
        Route::post('registration/apply-renewal', 'applyRigRenewal');                                           // Admin / Citizen // pending
        Route::post('application/get-uploaded-docs', 'getUploadDocuments');                                     // Admin/ Citizen

        Route::post('get-approve-registrations', 'getApproveRegistration');                                     // Citizen
        Route::post('get-rejected-registrations', 'getRejectedRegistration');

        Route::post('get-renewal-history', 'getRenewalHistory');
    });

    /**
     * | Rig Workflow 
     */
    Route::controller(RigWorkflowController::class)->group(function () {
        Route::post('inbox', 'inbox');                                                                          // Workflow
        Route::post('outbox', 'outbox');                                                                        // Workflow
        Route::post('post-next-level', 'postNextLevel');                                                        // Workflow
        Route::post('special-inbox', 'RigSpecialInbox');                                                        // Workflow
        Route::post('escalate', 'postEscalate');                                                                // Workflow                     
        Route::post('doc-verify-reject', 'docVerifyRejects');                                                   // Workflow
        Route::post('final-verify-reject', 'finalApprovalRejection');                                           // Workflow
        Route::post('list-approved-application', 'listfinisherApproveApplications');                            // Workflow
        Route::post('list-rejected-application', 'listfinisherRejectApplications');                             // Workflow
    });

    // payment operations 
    Route::controller(RigPaymentController::class)->group(function () {
        Route::post('razorpay/initiate-payment', 'initiatePayment');                                        #_Initiate Online Payment ----------------- 0701   
        Route::post('razorpay/save-response', 'saveRazorpayResponse')->withoutMiddleware(['auth:sanctum', 'expireBearerToken']);   #_Save Response of Online Payment --------- 0702                                                                     
    });
});