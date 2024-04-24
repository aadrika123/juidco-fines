<?php

namespace App\Models;

use App\IdGenerator\IdGeneration;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PenaltyRecord extends Model
{
    use HasFactory;

    protected $table = "penalty_applied_records";
    protected $guarded = [];

    /*Add Records*/
    public function store($req)
    {
        $data = PenaltyRecord::create($req);  // Store Record into database
        return $data;
    }

    /**
     * |
     */
    public function view($id)
    {
        return PenaltyRecord::find($id);
    }

    /**
     * | Read Record Details
     */
    public function recordDetail()
    {
        $docUrl = Config::get('constants.DOC_URL');
        return PenaltyRecord::select(
            'penalty_applied_records.*',
            'violations.violation_name',
            'violations.section_id',
            'violations.department_id',
            'violations.violation_name as section_definition',
            'sections.violation_section',
            'departments.department_name as department',
            'ulb_ward_masters.ward_name',
            DB::raw(
                "CASE 
                        WHEN penalty_applied_records.status = '1' THEN 'Active'
                        WHEN penalty_applied_records.status = '0' THEN 'Deactivated'  
            WHEN penalty_applied_records.status = '2' THEN 'Approved'  
                    END as status,
                    TO_CHAR(penalty_applied_records.created_at::date,'dd-mm-yyyy') as date,
                    TO_CHAR(penalty_applied_records.created_at,'HH12:MI:SS AM') as time",
            )
        )
            ->join('violations', 'violations.id', 'penalty_applied_records.violation_id')
            ->join('sections', 'sections.id',  'violations.section_id')
            ->join('departments', 'departments.id', 'violations.department_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'penalty_applied_records.ward_id')
            ->orderByDesc('penalty_applied_records.id');
    }

    /**
     * | Get Records by Application No
     */
    public function searchByAppNo($req)
    {
        return DB::table('penalty_applied_records as a')

            // ->where("penalty_applied_records.section_name", "Ilike", DB::raw("'%" . $req->search . "%'"))
            // ->orWhere("b.class_name", "Ilike", DB::raw("'%" . $req->search . "%'"))
            ->select(
                DB::raw("penalty_applied_records.*,b.violation_name,c.violation_section,
        CASE WHEN penalty_applied_records.status = '0' THEN 'Deactivated'  
        WHEN penalty_applied_records.status = '1' THEN 'Active'
        END as status,
        TO_CHAR(penalty_applied_records.created_at::date,'dd-mm-yyyy') as date,
        TO_CHAR(penalty_applied_records.created_at,'HH12:MI:SS AM') as time
        ")
            )
            ->join('violations as b', 'b.id', '=', 'penalty_applied_records.violation_id')
            ->join('violation_under_sections as c', 'c.id', '=', 'penalty_applied_records.violation_section_id')
            ->where("penalty_applied_records.application_no", "Ilike",  DB::raw("'%" . $req->applicationNo . "%'"));
    }
}
