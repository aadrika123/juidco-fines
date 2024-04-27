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
}
