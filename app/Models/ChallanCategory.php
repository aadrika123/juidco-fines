<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChallanCategory extends Model
{
    use HasFactory;

    /*Read all Records by*/
    public function getList()
    {
        return ChallanCategory::select(
            DB::raw("id,category_type,
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
}
