<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PenaltyFinalRecord extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($reqs)
    {
        return PenaltyFinalRecord::create($reqs);
    }

    /**
     * |
     */
    public function view($id)
    {
        return PenaltyFinalRecord::find($id);
    }

    /**
     * |
     */
    public function recordDetail()
    {
        return PenaltyFinalRecord::select(
            'penalty_final_records.*',
            'violations.violation_name',
            'violations.section_id',
            'violations.violation_name as section_definition',
            'sections.violation_section',
            'departments.department_name as department',
            DB::raw(
                "CASE 
                        WHEN penalty_final_records.status = '1' THEN 'Active'
                        WHEN penalty_final_records.status = '0' THEN 'Deactivated'  
                        WHEN penalty_final_records.status = '2' THEN 'Approved'  
                    END as status,
                    TO_CHAR(penalty_final_records.created_at::date,'dd-mm-yyyy') as date,
                    TO_CHAR(penalty_final_records.created_at,'HH12:MI:SS AM') as time",
            )
        )
            ->join('violations', 'violations.id', '=', 'penalty_final_records.violation_id')
            ->join('sections', 'sections.id', '=', 'violations.section_id')
            ->join('departments', 'departments.id', 'violations.department_id')
            ->orderByDesc('penalty_final_records.id');
    }
}
