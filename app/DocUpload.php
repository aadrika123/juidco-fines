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
}
