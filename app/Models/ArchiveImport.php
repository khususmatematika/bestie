<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveImport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['subject_id', 'grade_level_id', 'year_label', 'original_name', 'items_count', 'imported_by'];

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return HasMany<ArchiveItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ArchiveItem::class);
    }
}
