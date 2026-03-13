<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contribution_amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'total_members' => ['required', 'integer', 'min:2', 'max:100'],
            'cycle_days' => ['required', 'integer', 'min:2', 'max:365'],
            'frequency' => ['required', 'string', 'in:daily,weekly'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required',
            'name.max' => 'Group name cannot exceed 255 characters',
            'contribution_amount.required' => 'Contribution amount is required',
            'contribution_amount.numeric' => 'Contribution amount must be a number',
            'contribution_amount.min' => 'Contribution amount must be at least ₦100',
            'contribution_amount.max' => 'Contribution amount cannot exceed ₦10,000,000',
            'total_members.required' => 'Total members is required',
            'total_members.integer' => 'Total members must be an integer',
            'total_members.min' => 'Group must have at least 2 members',
            'total_members.max' => 'Group cannot have more than 100 members',
            'cycle_days.required' => 'Cycle days is required',
            'cycle_days.integer' => 'Cycle days must be an integer',
            'cycle_days.min' => 'Cycle must be at least 2 days',
            'cycle_days.max' => 'Cycle cannot exceed 365 days',
            'frequency.required' => 'Frequency is required',
            'frequency.in' => 'Frequency must be either daily or weekly',
        ];
    }
}
