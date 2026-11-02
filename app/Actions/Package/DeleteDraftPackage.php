<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Exceptions\WorkflowException;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Gate;

/**
 * Guru menghapus paket yang masih draft (soft delete). Paket yang sudah dikirim tidak dapat dihapus.
 */
final class DeleteDraftPackage
{
    public function handle(QuestionPackage $package, User $teacher): void
    {
        Gate::forUser($teacher)->authorize('manage', $package);

        if (! $package->isDraft()) {
            throw new WorkflowException('Paket yang sudah dikirim tidak dapat dihapus.');
        }

        ActivityLogger::log('package.deleted', $package, ['title' => $package->title], $teacher);

        $package->delete();
    }
}
