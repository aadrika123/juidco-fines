<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WfWorkflowrolemap extends Model
{
    use HasFactory;
    protected $connection = "pgsql_master";

    /**
     * | Get Ulb Workflows By Role Ids
     */
    public function getWfByRoleId($roleIds)
    {
        return WfWorkflowrolemap::select('workflow_id')
            ->whereIn('wf_role_id', $roleIds)
            ->get();
    }

    /**
     * | 
     */
    public function getRoleDetails($request)
    {
        return DB::connection('pgsql_master')
            ->table('wf_workflowrolemaps')
            ->select(
                'wf_workflowrolemaps.id',
                'wf_workflowrolemaps.workflow_id',
                'wf_workflowrolemaps.wf_role_id',
                'wf_workflowrolemaps.forward_role_id',
                'wf_workflowrolemaps.backward_role_id',
                'wf_workflowrolemaps.is_initiator',
                'wf_workflowrolemaps.is_finisher',
                'r.role_name as forward_role_name',
                'rr.role_name as backward_role_name'
            )
            ->leftJoin('wf_roles as r', 'wf_workflowrolemaps.forward_role_id', '=', 'r.id')
            ->leftJoin('wf_roles as rr', 'wf_workflowrolemaps.backward_role_id', '=', 'rr.id')
            ->where('workflow_id', $request->workflowId)
            ->where('wf_role_id', $request->wfRoleId)
            ->first();
    }
}
