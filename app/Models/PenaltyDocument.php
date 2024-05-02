<?php

namespace App\Models;

use App\DocUpload;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PenaltyDocument extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function storeDocument($req, $id, $applicationNo)
    {
        $data = [];
        $docUpload = new DocUpload;

        $documentTypes = [
            'photo'      => 'Violation Image',
            'photo2'     => 'Violation Image',
            'photo3'     => 'Violation Image',
            'audioVideo' => 'Violation Video',
            'pdf'        => 'Violation Document',
        ];

        foreach ($documentTypes as $inputName => $documentName) {
            if ($req->file($inputName)) {
                $file = $req->file($inputName);
                $req->merge([
                    'document' => $file
                ]);

                #_Doc Upload through a Class
                // $imageName = $docUpload->uploadOld($refImageName, $file, 'FinePenalty/');

                #_Doc Upload through DMS
                $docStatus = $docUpload->upload($req);
                if ($docStatus['status'] != true)
                    throw new Exception("Doc Upload Failed");
                
                $docMetadata = new PenaltyDocument([
                    'applied_record_id' => $id,
                    'unique_id' => $docStatus['data']['uniqueId'],
                    'reference_no' => $docStatus['data']['ReferenceNo'],
                    'document_name' => $documentName,
                    'latitude'      => $req->latitude ?? null,
                    'longitude'     => $req->longitude ?? null,
                    'challan_type'  => $req->challanType,
                ]);
                $docMetadata->save();
                $data["{$inputName}_details"] = $docMetadata;
            }
        }
        return $data;
    }

    /**
     * | Get Uploaded Document
     */
    public function getDocument($applicationDtls)
    {
        $docUrl = Config::get('constants.DOC_URL');
        $data = PenaltyDocument::select(
            'id',
            'latitude',
            'longitude',
            'document_name',
            'reference_no'
            // DB::raw("concat('$docUrl/',document_path) as doc_path"),
        )
            ->where('applied_record_id', $applicationDtls->id)
            ->where('challan_type', $applicationDtls->challan_type)
            ->where('status', 1)
            ->get();

        return $data;
    }
}
