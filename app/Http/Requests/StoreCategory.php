<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategory extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // $user = auth('api')->user();
        // var_dump($user);
        return true; //$user->role == 'admin' || $user->role == 'superadmin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => ['bail', 'required', 'unique:categories', 'string'],
            'description' => 'bail|nullable|string',
        ];
    }
}