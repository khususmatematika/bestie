<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => mb_strtoupper(trim($this->input('name')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('grade_level')?->id;

        return [
            'name' => ['required', 'string', 'max:10', Rule::unique('grade_levels', 'name')->ignore($id)],
            'phase' => ['nullable', 'string', 'max:10'],
            'phase_elective' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['required', 'integer', 'between:0,255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama tingkat',
            'phase' => 'fase mapel umum',
            'phase_elective' => 'fase mapel pilihan',
            'sort_order' => 'urutan',
        ];
    }
}
