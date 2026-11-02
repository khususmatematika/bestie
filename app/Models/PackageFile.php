<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExtractionStatus;
use App\Enums\FileKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageFile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'kind',
        'original_name',
        'path',
        'mime',
        'size',
        'sha256',
        'extraction_status',
        'extraction_report',
        'uploaded_by',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => FileKind::class,
            'extraction_status' => ExtractionStatus::class,
            'extraction_report' => 'array',
        ];
    }

    /**
     * @return BelongsTo<QuestionPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(QuestionPackage::class, 'package_id');
    }

    public function extension(): string
    {
        return mb_strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }
}
