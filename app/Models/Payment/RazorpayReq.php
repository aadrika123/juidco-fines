<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RazorpayReq extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Create 
     */
    public function store($req)
    {
        return RazorpayReq::create($req);
    }

    /**
     * |
     */
    public function getPaymentRecord($req)
    {
        return RazorpayReq::where('order_id', $req->orderId)
            ->where('application_id', $req->applicationId)
            ->where('payment_status', 0)
            ->first();
    }
}
