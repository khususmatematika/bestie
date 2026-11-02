<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;

class ReviseFromDocumentRequest extends FormRequest
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
            'naskah' => ['required', 'file', 'max:'.((int) config('banksoal.upload.max_mb', 20) * 1024), new SafeUpload(['docx', 'pdf'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['naskah' => 'berkas naskah'];
    }
}
