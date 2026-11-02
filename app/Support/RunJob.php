<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Menjalankan job berat tanpa wajib ada worker antrian: segera setelah respons dikirim ke peramban
 * (pengguna tidak menunggu), di proses PHP yang sama. Atur BANKSOAL_INLINE_JOBS=false untuk memakai
 * antrian biasa (butuh `php artisan queue:work`).
 */
final class RunJob
{
    public static function dispatch(object $job): void
    {
        if (! config('banksoal.inline_jobs', true) || config('queue.default') === 'sync') {
            dispatch($job);

            return;
        }

        app()->terminating(static function () use ($job): void {
            @set_time_limit(600);
            dispatch_sync($job);
        });
    }
}
