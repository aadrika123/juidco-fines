<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PenaltyChallan extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at'];


    public function store($req)
    {
        return PenaltyChallan::create($req);
    }

    /**
     * | 
     */
    public function view($id)
    {
        return PenaltyChallan::find($id);
    }

    /**
     * | Challan Details
     */
    public function details()
    {
        return PenaltyChallan::select(
            'penalty_challans.id',
            'penalty_challans.challan_date',
            'penalty_challans.challan_no',
            'penalty_challans.amount',
            'penalty_challans.penalty_amount',
            'penalty_challans.total_amount',
            'penalty_final_records.id as application_id',
            'penalty_final_records.full_name',
            'penalty_final_records.mobile',
            'penalty_final_records.application_no',
            'penalty_final_records.holding_no',
            'penalty_final_records.payment_status',
            'tran_no as transaction_no',
            'penalty_transactions.tran_no',
            'violation_name',
            'sections.violation_section',
            DB::raw(
                "CASE 
                        WHEN penalty_challans.challan_date > CURRENT_DATE + INTERVAL '14 days' THEN true
                        else false
                END as has_expired"
            )
        )
            ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_challans.penalty_record_id')
            ->join('violations', 'violations.id', 'penalty_final_records.violation_id')
            ->join('sections', 'sections.id', 'violations.section_id')
            ->leftJoin('penalty_transactions', function ($join) {
                $join->on('penalty_transactions.challan_id', '=', 'penalty_challans.id')
                    ->where('penalty_transactions.status', 1);
            })
            ->where('penalty_challans.status', 1)
            ->orderbyDesc('penalty_challans.id');
    }

    /**
     * | Recent Details
     */
    public function recentChallanDetails()
    {
        return PenaltyChallan::select(
            'penalty_challans.*',
            'penalty_final_records.id as application_id',
            'full_name',
            'payment_status',
            'tran_no',
            DB::raw(
                "TO_CHAR(penalty_challans.challan_date,'DD-MM-YYYY') as challan_date,
                TO_CHAR(penalty_challans.payment_date,'DD-MM-YYYY') as payment_date",
            )
        )
            ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_challans.penalty_record_id')
            ->leftjoin('penalty_transactions', 'penalty_transactions.challan_id', 'penalty_challans.id')
            ->orderbyDesc('penalty_challans.id');
    }
}