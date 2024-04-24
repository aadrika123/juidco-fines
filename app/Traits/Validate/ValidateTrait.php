<?php

namespace App\Traits\Validate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/***
 * @Parent - App\Http\Request\AuthUserRequest
 * Author Name-Anshu Kumar
 * Created On- 27-06-2022
 * Creation Purpose- For Validating During User Registeration
 * Coding Tested By-
 */

trait ValidateTrait
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function a()
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
