<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigLicenseDuration extends Model
{

    protected $table = 'rig_license_durations';

    protected $fillable = [
        'ulb_id',
        'license_duration_years',
        'status'
    ];
    public $timestamps = true;
}
