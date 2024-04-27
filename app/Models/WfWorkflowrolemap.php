<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
