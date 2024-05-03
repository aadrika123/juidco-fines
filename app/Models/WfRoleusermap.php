<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WfRoleusermap extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = "pgsql_master";
    /**
     * |
     */
    public function store($req)
    {
        return WfRoleusermap::create($req);
    }

    /**
     * |
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
     * | Get Role details by User Id
     */
    public function getRoleDetailsByUserId($userId)
    {
        return WfRoleusermap::select(
            'wf_roles.role_name AS role',
            'wf_roles.id AS roleId'
        )
            ->join('wf_roles', 'wf_roles.id', '=', 'wf_roleusermaps.wf_role_id')
            ->where('wf_roleusermaps.user_id', $userId)
            ->where('wf_roleusermaps.is_suspended', false)
            ->orderByDesc('wf_roles.id')
            ->first();
    }

    /**
     * | Get role by User and Workflow Id
     */
    public function getRoleByUserWfId($req)
    {
        return DB::table('wf_roleusermaps as r')
            ->select('r.wf_role_id')
            ->join('wf_workflowrolemaps as w', 'w.wf_role_id', '=', 'r.wf_role_id')
            ->where('r.user_id', $req->userId)
            ->where('w.workflow_id', $req->workflowId)
            ->where('w.is_suspended', false)
            ->first();
    }

    /**
     * | 
     */
    public function getUserId($req)
    {
        return  WfRoleusermap::select('user_id', 'wf_role_id')
            ->join('users', 'users.id', 'wf_roleusermaps.user_id')
            ->where('wf_role_id', $req['roleId'])
            ->where('ulb_id', $req['ulbId'])
            ->first();
    }


    /**
     * | Get role by User and Workflow Id
     */
    public function getRoleByUserWfAndId($req)
    {
        return DB::table('wf_roleusermaps as r')
            ->select(
                'r.wf_role_id',
                'w.forward_role_id',
                'w.backward_role_id'
            )
            ->join('wf_workflowrolemaps as w', 'w.wf_role_id', '=', 'r.wf_role_id')
            ->where('r.user_id', $req->userId)
            ->where('w.workflow_id', $req->workflowId)
            ->where('w.is_suspended', false)
            ->first();
    }
}
