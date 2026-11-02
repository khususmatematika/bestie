<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\ExportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
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
            'school_name' => ['required', 'string', 'max:150'],
            'allow_self_validation' => ['required', 'boolean'],
            'require_cross_validator_for_self' => ['required', 'boolean'],
            'export_format' => ['required', 'array'],
            'export_format.font' => ['required', 'string', Rule::in(ExportFormat::fonts())],
            'export_format.font_size' => ['required', 'integer', 'between:8,18'],
            'export_format.margin_cm' => ['required', 'numeric', 'between:1,4'],
            'export_format.kop_top' => ['nullable', 'string', 'max:300'],
            'export_format.kop_extra' => ['nullable', 'string', 'max:500'],
            'export_format.show_logo' => ['required', 'boolean'],
            'export_format.footer_text' => ['nullable', 'string', 'max:120'],
            'export_format.doc_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9._-]+$/'],
            'export_format.show_key_sheet' => ['required', 'boolean'],
            'export_format.show_blueprint' => ['required', 'boolean'],
            'export_format.show_recap' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'school_name' => 'nama sekolah',
            'export_format.font' => 'font',
            'export_format.font_size' => 'ukuran font',
            'export_format.margin_cm' => 'margin',
            'export_format.doc_prefix' => 'awalan nomor dokumen',
        ];
    }
}
