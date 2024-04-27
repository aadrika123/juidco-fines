<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PenaltyTransaction extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $hidden = ['created_at', 'updated_at'];

    public function store($req)
    {
        return PenaltyTransaction::create($req);
    }

    /**
     * | Transaction Details
     */
    public function tranDtl()
    {
        $docUrl = Config::get('constants.DOC_URL');
        return PenaltyTransaction::select(
            'penalty_transactions.id',
            'tran_no',
            'tran_date',
            'payment_mode',
            'penalty_transactions.amount',
            'penalty_transactions.penalty_amount',
            'penalty_transactions.total_amount',
            'application_no',
            'full_name',
            'challan_no',
            'challan_date',
            'violations.violation_name',
            'departments.department_name as department',
            'penalty_final_records.ulb_id',
            DB::raw(
                "CASE 
                    WHEN signature IS NULL THEN ''
                        else
                    concat('$docUrl/',signature)
                END as signature",
            )

        )
            ->join('penalty_final_records', 'penalty_final_records.id', 'penalty_transactions.application_id')
            ->join('penalty_challans', 'penalty_challans.id', 'penalty_transactions.challan_id')
            ->leftjoin('users', 'users.id', 'penalty_transactions.tran_by')
            ->join('violations', 'violations.id', 'penalty_final_records.violation_id')
            ->join('departments', 'departments.id', 'violations.department_id');
    }

    /**
     * | Details for Cash Verification
     */
    public function cashDtl($date)
    {
        return PenaltyTransaction::select('penalty_transactions.*', 'users.user_name', 'users.id as user_id', 'mobile')
            ->join('users', 'users.id', 'penalty_transactions.tran_by')
            ->where('penalty_transactions.status', 1)
            ->where('verify_status', 2)
            ->where('tran_date', $date);
    }
}
