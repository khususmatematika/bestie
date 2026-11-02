<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveImportRequest extends FormRequest
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
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'year_label' => ['required', 'string', 'max:40'],
            'file' => ['required', 'file', 'mimes:docx,pdf', 'max:'.(int) config('banksoal.import.max_kb', 5120)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['subject_id' => 'mapel', 'grade_level_id' => 'tingkat', 'year_label' => 'tahun/semester', 'file' => 'berkas'];
    }
}
