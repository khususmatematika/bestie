<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PeriodStatus;
use App\Enums\PeriodType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentPeriod extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'semester_id',
        'name',
        'type',
        'upload_deadline',
        'validation_deadline',
        'revision_deadline',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => PeriodType::class,
            'status' => PeriodStatus::class,
            'upload_deadline' => 'date',
            'validation_deadline' => 'date',
            'revision_deadline' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Buka;
    }

    /**
     * Contoh: "STS · Ganjil 2026/2027". Relasi semester.year harus sudah dimuat.
     */
    public function label(): string
    {
        return $this->type->short().' · '.($this->semester?->label() ?? '');
    }

    /**
     * Ditambah pengecekan paket soal pada M1.
     */
    public function canBeDeleted(): bool
    {
        return true;
    }
}
