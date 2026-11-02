<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageExport extends Model
{
    /** @var list<string> */
    protected $fillable = ['package_id', 'requested_by', 'status', 'doc_number', 'path', 'pdf_path', 'sha256', 'format_hash', 'error', 'generated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ExportStatus::class, 'generated_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<QuestionPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(QuestionPackage::class, 'package_id');
    }
}
