<?php

namespace App\Http\Requests\Rig;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;

class RigPaymentReq extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $offlinePaymentModes = Config::get('rig.PAYMENT_MODE');

        $rules['id'] = 'required';
        $rules['paymentMode'] = 'required|in:ONLINE,CASH,CHEQUE,DD,NEFT';
        if (isset($this['paymentMode']) &&  in_array($this['paymentMode'], $offlinePaymentModes) && $this['paymentMode'] != $offlinePaymentModes['3']) {
            $rules['chequeDate']    = "required|date|date_format:Y-m-d";
            $rules['bankName']      = "required";
            $rules['branchName']    = "required";
            $rules['chequeNo']      = "required";
        }
        return $rules;
    }
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'    => false,
            'message'   => "Validation Error!",
            'error'     => $validator->errors()
        ], 422));
    }
}
