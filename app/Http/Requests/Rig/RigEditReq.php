<?php

namespace App\Http\Requests\Rig;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RigEditReq extends FormRequest
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
        $rules['id']                  = 'required|int';
        $rules['address']             = 'nullable';
        $rules['applicantName']       = 'nullable';
        $rules['mobileNo']            = 'nullable';
        $rules['email']               = 'nullable';
        $rules['vehicleComapny']      = 'nullable';
        $rules['registrationNumber']  = 'nullable';

        return $rules;
    }
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'    => false,
            'message'   => "Validation Error!",
            'error'     => $validator->errors()
        ], 200));
    }
}
