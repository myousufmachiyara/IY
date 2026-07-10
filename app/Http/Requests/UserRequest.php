<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('team')?->id; // resource param name = "team"

        return [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone'  => ['nullable', 'string', 'max:40'],
            'role'   => ['required', Rule::in(['super_admin', 'accountant', 'sales_agent', 'vendor_agent'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],

            // conditional economics
            'sales_commission_percent'  => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:role,sales_agent'],
            'sales_fixed_bonus'         => ['nullable', 'integer', 'min:0', 'required_if:role,sales_agent'],
            'vendor_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:role,vendor_agent'],
            'vendor_location'           => ['nullable', 'string', 'max:255'],
        ];
    }
}