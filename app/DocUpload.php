<?php

namespace App;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class DocUpload
{
    /**
     * | Image Document Upload
     * | @param refImageName format Image Name like SAF-geotagging-id (Pass Your Ref Image Name Here)
     * | @param requested image (pass your request image here)
     * | @param relativePath Image Relative Path (pass your relative path of the image to be save here)
     * | @return imageName imagename to save (Final Image Name with time and extension)
     */
    public function uploadOld($refImageName, $image, $relativePath)
    {
        $extention = $image->extension();
        $imageName = time() . '-' . $refImageName . '.' . $extention;
        $image->move($relativePath, $imageName);

        return $imageName;
    }

    /**
     * | New DMS Code
     */
    public function upload($request)
    {
        try {
            // $contentType = (collect(($request->headers->all())['content-type'] ?? "")->first());
            $dmsUrl = Config::get('constants.DMS_URL');
            $file = $request->document;
            $filePath = $file->getPathname();
            $hashedFile = hash_file('sha256', $filePath);
            $filename = ($request->document)->getClientOriginalName();
            $api = "$dmsUrl/backend/document/upload";
            $transfer = [
                "file" => $request->document,
                "tags" => $filename,
                // "reference" => 425
            ];
            $returnData = Http::withHeaders([
                "x-digest"      => "$hashedFile",
                "token"         => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
                "folderPathId"  => 1
            ])->attach([
                [
                    'file',
                    file_get_contents($filePath),
                    $filename
                ]
            ])->post("$api", $transfer);
            if ($returnData->successful()) {
                return (json_decode($returnData->body(), true));
            }
            throw new Exception((json_decode($returnData->body(), true))["message"] ?? "");
        } catch (Exception $e) {
            return ["status" => false, "message" => $e->getMessage(), "data" => ""];
        }
    }

    /**
     * | This function is to get the document url from the DMS for single documents
     */
    public function getSingleDocUrl($document)
    {
        $dmsUrl = Config::get('constants.DMS_URL');
        
        $apiUrl = "$dmsUrl/backend/document/view-by-reference";
        $key = collect();

        if ($document) {
            $postData = [
                'referenceNo' => $document->reference_no,
            ];
            $response = Http::withHeaders([
                "token" => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
            ])->post($apiUrl, $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $key['id'] =  $document->id ?? null;
                $key['doc_id'] =  $document->doc_id ?? null;
                $key['doc_code'] =  $document->doc_code;
                $key['verify_status'] =  $document->verify_status;
                $key['owner_name'] =  $document->owner_name;
                $key['remarks'] =  $document->remarks ?? null;
                $key['doc_path'] = $responseData['data']['fullPath'] ?? "";
                $key['responseData'] = $responseData;
                // $data->push($key);
            }
        }
        return $key;
    }

    /**
     * | This function is to get the document url from the DMS for multiple documents
     */
    public function getDocUrl($documents)
    {
        $dmsUrl = Config::get('constants.DMS_URL');
        $apiUrl = "$dmsUrl/backend/document/view-by-reference";
        $data = collect();

        foreach ($documents as $document) {
            $postData = [
                'referenceNo' => $document->reference_no,
            ];
            if ($document->reference_no) {
                $response = Http::withHeaders([
                    "token" => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
                ])->post($apiUrl, $postData);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $key['id'] =  $document->id;
                    $key['doc_code'] =  $document->doc_code??"";
                    $key['verify_status'] =  $document->verify_status??"";
                    $key['owner_name'] =  $document->owner_name??"";
                    $key['remarks'] =  $document->remarks??"";
                    $key['owner_dtl_id'] =  $document->owner_dtl_id ?? null;
                    $key['doc_path'] = $responseData['data']['fullPath'] ?? null;
                    $key['latitude'] = $document->latitude ?? null;
                    $key['longitude'] = $document->longitude ?? null;
                    $key['document_name'] = $document->document_name ?? null;
                    $key['responseData'] = $responseData;
                    $data->push($key);
                }
            }
        }
        return $data;
    }
}
