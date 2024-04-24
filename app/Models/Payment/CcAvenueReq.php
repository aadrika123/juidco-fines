<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CcAvenueReq extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Create 
     */
    public function store($req)
    {
        return CcAvenueReq::create($req);
    }

    /**
     * |
     */
    public function getPaymentRecord($req)
    {
        return CcAvenueReq::where('order_id', $req->orderId)
            ->where('application_id', $req->applicationId)
            ->where('payment_status', 0)
            ->first();
    }
}
