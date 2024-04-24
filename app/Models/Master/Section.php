<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Section extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * | Add Records
     */
    public function store(array $req)
    {
        return Section::create($req);
    }

    /**
     * | Find by Id
     */
    public function sectionById($id)
    {
        return Section::find($id);
    }

    /*Read Records by name*/
    public function checkExisting($req)
    {
        return Section::where('violation_section', strtoupper($req->violationSection))
            ->where('department_id', $req->departmentId)
            ->where('status', 1)
            ->first();
    }

    /*Read all Records by*/
    public function getList($req)
    {
        return Section::select(
            DB::raw("id,violation_section,department_id,
         CASE 
             WHEN status = '0' THEN 'Deactivated'  
             WHEN status = '1' THEN 'Active'
         END as status,
         TO_CHAR(created_at::date,'dd-mm-yyyy') as date,
         TO_CHAR(created_at,'HH12:MI:SS AM') as time
         ")
        )
            ->where('department_id', $req->departmentId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->get();
    }

    /*Read all Records by*/
    public function recordDetails($req)
    {
        return Section::select(
            DB::raw("sections.id,sections.violation_section,sections.department_id, departments.department_name,
        CASE 
            WHEN sections.status = '0' THEN 'Deactivated'  
            WHEN sections.status = '1' THEN 'Active'
        END as status,
        TO_CHAR(sections.created_at::date,'dd-mm-yyyy') as date,
        TO_CHAR(sections.created_at,'HH12:MI:SS AM') as time
        ")
        )
            ->join('departments', 'departments.id',  '=', 'sections.department_id')
            ->where('sections.status', 1)
            ->orderByDesc('sections.id');
        // ->get();
    }
}
