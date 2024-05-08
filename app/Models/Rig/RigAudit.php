<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RigAudit extends Model
{
    use HasFactory;

    /**
     * | Save Audit data  
     */
    public function saveAuditData($req, $tableName)
    {
        $mPetAudit = new RigAudit();
        $mPetAudit->json_data = $req;
        $mPetAudit->table_name = $tableName;
        $mPetAudit->save();
    }
}
