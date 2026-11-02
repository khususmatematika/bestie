<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SemesterStatus;
use App\Enums\SemesterTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['academic_year_id', 'term', 'starts_on', 'ends_on', 'status'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'term' => SemesterTerm::class,
            'status' => SemesterStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    /**
     * @return HasMany<AssessmentPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AssessmentPeriod::class);
    }

    /**
     * @return HasMany<TeachingAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /**
     * @param  Builder<Semester>  $query
     * @return Builder<Semester>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SemesterStatus::Aktif->value);
    }

    public static function current(): ?self
    {
        return static::query()->with('year')->active()->first();
    }

    /**
     * Contoh: "Ganjil 2026/2027". Relasi year harus sudah dimuat.
     */
    public function label(): string
    {
        return $this->term->label().' '.($this->year?->name ?? '');
    }

    public function isArchived(): bool
    {
        return $this->status === SemesterStatus::Arsip;
    }

    public function canBeDeleted(): bool
    {
        return ! $this->periods()->exists() && ! $this->assignments()->exists();
    }
}
