<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubjectCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'name', 'category', 'is_active'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'category' => SubjectCategory::class,
        ];
    }

    /**
     * Tingkat tempat mapel ini diajarkan.
     *
     * @return BelongsToMany<GradeLevel, $this>
     */
    public function gradeLevels(): BelongsToMany
    {
        return $this->belongsToMany(GradeLevel::class, 'grade_level_subject')->orderBy('grade_levels.sort_order');
    }

    /**
     * @return HasMany<TeachingAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function canBeDeleted(): bool
    {
        return ! $this->assignments()->exists();
    }

    public function isElective(): bool
    {
        return $this->category === SubjectCategory::Pilihan;
    }
}
