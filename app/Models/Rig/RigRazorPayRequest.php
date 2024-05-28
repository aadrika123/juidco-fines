<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigRazorPayRequest extends Model
{
    use HasFactory;
    protected $guarded = [];


    /*
    * | Create 
    */
    public function store($req)
    {
        return RigRazorPayRequest::create($req);
    }

    /**
     * |
     */
    public function getPaymentRecord($req)
    {
        return RigRazorPayRequest::where('order_id', $req->orderId)
            ->where('related_id', $req->id)
            ->where('payment_status', 0)
            ->first();
    }
}
