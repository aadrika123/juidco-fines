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
        $rules = [
            'id'                    => 'required|int',
            'address'               => 'required|',
            'applicantName'         => "required|",
            'mobileNo'              => "required|digits:10|regex:/[0-9]{10}/",
            'email'                 => "required|email",
            'vehicleComapny'        => "required",
            'registrationNumber'    => "required",
            'documents'             => 'nullable|array',
            'documents.*.image'     => 'nullable|mimes:png,jpeg,pdf,jpg|max:2048',

            'documents.*.docCode'    => 'nullable|string',
            'documents.*.ownerDtlId' => 'nullable|integer'
        ];

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
