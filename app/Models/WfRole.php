<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WfRole extends Model
{
    use HasFactory;

    protected $guarded = [];

    /*Add Records*/
    public function store(array $req)
    {
        return WfRole::create($req);
    }

    /*Read Records by name*/
    public function checkExisting($req)
    {
        return WfRole::where(DB::raw('upper(role_name)'), strtoupper($req->roleName))
            ->where('is_suspended', false)
            ->get();
    }

    /*Read all Records by*/
    public function getList()
    {
        return WfRole::select(
            DB::raw("id,role_name,
        CASE 
            WHEN status = '0' THEN 'Deactivated'  
            WHEN status = '1' THEN 'Active'
        END as status,
        TO_CHAR(created_at::date,'dd-mm-yyyy') as date,
        TO_CHAR(created_at,'HH12:MI:SS AM') as time
        ")
        )
            ->where('status', 1)
            ->orderByDesc('id')
            ->get();
    }

    /*Read all Records by*/
    public function recordDetails()
    {
        return WfRole::select(
            DB::raw("id,role_name,user_type,
        TO_CHAR(created_at::date,'dd-mm-yyyy') as date,
        TO_CHAR(created_at,'HH12:MI:SS AM') as time
        ")
        )
            ->where('is_suspended', false)
            ->orderByDesc('id');
    }
}
