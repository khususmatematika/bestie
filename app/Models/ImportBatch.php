<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'original_name',
        'file_path',
        'status',
        'options',
        'results',
        'error_message',
        'total',
        'success',
        'failed',
        'report_path',
        'created_by',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'options' => 'array',
            'results' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
