<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
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
        $id = $this->route('academic_year')?->id;

        return [
            'name' => ['required', 'regex:/^\d{4}\/\d{4}$/', Rule::unique('academic_years', 'name')->ignore($id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tahun ajaran',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.regex' => 'Format tahun ajaran harus seperti 2026/2027.'];
    }
}
