<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\PackageStatus;
use App\Exceptions\WorkflowException;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Admin merapikan nomor soal yang loncat (mis. 31, 32, 36, 37, 38 → 31, 32, 33, 34, 35) pada paket yang sudah dikirim.
 * Hanya mengubah nomor tampilan: isi butir, riwayat revisi, dan telaah validator melekat pada butir (bukan pada nomor).
 * Paket Fix/Terkunci tidak boleh diubah karena berkas final sudah dibuat.
 */
final class RenumberPackageAsAdmin
{
    /**
     * Nomor diurutkan ulang menjadi 1, 2, 3, … sesuai urutan sekarang.
     *
     * @return array<int, int> peta nomor lama => nomor baru (hanya yang berubah)
     */
    public function handle(QuestionPackage $package, User $admin): array
    {
        return DB::transaction(function () use ($package, $admin): array {
            $package = QuestionPackage::query()->with('assessmentPeriod.semester')->lockForUpdate()->findOrFail($package->id);

            Gate::forUser($admin)->authorize('administer', $package);

            if (in_array($package->status, [PackageStatus::Fix, PackageStatus::Terkunci], true)) {
                throw new WorkflowException('Paket yang sudah Fix atau terkunci tidak dapat diubah nomornya.');
            }

            PackageGuard::ensureWritable($package);

            $map = [];

            // Berurutan naik: nomor baru selalu ≤ nomor lama, tidak bentrok dengan indeks unik (package_id, number).
            QuestionItem::query()->where('package_id', $package->id)->orderBy('number')->get()
                ->each(function (QuestionItem $item, int $index) use (&$map): void {
                    if ($item->number !== $index + 1) {
                        $map[$item->number] = $index + 1;
                        $item->update(['number' => $index + 1]);
                    }
                });

            if ($map !== []) {
                ActivityLogger::log('package.renumbered_by_admin', $package, ['map' => $map], $admin);
            }

            return $map;
        });
    }
}
