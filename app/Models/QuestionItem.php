<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStatus;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'number',
        'status',
        'blueprint',
        'type',
        'stem',
        'options',
        'answer_key',
        'scoring_guide',
        'current_revision_id',
        'reviewed_at',
        'resubmitted_at',
        'images',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'type' => QuestionType::class,
            'blueprint' => 'array',
            'options' => 'array',
            'images' => 'array',
            'reviewed_at' => 'datetime',
            'resubmitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QuestionPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(QuestionPackage::class, 'package_id');
    }

    /**
     * @return HasMany<ItemRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ItemRevision::class, 'item_id')->orderBy('version');
    }

    /**
     * @return HasMany<ItemReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ItemReview::class, 'item_id')->orderBy('reviewed_at')->orderBy('id');
    }

    /**
     * Isi butir saat ini sebagai larik (dipakai untuk snapshot revisi).
     *
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return [
            'blueprint' => $this->blueprint ?? [],
            'type' => $this->type->value,
            'stem' => $this->stem,
            'options' => $this->options ?? [],
            'answer_key' => $this->answer_key,
            'scoring_guide' => $this->scoring_guide,
        ];
    }

    /**
     * Daftar kekurangan data minimum sebelum paket dikirim (R-02). Kosong = lengkap.
     *
     * @return list<string>
     */
    public function missingFields(): array
    {
        $missing = [];
        $blueprint = $this->blueprint ?? [];

        if (trim((string) $this->stem) === '') {
            $missing[] = 'teks soal';
        }

        if (trim((string) ($blueprint['indikator'] ?? '')) === '') {
            $missing[] = 'indikator soal';
        }

        $hasKey = trim((string) $this->answer_key) !== ''
            || ($this->type->acceptsScoringGuideAsKey() && trim((string) $this->scoring_guide) !== '');

        $multiTf = $this->type === QuestionType::BenarSalah && array_filter((array) $this->options, static fn (mixed $v): bool => trim((string) $v) !== '') !== [];

        if ($hasKey && $multiTf) {
            $statements = array_keys(array_filter((array) $this->options, static fn (mixed $v): bool => trim((string) $v) !== ''));
            preg_match_all('/([A-E])\s*=\s*([BS])/u', (string) $this->answer_key, $m);

            if (array_diff($statements, $m[1]) !== []) {
                $missing[] = 'kunci Benar/Salah tiap pernyataan (mis. A=B; B=S)';
            }
        } elseif ($hasKey && $this->type === QuestionType::BenarSalah && ! in_array($this->answer_key, ['B', 'S'], true)) {
            $missing[] = 'kunci Benar/Salah (B atau S)';
        } elseif (! $hasKey) {
            $missing[] = $this->type->acceptsScoringGuideAsKey() ? 'kunci/pedoman penskoran' : 'kunci jawaban';
        }

        if ($this->type->hasOptions() && count(array_filter((array) $this->options, static fn (mixed $v): bool => trim((string) $v) !== '')) < 2) {
            $missing[] = 'pilihan jawaban (minimal 2)';
        }

        return $missing;
    }
}
