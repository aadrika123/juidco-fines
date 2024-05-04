<?php

namespace App\Models\Rig;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempTransaction extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = 'pgsql_master';

    /**
     * | Save temp details 
     */
    public function tempTransaction($req)
    {
        $mTempTransaction = new TempTransaction();
        $mTempTransaction->create($req);
    }
}
