<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PackageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionPackage extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assessment_period_id',
        'teacher_id',
        'validator_id',
        'subject_id',
        'grade_level_id',
        'title',
        'status',
        'progress_percent',
        'total_items',
        'approved_items',
        'is_self_validated',
        'submitted_at',
        'first_reviewed_at',
        'fixed_at',
        'locked_at',
        'locked_by',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => PackageStatus::class,
            'is_self_validated' => 'boolean',
            'submitted_at' => 'datetime',
            'first_reviewed_at' => 'datetime',
            'fixed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AssessmentPeriod, $this>
     */
    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id')->withTrashed();
    }

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
     * @return HasMany<QuestionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuestionItem::class, 'package_id')->orderBy('number');
    }

    /**
     * @return HasMany<PackageFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(PackageFile::class, 'package_id')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === PackageStatus::Draft;
    }

    public function isLocked(): bool
    {
        return $this->status === PackageStatus::Terkunci;
    }

    /**
     * Paket masih boleh diubah: belum terkunci, periode dibuka, dan semester bukan arsip (R-08, R-12).
     * Relasi assessmentPeriod.semester harus sudah dimuat.
     */
    public function isWritable(): bool
    {
        $period = $this->assessmentPeriod;

        return ! $this->isLocked()
            && $period->isOpen()
            && ! $period->semester->isArchived();
    }
}
