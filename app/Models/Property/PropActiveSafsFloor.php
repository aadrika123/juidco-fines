<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropActiveSafsFloor extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_property';

    /**
     * | Get Safs Floors By Saf Id
     */
    public function getSafFloors($safId)
    {
        return PropActiveSafsFloor::where('saf_id', $safId)
            ->where('status', 1);
    }


    /**
     * | Get occupancy type according to Saf id
     */
    public function getOccupancyType($safId, $refTenanted)
    {
        $occupency = PropActiveSafsFloor::where('saf_id', $safId)
            ->where('occupancy_type_mstr_id', $refTenanted)
            ->get();
        $check = collect($occupency)->first();
        if ($check) {
            $metaData = [
                'tenanted' => true
            ];
            return $metaData;
        }
        return  $metaData = [
            'tenanted' => false
        ];
        return $metaData;
    }
}
