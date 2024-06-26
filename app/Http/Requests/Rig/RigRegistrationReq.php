<?php

namespace App\Http\Requests\Rig;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RigRegistrationReq extends FormRequest
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
            'address'               => 'required|',
            'applyThrough'          => 'nullable|int|in:1,2',
            'ownerCategory'         => 'nullable|in:1,2',
            'ulbId'                 => 'nullable|int',
            'ward'                  => 'nullable|int',
            'applicantName'         => "required|",
            'mobileNo'              => "required|digits:10|regex:/[0-9]{10}/",
            'email'                 => "required|email",
            'panNo'                 => "nullable|min:10|max:10|alpha_num|",
            'telephone'             => "nullable|int|regex:/[0-9]{10}/",
            'vehicleComapny'        => "required",
            'registrationNumber'    => "required",
            'documents'            => 'nullable|array',
            'documents.*.image'    => 'nullable|mimes:png,jpeg,pdf,jpg|max:2048',
            
            'documents.*.docCode'  => 'nullable|string',
            'documents.*.ownerDtlId' => 'nullable|integer'
        ];

        if (isset($this->applyThrough) && $this->applyThrough) {
            $rules['propertyNo'] = 'required|';
        }
        if (isset($this->isRenewal) && $this->isRenewal == 1) {
            $rules['registrationId'] = 'required|';
            $rules['isRenewal'] = 'int|in:1,0';
        }
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
