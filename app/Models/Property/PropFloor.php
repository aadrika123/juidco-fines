<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropFloor extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_property';

    /**
     * | Get Property Floors using property Id
     */
    public function getPropFloors($propertyId)
    {
        return PropFloor::select(
            'prop_floors.*',
        )
            ->where('property_id', $propertyId)
            ->where('prop_floors.status', 1);
    }


    /**
     * | Get occupancy type according to holding id
     */
    public function getOccupancyType($propertyId, $refTenanted)
    {
        $occupency = PropFloor::where('property_id', $propertyId)
            ->where('occupancy_type_mstr_id', $refTenanted)
            ->get();
        $check = collect($occupency)->first();
        if ($check) {
            $metaData = [
                'tenanted' => true,
            ];
            return $metaData;
        }
        return  $metaData = [
            'tenanted' => false
        ];

        return $metaData;
    }
}
