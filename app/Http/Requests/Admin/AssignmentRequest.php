<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Validation\Validator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AssignmentRequest extends FormRequest
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
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'teacher_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $isTeacher = User::query()
                        ->whereKey($value)
                        ->where('is_active', true)
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [RoleName::Teacher->value, RoleName::Validator->value]))
                        ->exists();

                    if (! $isTeacher) {
                        $fail('Guru yang dipilih tidak valid atau tidak aktif.');
                    }
                },
            ],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level_ids' => ['required', 'array', 'min:1'],
            'grade_level_ids.*' => ['integer', 'distinct', 'exists:grade_levels,id'],
        ];
    }

    /**
     * Tingkat yang dipilih harus tingkat tempat mapel tersebut diajarkan.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subject = Subject::query()->find($this->integer('subject_id'));

                if ($subject === null) {
                    return;
                }

                $allowed = $subject->gradeLevels()->pluck('grade_levels.id')->all();
                $invalid = GradeLevel::query()
                    ->whereIn('id', (array) $this->input('grade_level_ids'))
                    ->whereNotIn('id', $allowed)
                    ->orderBy('sort_order')
                    ->pluck('name');

                if ($invalid->isNotEmpty()) {
                    $validator->errors()->add('grade_level_ids', "Mapel {$subject->name} tidak diajarkan di kelas {$invalid->implode(', ')}.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'semester_id' => 'semester',
            'teacher_id' => 'guru',
            'subject_id' => 'mapel',
            'grade_level_ids' => 'tingkat',
        ];
    }
}
