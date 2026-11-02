<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Support\Notify;
use App\Actions\Export\RequestFinalExport;
use App\Enums\ItemStatus;
use App\Enums\PackageStatus;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;

/**
 * Menghitung ulang progres paket dan menetapkan status turunan (R-06, R-07):
 * semua nomor disetujui => FIX; ada nomor PERLU_GANTI => PERLU_REVISI; selain itu DITELAAH.
 * Draft, Terkirim, dan Terkunci tidak diubah statusnya di sini. Dipanggil di dalam transaksi pemanggil.
 */
final class RecalculatePackageProgress
{
    public function handle(QuestionPackage $package): QuestionPackage
    {
        $counts = QuestionItem::query()
            ->toBase()
            ->where('package_id', $package->id)
            ->selectRaw(
                'count(*) as total, sum(case when status = ? then 1 else 0 end) as approved, sum(case when status = ? then 1 else 0 end) as needs_change',
                [ItemStatus::Disetujui->value, ItemStatus::PerluGanti->value],
            )
            ->first();

        $total = (int) ($counts->total ?? 0);
        $approved = (int) ($counts->approved ?? 0);
        $needsChange = (int) ($counts->needs_change ?? 0);

        $attributes = [
            'total_items' => $total,
            'approved_items' => $approved,
            'progress_percent' => $total === 0 ? 0 : intdiv($approved * 100, $total),
        ];

        $recalculable = [PackageStatus::Ditelaah, PackageStatus::PerluRevisi, PackageStatus::Fix];

        if (in_array($package->status, $recalculable, true)) {
            if ($total > 0 && $approved === $total) {
                $status = PackageStatus::Fix;
            } elseif ($needsChange > 0) {
                $status = PackageStatus::PerluRevisi;
            } else {
                $status = PackageStatus::Ditelaah;
            }

            $attributes['status'] = $status;

            if ($status === PackageStatus::Fix) {
                $attributes['fixed_at'] = $package->status === PackageStatus::Fix ? $package->fixed_at : now();
            } else {
                $attributes['fixed_at'] = null;
            }
        }

        $wasFix = $package->status === PackageStatus::Fix;

        $package->update($attributes);

        // Langkah 8: begitu paket mencapai Fix, naskah final otomatis disiapkan di antrian.
        if (! $wasFix && $package->status === PackageStatus::Fix) {
            app(RequestFinalExport::class)->handle($package);

            Notify::to($package->teacher_id, 'Paket dinyatakan Fix', 'Semua nomor pada paket "'.$package->title.'" sudah disetujui. Naskah final siap diunduh.', '/teacher/packages/'.$package->id, 'fix');
            Notify::to($package->validator_id !== $package->teacher_id ? $package->validator_id : null, 'Paket dinyatakan Fix', 'Paket "'.$package->title.'" telah 100% divalidasi.', '/validator/packages/'.$package->id.'/review', 'fix');
        }

        return $package;
    }
}
