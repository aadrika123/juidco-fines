<?php

namespace App\Traits\Fines;

use Illuminate\Database\Eloquent\Collection;

trait FinesTrait
{
    /**
     * | Get Basic Details
     */
    public function generatePenaltyDetails($data)
    {
        return new Collection([
            ['displayString' => 'Violation Name', 'key' => 'violation_name', 'value' => $data->violation_name],
            ['displayString' => 'Violation Section', 'key' => 'violation_section', 'value' => $data->violation_section],
            ['displayString' => 'Penalty Amount', 'key' => 'penalty_amount', 'value' => '₹ ' . $data->amount]
        ]);
    }

    /**
     * | Generating Bride Details
     */
    public function generateBasicDetails($data)
    {
        return new Collection([
            ['displayString' => 'Name', 'key' => 'name', 'value' => $data->full_name,],
            ['displayString' => 'Mobile', 'key' => 'mobile', 'value' => $data->mobile,],
            ['displayString' => 'Email', 'key' => 'email', 'value' => $data->email,],
            ['displayString' => 'Holding No.', 'key' => 'holding_no', 'value' => $data->holding_no,]
        ]);
    }

    /**
     * | Generating Groom Details
     */
    public function generateAddressDetails($data)
    {
        return new Collection([
            ['displayString' => 'Address', 'key' => 'street_address', 'value' => $data->street_address,],
            ['displayString' => 'City', 'key' => 'city', 'value' => $data->city,],
            ['displayString' => 'Region', 'key' => 'region', 'value' => $data->region,],
            ['displayString' => 'Postal Code', 'key' => 'postal_code', 'value' => $data->postal_code,],
        ]);
    }

    /**
     * | Witness Details
     */
    public function generateWitnessDetails($data)
    {
        return new Collection([
            ['displayString' => 'Witness Name', 'key' => 'witness_name', 'value' => $data->witness_name,],
            ['displayString' => 'Witness Mobile', 'key' => 'witness_mobile', 'value' => $data->witness_mobile,]
        ]);
    }

    /**
     * | Generate Card Details
     */
    public function generateCardDtls($data)
    {

        $violationDtls = new Collection([
            ['displayString' => 'Name', 'key' => 'name', 'value' => $data->full_name,],
            ['displayString' => 'Mobile', 'key' => 'mobile', 'value' => $data->mobile,],
            ['displayString' => 'Violation Section', 'key' => 'violation_section', 'value' => $data->violation_section],
            ['displayString' => 'Penalty Amount', 'key' => 'penalty_amount', 'value' => '₹ ' . $data->amount]
        ]);

        $cardElement = [
            'headerTitle' => "Violation Details",
            'data' => $violationDtls
        ];
        return $cardElement;
    }

    /**
     * | Comparison Report
     */
    public function comparison($final, $applied)
    {
        return new Collection([
            ['displayString' => 'Date', 'final' => ($final->created_at)->format('d-m-Y'), 'applied' => ($applied->created_at)->format('d-m-Y'),],
            ['displayString' => 'Name of Violator',  'final' => $final->full_name,       'applied' => $applied->full_name,],
            ['displayString' => 'Mobile No',         'final' => $final->mobile,          'applied' => $applied->mobile,],
            ['displayString' => 'Email',             'final' => $final->email,           'applied' => $applied->email,],
            ['displayString' => 'Guardian Name',     'final' => $final->guardian_name,   'applied' => $applied->guardian_name,],
            ['displayString' => 'Address',           'final' => $final->street_address,  'applied' => $applied->street_address,],
            ['displayString' => 'Violation Made',    'final' => $final->violation_name,  'applied' => $applied->violation_name,],
            ['displayString' => 'Violation Section', 'final' => $final->violation_section,  'applied' => $applied->violation_section,],
            ['displayString' => 'Violation Place',   'final' => $final->violation_place,    'applied' => $applied->violation_place,],
            ['displayString' => 'Penalty Amount',    'final' => '₹' . $final->total_amount, 'applied' => '₹' . $applied->amount,],
            ['displayString' => 'Applier/Approver',  'final' => $final->user_name,          'applied' => $applied->user_name,],
            // ['displayString' => 'Name of Violator', 'final' => $final->full_name, 'applied' => $applied->full_name,],
            // ['displayString' => 'Mobile No', $final->mobile, $applied->mobile]
        ]);
    }
}
