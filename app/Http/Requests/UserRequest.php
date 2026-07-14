<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is handled by the 'permission:team' route middleware
    }

    public function rules(): array
    {
        $userId = $this->route('team')?->id;

        return [
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->ignore($userId)],
            'email'    => ['nullable', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone'    => ['nullable', 'string', 'max:40'],
            'role'     => ['required', 'string', Rule::exists('roles', 'name')],
            'status'   => ['required', Rule::in(['active', 'inactive'])],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],

            'sales_commission_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sales_fixed_bonus'         => ['nullable', 'integer', 'min:0'],
            'vendor_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vendor_location'           => ['nullable', 'string', 'max:255'],
        ];
    }
}