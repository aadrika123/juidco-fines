<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UserRegistrationRequest extends FormRequest
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
            'name' => 'required|string|max:30',
            'name' => 'required|string|max:30',
            'email' => 'required|email|unique:users|max:100',
            'password' => [
                'required',
                'min:6',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[a-z]/', $value)) {
                        $fail('The ' . $attribute . ' must contain at least one lowercase letter.');
                    }
                },
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('The ' . $attribute . ' must contain at least one uppercase letter.');
                    }
                },
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[0-9]/', $value)) {
                        $fail('The ' . $attribute . ' must contain at least one digit.');
                    }
                },
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[@$!%*#?&]/', $value)) {
                        $fail('The ' . $attribute . ' must contain a special character.');
                    }
                },
            ],
            'password_confirmation' => 'required|same:password',
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
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    // public function messages()
    // {
    //     return [
    //         'password.regex' => 'The password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character.',
    //     ];
    // }


}
