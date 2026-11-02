<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemRevision extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['item_id', 'version', 'snapshot', 'source', 'uploaded_by', 'uploaded_at', 'file_id'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'uploaded_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /**
     * @return BelongsTo<PackageFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(PackageFile::class, 'file_id');
    }
}
