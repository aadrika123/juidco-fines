<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UlbWardMaster extends Model
{
    use HasFactory;
    protected $hidden = ['created_at', 'updated_at'];

    /**
     * |
     */
    public function getWardList($ulbId)
    {
        return UlbWardMaster::select('id', 'ward_name', 'ulb_id')
            ->where('ulb_id', $ulbId)
            ->where('status', 1)
            ->get();
    }
}
