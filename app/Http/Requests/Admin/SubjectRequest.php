<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\SubjectCategory;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('subject')?->id;

        return [
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('subjects', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::enum(SubjectCategory::class)],
            'grade_level_ids' => ['required', 'array', 'min:1'],
            'grade_level_ids.*' => ['integer', 'distinct', 'exists:grade_levels,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode mapel',
            'name' => 'nama mapel',
            'category' => 'kategori mapel',
            'grade_level_ids' => 'tingkat kelas',
        ];
    }
}
