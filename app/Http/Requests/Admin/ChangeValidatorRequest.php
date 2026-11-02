<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ChangeValidatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['validator_id' => ['required', 'integer', 'exists:users,id']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['validator_id' => 'validator'];
    }
}
