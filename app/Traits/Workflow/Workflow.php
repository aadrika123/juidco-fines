<?php

namespace App\Traits\Workflow;

use App\Models\WorkflowCandidate;
use App\Models\WfRoleusermap;
use App\Models\WfWardUser;
use App\Models\WfWorkflow;
use App\Models\WfWorkflowrolemap;
use Carbon\Carbon;
use Exception;
use App\Models\WfRole;
use Illuminate\Support\Facades\DB;

/**
 * Trait for Workflows
 * Created By-Tannu Verma
 * Created On-13-06-2023 
 * --------------------------------------------------------------------------------------
 */

trait Workflow
{
    /**
     * Function for storing or saving the workflows 
     */

    public function savingWorkflow($workflow, $request)
    {
        $workflow->module_id = $request->ModuleID;
        $workflow->workflow_name = $request->workflow_name;
        $workflow->save();
        return response()->json(['Successfully Saved The Workflow'], 200);
    }

    /**
     * Check workflow Candidate already existing
     */
    // public function checkExisting($request)
    // {
    //     return  WorkflowCandidate::where('ulb_workflow_id', '=', $request->UlbWorkflowID)
    //         ->where('user_id', '=', $request->UserID)
    //         ->first();
    // }
    // public function checkExisting($request)
    // {
    //     return  WorkflowCandidate::where('ulb_workflow_id', '=', $request->UlbWorkflowID)
    //         ->where('user_id', '=', $request->UserID)
    //         ->first();
    // }

    /**
     * Function for Saving and Editing Workflow Candidates
     */
    public function savingWorkflowCandidates($wc, $request)
    {
        $wc->ulb_workflow_id = $request->UlbWorkflowID;
        $wc->forward_id = $request->ForwardID;
        $wc->backward_id = $request->BackwardID;
        $wc->full_movement = $request->FullMovement;
        $wc->is_admin = $request->IsAdmin;
        $wc->user_id = $request->UserID;
        $wc->save();
        return response()->json('Successfully Saved the Workflow Candidates', 200);
    }

    // Fetching workflows as array
    public function fetchWorkflow($workflow, $arr)
    {
        foreach ($workflow as $workflows) {
            $val['id'] = $workflows->id ?? '';
            $val['module_id'] = $workflows->module_id ?? '';
            $val['workflow_name'] = $workflows->workflow_name ?? '';
            $val['module_name'] = $workflows->module_name ?? '';
            $val['deleted_at'] = $workflows->deleted_at ?? '';
            $val['created_at'] = $workflows->created_at ?? '';
            $val['updated_at'] = $workflows->updated_at ?? '';
            array_push($arr, $val);
        }
        return response()->json($arr, 200);
    }

    // Fetching Workflow Candidates
    public function fetchWorkflowCandidates($wc, $arr)
    {
        foreach ($wc as $wcs) {
            $val['id'] = $wcs->id ?? '';
            $val['ulb_workflow_id'] = $wcs->ulb_workflow_id ?? '';
            $val['workflow_name'] = $wcs->workflow_name ?? '';
            $val['user_id'] = $wcs->user_id ?? '';
            $val['user_name'] = $wcs->user_name ?? '';
            $val['forward_id'] = $wcs->forward_id ?? '';
            $val['forward_user'] = $wcs->forward_user ?? '';
            $val['backward_id'] = $wcs->backward_id ?? '';
            $val['backward_user'] = $wcs->backward_user ?? '';
            $val['full_movement'] = $wcs->full_movement ?? '';
            $val['is_admin'] = $wcs->is_admin ?? '';
            array_push($arr, $val);
        }
        $message = ["status" => true, "message" => "Date Fetched", "data" => $arr];
        return response()->json($message, 200);
    }

    /**
     * | Created On - 11/10/2022 
     * | Created By - Anshu Kumar
       | ----------- Function used to determine the current user while applying to any module -------- |
     * | @param workflowId > workflow id applied module
     */
    public function getWorkflowCurrentUser($workflowId)
    {
        $query = WfWorkflowrolemap::select('*')
            ->join('wf_roles', 'wf_roles.id', 'wf_workflowrolemaps.wf_role_id')
            ->where('workflow_id', $workflowId)
            ->get();
        return $query;
    }

    /** | Code to be used to determine initiator
    $workflows = $this->getWorkflowCurrentUser($workflow_id);
    $collectWorkflows = collect($workflows);
    $filtered = $collectWorkflows->filter(function ($value, $key) {
        return $value;
    });

    return $filtered->firstWhere('is_initiator', true);
     */

    /**
     * | get Workflow Data for Initiator
     * | @param userId > Logged In user ID
     * | @param workflowId > Workflow ID
     */
    public function getWorkflowInitiatorData($userId, $workflowId)
    {
        $query = "SELECT 
                        wf.id,
                        wf.workflow_id,
                        wf.wf_role_id,
                        r.role_name,
                        wf.is_initiator,
                        wf.is_finisher,
                        rum.user_id,
                        wu.ward_id
                FROM wf_workflowrolemaps  wf
                INNER JOIN (SELECT * FROM wf_roleusermaps WHERE user_id=$userId) rum ON rum.wf_role_id=wf.wf_role_id
                INNER JOIN (SELECT * FROM wf_roles) r ON r.id=rum.wf_role_id
                INNER JOIN (SELECT * FROM wf_ward_users WHERE user_id=$userId) wu ON wu.user_id=rum.user_id
                WHERE wf.workflow_id=$workflowId AND wf.is_initiator=true";
        return $query;
    }

    /**
     * | get workflow role Id by logged in User Id
     * -------------------------------------------
     * @param userId > current Logged in User
     */
    public function getRoleIdByUserId($userId)
    {
        $roles = WfRoleusermap::select('id', 'wf_role_id', 'user_id')
            ->where('user_id', $userId)
            ->where('is_suspended', false)
            ->get();
        return $roles;
    }

    /**
     * | get Ward By Logged in User Id
     * -------------------------------------------
     * | @param userId > Current Logged In User Id
     */
    // public function getWardByUserId($userId)
    // {
    //     $occupiedWard = WfWardUser::select('id', 'ward_id')
    //         ->where('user_id', $userId)
    //         ->get();
    //     return $occupiedWard;
    // }

    //logged in user role 
    public function getRole($request)
    {
        $userId = authUser($request)->id;
        // DB::enableQueryLog();
        $role = WfRoleusermap::select(
            'wf_workflowrolemaps.*',
            'wf_roleusermaps.user_id'
        )
            ->join('wf_workflowrolemaps', 'wf_workflowrolemaps.wf_role_id', 'wf_roleusermaps.wf_role_id')
            ->where('user_id', $userId)
            ->where('wf_workflowrolemaps.workflow_id', $request->workflowId)
            ->first();
        // dd(DB::getQueryLog());

        return remove_null($role);
    }

    public function getRoleByUserUlbId($ulbId, $userId)
    {
        // try {
        $role = WfRole::select('wf_roles.*')
            ->where('ulb_ward_masters.ulb_id', $ulbId)
            ->where('wf_roleusermaps.user_id', $userId)
            ->join('wf_roleusermaps', 'wf_roleusermaps.wf_role_id', 'wf_roles.id')
            ->join('wf_ward_users', 'wf_ward_users.user_id', 'wf_roleusermaps.user_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'wf_ward_users.ward_id')
            ->first();
        if ($role) {
            return ($role);
        }
    }

    /**
     * | Get Initiator Id While Sending to level Pending For the First Time
     * | @param mixed $wfWorkflowId > Workflow Id of Modules
     * | @var string $query
     */
    public function getInitiatorId(int $wfWorkflowId)
    {
        $query = "SELECT 
                    r.id AS role_id,
                    r.role_name AS role_name,
                    w.forward_role_id
                    FROM wf_roles r
                    INNER JOIN (SELECT * FROM wf_workflowrolemaps WHERE workflow_id=$wfWorkflowId) w ON w.wf_role_id=r.id
                    WHERE w.is_initiator=TRUE 
                    ";
        return $query;
    }


    /**
     * | Get Finisher Id while approve or reject application
     * | @param wfWorkflowId ulb workflow id 
     */
    public function getFinisherId(int $wfWorkflowId)
    {
        $query = "SELECT 
                    r.id AS role_id,
                    r.role_name AS role_name 
                    FROM wf_roles r
                    INNER JOIN (SELECT * FROM wf_workflowrolemaps WHERE workflow_id=$wfWorkflowId) w ON w.wf_role_id=r.id
                    WHERE w.is_finisher=TRUE ";
        return $query;
    }

    /**
     * | Workflow Track Trait
     * | @param workflowTrack new model object
     * | @param req requested parameters to be saved in workflow track
     */
    public function workflowTrack($workflowTrack, $req)
    {
        $workflowTrack->workflow_id = $req['workflowId'];
        $workflowTrack->citizen_id = $req['citizenId'];
        $workflowTrack->ref_table_dot_id = $req['refTableId'];
        $workflowTrack->ref_table_id_value = $req['applicationId'];
        $workflowTrack->message = $req['message'];
        $workflowTrack->commented_by = $req['citizenId'];
        $workflowTrack->track_date = Carbon::now()->format('Y-m-d H:i:s');
        $workflowTrack->forwarded_to = $req['forwardedTo'] ?? null;
    }

    /**
     * | Get workflowId using ulbId and workflow master Id
     * | @param request
     */
    public function getWorkflowByUlb($request)
    {
        WfWorkflow::where('ulb_id', $request->ulbId)
            ->where('wf_master_id', $request->wfMasterId)
            ->where('is_suspended', false)
            ->first();
    }
    public function getUserRollV2($user_id, $ulb_id, $workflow_id)
    {
        try {
            DB::enableQueryLog();
            $data = WfRole::select(
                DB::raw(
                    "wf_roles.id as role_id,wf_roles.role_name,
                    wf_workflowrolemaps.is_initiator, wf_workflowrolemaps.is_finisher,
                    wf_workflowrolemaps.forward_role_id,forword.role_name as forword_name,
                    wf_workflowrolemaps.backward_role_id,backword.role_name as backword_name,
                    wf_workflowrolemaps.allow_full_list,wf_workflowrolemaps.can_escalate,
                    wf_workflowrolemaps.serial_no,wf_workflowrolemaps.is_btc,
                    wf_workflowrolemaps.can_upload_document,
                    wf_workflowrolemaps.can_verify_document,
                    wf_workflowrolemaps.can_backward,
                    wf_workflowrolemaps.can_edit,
                    wf_workflows.id as workflow_id,wf_masters.workflow_name,
                    ulb_masters.id as ulb_id, ulb_masters.ulb_name,
                    ulb_masters.ulb_type"
                )
            )
                ->join("wf_roleusermaps", function ($join) {
                    $join->on("wf_roleusermaps.wf_role_id", "=", "wf_roles.id")
                        ->where("wf_roleusermaps.is_suspended", "=", FALSE);
                })
                ->join("users", "users.id", "=", "wf_roleusermaps.user_id")
                ->join("wf_workflowrolemaps", function ($join) {
                    $join->on("wf_workflowrolemaps.wf_role_id", "=", "wf_roleusermaps.wf_role_id")
                        ->where("wf_workflowrolemaps.is_suspended", "=", FALSE);
                })
                ->leftjoin("wf_roles AS forword", "forword.id", "=", "wf_workflowrolemaps.forward_role_id")
                ->leftjoin("wf_roles AS backword", "backword.id", "=", "wf_workflowrolemaps.backward_role_id")
                ->join("wf_workflows", function ($join) {
                    $join->on("wf_workflows.id", "=", "wf_workflowrolemaps.workflow_id")
                        ->where("wf_workflows.is_suspended", "=", FALSE);
                })
                ->join("wf_masters", function ($join) {
                    $join->on("wf_masters.id", "=", "wf_workflows.wf_master_id")
                        ->where("wf_masters.is_suspended", "=", FALSE);
                })
                ->join("ulb_masters", "ulb_masters.id", "=", "wf_workflows.ulb_id")
                ->where("wf_roles.is_suspended", false)
                ->where("wf_roleusermaps.user_id", $user_id)
                ->where("wf_workflows.ulb_id", $ulb_id)
                ->where("wf_workflows.wf_master_id", $workflow_id)
                ->orderBy("wf_roleusermaps.id", "desc")
                ->first();
            // dd(DB::getQueryLog());
            return $data;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
