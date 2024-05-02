<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigRazorPayResponse extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Create 
     */
    public function store($req)
    {
        return RigRazorPayResponse::create($req);
    }

    /**
     * | 
     */
    public function getTranNo($req)
    {
        return RigRazorPayResponse::select(
            'rig_razorpay_responses.id',
            'rig_trans.id as tran_id',
            'rig_trans.tran_no',
            'rig_trans.application_id',
            'rig_trans.challan_id',
            'rig_trans.payment_mode',
            'rig_trans.total_amount',
        )
            ->where('order_id', $req->orderId)
            ->join('rig_trans', 'rig_trans.related_id', 'rig_razorpay_responses.related_id')
            ->where('rig_razorpay_responses.status', 1)
            ->where('rig_trans.status', 1)
            ->orderByDesc('rig_razorpay_responses.id')
            ->first();
    }
}
