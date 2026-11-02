<?php

declare(strict_types=1);

namespace App\Http\Requests\Validator;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Panjang minimal catatan dijaga oleh Action (R-03) agar pesannya seragam.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => 'catatan', 'checklist' => 'daftar periksa'];
    }
}
