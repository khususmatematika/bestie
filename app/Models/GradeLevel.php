<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubjectCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GradeLevel extends Model
{
    /**
     * `phase` = fase mapel umum, `phase_elective` = fase mapel pilihan
     * (Kelas X: Fase E; XI & XII: Fase F untuk umum, Fase F+ untuk pilihan).
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'phase', 'phase_elective', 'sort_order'];

    /**
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'grade_level_subject');
    }

    /**
     * Fase yang berlaku untuk kategori mapel tertentu di tingkat ini.
     */
    public function phaseFor(SubjectCategory $category): ?string
    {
        return $category === SubjectCategory::Pilihan
            ? ($this->phase_elective ?? $this->phase)
            : $this->phase;
    }
}
