<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RazorpayResponse extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Create 
     */
    public function store($req)
    {
        return RazorpayResponse::create($req);
    }

    /**
     * | 
     */
    public function getTranNo($req)
    {
        return RazorpayResponse::select(
            'razorpay_responses.id',
            'penalty_transactions.id as tran_id',
            'penalty_transactions.tran_no',
            'penalty_transactions.application_id',
            'penalty_transactions.challan_id',
            'penalty_transactions.payment_mode',
            'penalty_transactions.total_amount',
        )
            ->where('order_id', $req->orderId)
            ->join('penalty_transactions', 'penalty_transactions.challan_id', 'razorpay_responses.challan_id')
            ->where('razorpay_responses.status', 1)
            ->where('penalty_transactions.status', 1)
            ->orderByDesc('razorpay_responses.id')
            ->first();
    }
}
