<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\QuestionType;
use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Isi satu nomor soal (editor draft maupun unggah ulang). Teks polos; tidak ada HTML.
 */
class ItemContentRequest extends FormRequest
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
            'type' => ['required', Rule::enum(QuestionType::class)],
            'stem' => ['required', 'string', 'max:20000'],
            'options' => ['nullable', 'array:A,B,C,D,E'],
            'options.*' => ['nullable', 'string', 'max:2000'],
            'answer_key' => ['nullable', 'string', 'max:1000'],
            'scoring_guide' => ['nullable', 'string', 'max:5000'],
            'blueprint' => ['nullable', 'array'],
            'blueprint.cp' => ['nullable', 'string', 'max:500'],
            'blueprint.materi' => ['nullable', 'string', 'max:500'],
            'blueprint.submateri' => ['nullable', 'string', 'max:500'],
            'blueprint.indikator' => ['nullable', 'string', 'max:1000'],
            'blueprint.level' => ['nullable', 'string', 'max:30'],
            'blueprint.bobot' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'attachment' => [
                'nullable',
                'file',
                'max:'.((int) config('banksoal.upload.max_mb', 20) * 1024),
                new SafeUpload(['docx', 'xlsx', 'pdf', 'png', 'jpg', 'jpeg']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'bentuk soal',
            'stem' => 'teks soal',
            'options' => 'pilihan jawaban',
            'answer_key' => 'kunci jawaban',
            'scoring_guide' => 'pedoman penskoran',
            'blueprint.cp' => 'capaian pembelajaran',
            'blueprint.materi' => 'materi',
            'blueprint.indikator' => 'indikator soal',
            'blueprint.level' => 'level kognitif',
            'blueprint.bobot' => 'bobot',
            'attachment' => 'lampiran',
        ];
    }
}
