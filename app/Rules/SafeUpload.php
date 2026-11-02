<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validasi berkas unggahan (rules.md §6.2): ekstensi masuk daftar putih DAN isi berkas
 * sesuai tanda tangan formatnya (docx/xlsx = ZIP, pdf, png, jpg; csv = teks tanpa byte NUL).
 */
final class SafeUpload implements ValidationRule
{
    /**
     * @param  list<string>  $extensions
     */
    public function __construct(private readonly array $extensions)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Berkas gagal diunggah. Coba lagi.');

            return;
        }

        $extension = mb_strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, $this->extensions, true)) {
            $fail('Format berkas tidak didukung. Gunakan: '.implode(', ', array_map(static fn (string $e): string => '.'.$e, $this->extensions)).'.');

            return;
        }

        $head = (string) file_get_contents((string) $value->getRealPath(), false, null, 0, 4096);

        $valid = match ($extension) {
            'docx', 'xlsx' => str_starts_with($head, "PK\x03\x04"),
            'pdf' => str_starts_with($head, '%PDF'),
            'png' => str_starts_with($head, "\x89PNG"),
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'csv' => ! str_contains($head, "\0"),
            default => false,
        };

        if (! $valid) {
            $fail('Isi berkas tidak sesuai dengan formatnya atau berkas rusak.');
        }
    }
}
