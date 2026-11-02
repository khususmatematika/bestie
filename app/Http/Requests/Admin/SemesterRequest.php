<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SemesterTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SemesterRequest extends FormRequest
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
        $id = $this->route('semester')?->id;

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term' => [
                'required',
                Rule::enum(SemesterTerm::class),
                Rule::unique('semesters', 'term')
                    ->where(fn ($query) => $query->where('academic_year_id', $this->input('academic_year_id')))
                    ->ignore($id),
            ],
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
            'academic_year_id' => 'tahun ajaran',
            'term' => 'semester',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['term.unique' => 'Semester tersebut sudah ada pada tahun ajaran yang dipilih.'];
    }
}
