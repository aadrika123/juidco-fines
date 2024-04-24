<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CcAvenueResponse extends Model
{
    use HasFactory;

    /**
     * | Create 
     */
    public function store($req)
    {
        return CcAvenueReq::create($req);
    }
}
