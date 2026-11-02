<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PeriodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssessmentPeriodRequest extends FormRequest
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
        $id = $this->route('period')?->id;

        return [
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => [
                'required',
                Rule::enum(PeriodType::class),
                Rule::unique('assessment_periods', 'type')
                    ->where(fn ($query) => $query->where('semester_id', $this->input('semester_id')))
                    ->ignore($id),
            ],
            'upload_deadline' => ['nullable', 'date'],
            'validation_deadline' => [
                'nullable',
                'date',
                Rule::when($this->filled('upload_deadline'), ['after_or_equal:upload_deadline']),
            ],
            'revision_deadline' => [
                'nullable',
                'date',
                Rule::when($this->filled('validation_deadline'), ['after_or_equal:validation_deadline']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'semester_id' => 'semester',
            'name' => 'nama periode',
            'type' => 'jenis asesmen',
            'upload_deadline' => 'tenggat unggah',
            'validation_deadline' => 'tenggat validasi',
            'revision_deadline' => 'tenggat revisi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['type.unique' => 'Jenis asesmen tersebut sudah ada pada semester yang dipilih.'];
    }
}
