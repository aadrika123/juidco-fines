<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class InfractionRecordingFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'fullName' => 'nullable|string',
            'mobile' => 'nullable|string',
            'email' => 'nullable|email',
            'holdingNo' => 'nullable|string',
            'streetAddress1' => 'nullable|string',
            'city' => 'nullable|string',
            'region' => 'nullable|string',
            'postalCode' => 'nullable|string',
            'country' => 'nullable|string',
            'violationId' => 'required|integer',
            'previousViolationOffence' => 'nullable|boolean',
            'wardId' => 'nullable|int',
            // 'photo' => 'required',
            // 'latitude' => 'required',
            // 'longitude' => 'required',
            'audioVideo' => 'nullable',
            'pdf' => 'nullable'
        ];
    }

    /**
     * Create a response object from the given validation exception.
     *
     * @param  \Illuminate\Contracts\Validation\Validator;
     * @param  \Illuminate\Contracts\Validation\Validator;  $validator
     * @return Illuminate\Http\Exceptions\HttpResponseException
     */

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(validationError($validator));
    }
}
