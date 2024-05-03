<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropActiveSaf extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_property';

    /**
     * | Get Saf Details by Saf No
     * | @param SafNo
     */
    public function getSafDtlBySaf()
    {
        return PropActiveSaf::select(
                'prop_active_safs.*',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no',
            )
            ->join('ulb_ward_masters as u', 'prop_active_safs.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 'prop_active_safs.new_ward_mstr_id', '=', 'u1.id')
            ->where('prop_active_safs.status', 1);
    }


    /**
     * | Get Saf Details by Saf No
     * | @param SafNo
     */
    public function getSafDtlBySafUlbNo($safNo, $ulbId)
    {
        return PropActiveSaf::where('prop_active_safs.saf_no', $safNo)
            ->where('prop_active_safs.ulb_id', $ulbId)
            ->select(
                'prop_active_safs.id',
                'prop_active_safs.saf_no',
                'prop_active_safs.ward_mstr_id',
                'prop_active_safs.new_ward_mstr_id',
                'prop_active_safs.elect_consumer_no',
                'prop_active_safs.elect_acc_no',
                'prop_active_safs.elect_bind_book_no',
                'prop_active_safs.elect_cons_category',
                'prop_active_safs.prop_address',
                'prop_active_safs.corr_address',
                'prop_active_safs.prop_pin_code',
                'prop_active_safs.corr_pin_code',
                'prop_active_safs.area_of_plot as total_area_in_desimal',
                'prop_active_safs.apartment_details_id',
                'prop_active_safs.prop_type_mstr_id',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no',
            )
            ->join('ulb_ward_masters as u', 'prop_active_safs.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 'prop_active_safs.new_ward_mstr_id', '=', 'u1.id')
            ->where('prop_active_safs.status', 1)
            ->first();
    }

    /**
     * | Get citizen safs
     */
    public function getCitizenSafs($citizenId, $ulbId)
    {
        return PropActiveSaf::select('id', 'saf_no', 'citizen_id')
            ->where('citizen_id', $citizenId)
            ->where('ulb_id', $ulbId)
            ->orderByDesc('id')
            ->get();
    }
}
