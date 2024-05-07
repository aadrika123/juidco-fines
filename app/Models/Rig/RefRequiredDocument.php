<?php

namespace App\Models\Rig;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefRequiredDocument extends Model
{
    use HasFactory;

    /**
     * | Get Total no of document for upload
     */
    public function totalNoOfDocs($moduleId)
    {
        $noOfDocs = RefRequiredDocument::select('requirements')
            // ->where('code', $docCode)
            ->where('module_id',$moduleId)
            ->first();
        $totalNoOfDocs = explode("#", $noOfDocs);
        return count($totalNoOfDocs);
    }
}
