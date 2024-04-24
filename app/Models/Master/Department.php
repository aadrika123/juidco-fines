<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Department extends Model
{
    use HasFactory;

    protected $guarded = [];

    /*Add Records*/
    public function store(array $req)
    {
        return Department::create($req);
    }

    /**
     * | Read Records by Name
     */
    public function checkExisting($req)
    {
        return Department::where('department_name', strtoupper($req->department))
            ->where('status', 1)
            ->first();
    }


    /**
     * | Read Active Department
     */
    public function recordDetails()
    {
        return Department::select(
            DB::raw("id,department_name,
                TO_CHAR(created_at::date,'dd-mm-yyyy') as date,
                TO_CHAR(created_at,'HH12:MI:SS AM') as time")
        )
            ->where('status', 1)
            ->orderByDesc('id');
    }
}
