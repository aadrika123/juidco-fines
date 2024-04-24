<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    //View All
    public function retrieveAll(Request $req)
    {
        try {
            $file = storage_path() . "/local-database/country.json";
            return file_get_contents($file);
            // $Banks = $this->_months::orderByDesc('id')->where('status', '1')->get();
            // return responseMsgs(true, "", $file, "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        }
    }
}
