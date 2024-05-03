<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Violation extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * | Add Records
     */
    public function store(array $req)
    {
        return Violation::create($req);
    }

    /**
     * | Find
     */
    public function violationById($id)
    {
        return Violation::find($id);
    }

    /**
     * Checks the reord is already exists or not
     */
    public function checkExisting($req)
    {
        return Violation::where('violation_name', $req->violationName)
            ->where('department_id', $req->departmentId)
            ->where('section_id', $req->sectionId)
            ->where('status', 1)
            ->get();
    }

    /**
     * Get All Records
     */
    public function recordDetails()
    {
        return Violation::select(
            DB::raw("violations.id,violations.violation_name,violations.penalty_amount, violations.on_spot,
            sections.violation_section, departments.department_name,users.name as created_by,
        CASE 
            WHEN violations.status = '0' THEN 'Deactivated'  
            WHEN violations.status = '1' THEN 'Active'
        END as status,
        TO_CHAR(violations.created_at::date,'dd-mm-yyyy') as date,
        TO_CHAR(violations.created_at,'HH12:MI:SS AM') as time
        ")
        )
            ->join('sections', 'sections.id', '=', 'violations.section_id')
            ->join('departments', 'departments.id', '=', 'violations.department_id')
            ->join('users', 'users.id', '=', 'violations.user_id')
            ->where('violations.status', 1)
            ->orderByDesc('violations.id');
        // ->get();
    }

    /**
     * Get List By Ids
     */
    public function getList($req)
    {
        return Violation::select(
            DB::raw("violations.id,violation_name,penalty_amount,section_id,violation_section,on_spot,violations.department_id,
        CASE 
            WHEN violations.status = '0' THEN 'Deactivated'  
            WHEN violations.status = '1' THEN 'Active'
        END as status,
        TO_CHAR(violations.created_at::date,'dd-mm-yyyy') as date,
        TO_CHAR(violations.created_at,'HH12:MI:SS AM') as time
        ")
        )
            ->join('sections', 'sections.id', 'violations.section_id')
            ->where('violations.department_id', $req->departmentId)
            ->where('violations.status', 1)
            ->orderByDesc('id')
            ->get();
    }
}
