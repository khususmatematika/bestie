<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
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
        $max = (int) config('banksoal.upload.max_mb', 20) * 1024;

        return [
            'assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'kisi_kisi' => ['nullable', 'file', 'max:'.$max, new SafeUpload(['xlsx', 'docx', 'pdf'])],
            'naskah' => ['nullable', 'file', 'max:'.$max, new SafeUpload(['docx', 'pdf'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'assignment_id' => 'mapel dan tingkat',
            'kisi_kisi' => 'berkas kisi-kisi',
            'naskah' => 'berkas naskah',
        ];
    }
}
