<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawWalletRequest extends FormRequest
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
            'amount' => [
                'required',
                'numeric',
                'min:100',
                'max:10000000',
            ],
            'bank_account_id' => [
                'required',
                'integer',
                'exists:bank_accounts,id',
            ],
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
            'amount.required' => 'Withdrawal amount is required',
            'amount.numeric' => 'Withdrawal amount must be a valid number',
            'amount.min' => 'Minimum withdrawal amount is ₦100',
            'amount.max' => 'Maximum withdrawal amount is ₦10,000,000',
            'bank_account_id.required' => 'Bank account is required',
            'bank_account_id.integer' => 'Bank account ID must be a valid integer',
            'bank_account_id.exists' => 'Selected bank account does not exist',
        ];
    }
}
