<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemReview extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['item_id', 'revision_id', 'validator_id', 'decision', 'checklist', 'note', 'reviewed_at'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'checklist' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QuestionItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(QuestionItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<ItemRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ItemRevision::class, 'revision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id')->withTrashed();
    }
}
