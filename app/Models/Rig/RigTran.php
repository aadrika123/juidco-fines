<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigTran extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        return RigTran::create($req);
    }
    /**
     * | Get transaction by application No
     */
    public function getTranByApplicationId($applicationId)
    {
        return RigTran::where('related_id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get transaction details accoring to related Id and transaction type
     */
    public function getTranDetails($relatedId)
    {
        return RigTran::where('related_id', $relatedId)
            // ->where('tran_type_id', $tranType)
            ->where('status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get transaction details according to transaction no
     */
    public function getTranDetailsByTranNo($tranNo)
    {
        return RigTran::select(
            'rig_trans.id AS refTransId',
            'rig_trans.*',
        )
            ->where('rig_trans.tran_no', $tranNo)
            ->where('rig_trans.status', 1)
            ->orderByDesc('rig_trans.id');
    }
}
