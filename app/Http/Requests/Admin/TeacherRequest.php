<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('email'))) {
            $email = mb_strtolower(trim($this->input('email')));
            $merge['email'] = $email === '' ? null : $email;
        }

        if (is_string($this->input('password')) && trim($this->input('password')) === '') {
            $merge['password'] = null;
        }

        if (is_string($this->input('nip'))) {
            $digits = preg_replace('/\D+/', '', $this->input('nip')) ?? '';
            $merge['nip'] = $digits === '' ? null : $digits;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('teacher')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:nip', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'nip' => ['nullable', 'required_without:email', 'digits_between:8,25', Rule::unique('users', 'nip')->ignore($id)],
            'password' => ['nullable', 'string', 'min:8', 'max:64'],
            'must_change_password' => ['sometimes', 'boolean'],
            'assignments' => ['sometimes', 'array', 'max:60'],
            'assignments.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'assignments.*.grade_level_ids' => ['present', 'array'],
            'assignments.*.grade_level_ids.*' => ['integer', 'exists:grade_levels,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
            'nip' => 'NIP',
            'assignments.*.subject_id' => 'mapel',
            'phone' => 'telepon',
        ];
    }
}
