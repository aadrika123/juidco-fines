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
 * | Created On  - 29/04/2024
 * | Created For - The Routes defined for the Rig Registration System Module
 * | Created By  - Arshad Hussain
 * | status      - Close by Developer
 * | close Date  - 09/05/2024
 */

    Route::get('/health-check', function () {
        return response()->json(['status' => 'ok']);
    });

Route::post('/rig-connection', function () {
    return ('Welcome to  rig machine route file');                                                                // 00
});

/**
 * | Grouped Route for middleware
 */
// Route::middleware(['request_logger'])->group(function () {
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
        Route::post('application/delete', 'deleteRigApplication');   # ❗❗❗                                      // Citizen / Admin
        Route::post('registration/apply-renewal', 'applyRigRenewal');                                           // Admin / Citizen // pending


        Route::post('get-approve-registrations', 'getApproveRegistration');                                     // Citizen
        Route::post('get-rejected-registrations', 'getRejectedRegistration');
        Route::post('application/get-wf-detials', 'getApplicationsDetails');                                    // Workflow

        Route::post('get-renewal-history', 'getRenewalHistory');
        Route::post('get-rejected-registration-list', 'getRejectedApplicationDetails');                         // Admin
        Route::post('get-approve-registration-list', 'getApprovedApplicationDetails');                          // Admin
        Route::post('get-approve-registration-list-V1', 'getApprovedApplicationDetails');                       // without user
        Route::post('application/searh-application', 'searchApplication');                                      // Admin

        Route::post('application/approve-license-data', 'getLicnenseDetails');                                  // Admin
        Route::post('application/renew-license', 'renewLicense');                                              //Renew Licencse

        Route::post('application/dashboard-details', 'rigDashbordDtls');                                        // Admin

        Route::post('application/edit-rig-details', 'editRigDetails');                                           // Admin
        Route::post('application/collection-report', 'listCollection');                                          // Admin

        Route::post('application/get-doc-to-upload', 'getDocToUpload');                                          // Admin/ Citizen
        Route::post('application/reupload-document', 'reuploadDocuments');                                       // Admin
        Route::post('application/get-uploaded-docs', 'getUploadDocuments');                                      // Admin/ Citizen
        Route::post('get-renewal-registration-details', 'getRenewalApplicationDetails');
        Route::post('btc-list-application', 'btcListJsk');                                                        // Workflow

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
        Route::post('back-to-jsk-list', 'btJskInbox');                                                          // Workflow
        Route::post('back-to-citizen', 'backToCitizen');                                                        // Workflow
        Route::post('generateLicense', 'generateLicense');                                                      // Workflow
        Route::post('application/get-uploaded-doc', 'getUploadDocumentsEsign');                                 // Admin/ Citizen
        Route::post('application/save-esighn-documents', 'saveEsighndocuments');                                // Esighn Documents
        Route::get('application/save-esighn', 'getUploadDocumentsEsigns');                                     // test case
        Route::post('application/get-sighnDocument', 'getSighnDocument');                                       // Admin/ Citizen

    });

    // payment operations
    Route::controller(RigPaymentController::class)->group(function () {
        Route::post("application/offline-payment", "offlinePayment");                                           # Admin
        Route::post('razorpay/initiate-payment', 'initiatePayment');                                            #_Initiate Online Payment ----------------- 0701
        Route::post('razorpay/save-response', 'saveRazorpayResponse');                                          #_Save Response of Online Payment --------- 0702
        Route::post("application/payment-receipt", "generatePaymentReceipt");                                   # Admin / Citizen
        Route::post('cash-verification-list', 'listCashVerification');                                          #_List of Cash Verification --------------- 0703
        Route::post('cash-verification-dtl', 'cashVerificationDtl');                                            #_Cash Verification Detail ---------------- 0704
        Route::post('verify-cash', 'verifyCash');                                                               #_Verify Cash ----------------------------- 0705
        Route::post('transaction-dactivation', 'deactivatePayment');                                            #_Transaction Deactivation  ----------------------------- 0705
        Route::post('search/transaction-cheque', 'searchTransaction');                                          #_Transaction Deactivation  ----------------------------- 0705
        Route::post('search/transaction-cheque-dtl', 'chequeDtlByIdRig');                                       #_Transaction Deactivation  ----------------------------- 0705
        Route::post('transaction/cheque-clear-bounce', 'chequeClearance');                                       #_Transaction Deactivation  ----------------------------- 0705
        Route::post('transaction/cheque-edit-dtls', 'editChequeNo');                                             #_Transaction Deactivation  ----------------------------- 0705
    });
});
