<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WfWorkflow extends Model
{
    use HasFactory;
    protected $connection = "pgsql_master";

    public function getWorklow($ulbId, $wfMasterId)
    {
        return WfWorkflow::where('wf_master_id', $wfMasterId)
            ->where('ulb_id', $ulbId)
            ->first();
    }

    public function getulbWorkflowId($wfMstId, $ulbId)
    {
        return WfWorkflow::where('wf_master_id', $wfMstId)
            ->where('ulb_id', $ulbId)
            ->where('is_suspended', false)
            ->first();
    }
}
