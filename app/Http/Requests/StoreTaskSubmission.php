<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskSubmission extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        return [

            'task_id' => ['bail', 'required', 'integer'],
            'user_id' => 'bail|required|integer|unique:task_submissions',
            'submission_link' => 'required|url',
            'comment' => 'required|string',

        ];
    }

    public function messages()
    {
        return [
            'user_id.unique' => "You have already submitted",
            'submission_link.required' => "Provide a submission link",
            'submission_link.url' => "Submission link must be a URL",
            'task_id.required' => "No task selected",
            'comment.required' => "Please a comment",
        ];
    }
}