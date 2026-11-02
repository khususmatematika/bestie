<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveItem extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['archive_import_id', 'number', 'type', 'stem', 'options', 'answer_key'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    /**
     * @return BelongsTo<ArchiveImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ArchiveImport::class, 'archive_import_id');
    }
}
