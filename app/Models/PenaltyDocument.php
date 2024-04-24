<?php

namespace App\Models;

use App\DocUpload;
use Carbon\Carbon;
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
                $refImageName = Str::random(5);
                $extention = $file->extension();

                #_Doc Upload through a Class
                $imageName = $docUpload->upload($refImageName, $file, 'FinePenalty/');

                // $extention = $file->getClientOriginalExtension();
                // $imageName = time() . '-' . $refImageName . '.' . $extention;
                // $file->move(public_path('FinePenalty/'), $imageName);

                $docMetadata = new PenaltyDocument([
                    'applied_record_id' => $id,
                    'document_type' => $extention,
                    'document_path' => 'FinePenalty/' . $imageName,
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


    public function storeDocument1($req, $id, $applicationNo)
    {
        if ($req->file('photo')) {
            $docPath = $req->file('photo')->move(public_path('FinePenalty/'), $req->photo->getClientOriginalName());
            $file_name = 'FinePenalty/' . $req->photo->getClientOriginalName();
            $docType = $req->photo->getClientOriginalExtension();
            // Create a new PhotoMetadata record
            $docMetadata = new PenaltyDocument([
                'applied_record_id' => $id,
                'document_type' => $docType,
                'document_path' => $file_name,
                'document_name' => "Violation Image",
                'latitude' => $req->latitude,
                'longitude' => $req->longitude,
                'document_verified_by' => authUser()->id,
                'document_verified_at' => Carbon::now(),
            ]);
            $docMetadata->save();
            $data['photo_details'] = $docMetadata;
        }

        if ($req->file('audioVideo')) {
            $docPath = $req->file('audioVideo')->move(public_path('FinePenalty/'), $req->audioVideo->getClientOriginalName());
            $file_name = 'FinePenalty/' . $req->audioVideo->getClientOriginalName();
            $docType = $req->audioVideo->getClientOriginalExtension();
            // Create a new PhotoMetadata record
            $docMetadata = new PenaltyDocument([
                'applied_record_id' => $id,
                'document_type' => $docType,
                'document_path' => $file_name,
                'document_name' => "Violation Video",
                'latitude' => $req->latitude ?? null,
                'longitude' => $req->longitude ?? null,
                'document_verified_by' => authUser()->id,
                'document_verified_at' => Carbon::now(),
            ]);
            $docMetadata->save();
            $data['video_details'] = $docMetadata;
        }
        if ($req->file('pdf')) {
            $docPath = $req->file('pdf')->move(public_path('FinePenalty/'), $req->pdf->getClientOriginalName());
            $file_name = 'FinePenalty/' . $req->pdf->getClientOriginalName();
            $docType = $req->pdf->getClientOriginalExtension();
            // Create a new PhotoMetadata record
            $docMetadata = new PenaltyDocument([
                'applied_record_id' => $id,
                'document_type' => $docType,
                'document_path' => $file_name,
                'document_name' => "Violation Document",
                'latitude' => $req->latitude ?? null,
                'longitude' => $req->longitude ?? null,
                'document_verified_by' => authUser()->id,
                'document_verified_at' => Carbon::now(),
            ]);
            $docMetadata->save();
            $data['pdf_details'] = $docMetadata;
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
            DB::raw("concat('$docUrl/',document_path) as doc_path"),
        )
            ->where('applied_record_id', $applicationDtls->id)
            ->where('challan_type', $applicationDtls->challan_type)
            ->where('status', 1)
            ->get();

        return $data;
    }
}
