<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'starts_on', 'ends_on', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Semester, $this>
     */
    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    /**
     * Tahun ajaran yang sudah memiliki semester tidak boleh dihapus.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->semesters()->exists();
    }
}
