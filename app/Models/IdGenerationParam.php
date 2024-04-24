<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdGenerationParam extends Model
{
    use HasFactory;

    /**
     * | Find IdGeneration by id
     */
    public function getParams($id)
    {
        return IdGenerationParam::find($id);
    }
}
