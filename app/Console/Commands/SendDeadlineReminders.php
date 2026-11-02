<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ItemStatus;
use App\Enums\PackageStatus;
use App\Enums\PeriodStatus;
use App\Models\AssessmentPeriod;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Support\Notify;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * Pengingat tenggat (H-2 sampai hari H) untuk periode yang dibuka. Aman dijalankan berulang: satu pengingat per pengguna per tenggat per hari.
 */
class SendDeadlineReminders extends Command
{
    protected $signature = 'notifications:deadlines {--days=2 : Berapa hari sebelum tenggat mulai diingatkan}';

    protected $description = 'Kirim notifikasi pengingat tenggat unggah, validasi, dan revisi';

    public function handle(): int
    {
        $window = max(0, (int) $this->option('days'));
        $today = today();
        $sent = 0;

        foreach (AssessmentPeriod::query()->where('status', PeriodStatus::Buka->value)->get() as $period) {
            $sent += $this->remind($period, 'unggah naskah', $period->upload_deadline, $window, $today, $this->draftTeachers($period), '/teacher/packages');
            $sent += $this->remind($period, 'validasi', $period->validation_deadline, $window, $today, $this->pendingValidators($period), '/validator/inbox');
            $sent += $this->remind($period, 'revisi soal', $period->revision_deadline, $window, $today, $this->revisingTeachers($period), '/teacher/packages');
        }

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }

    /**
     * @param  iterable<User>  $users
     */
    private function remind(AssessmentPeriod $period, string $label, ?CarbonInterface $deadline, int $window, CarbonInterface $today, iterable $users, string $url): int
    {
        if ($deadline === null) {
            return 0;
        }

        $days = (int) $today->diffInDays($deadline->copy()->startOfDay(), false);

        if ($days < 0 || $days > $window) {
            return 0;
        }

        $when = $days === 0 ? 'hari ini' : "{$days} hari lagi";
        $sent = 0;

        foreach ($users as $user) {
            $key = "tenggat:{$period->id}:{$label}:{$today->toDateString()}";

            if (Notify::already($user, $key)) {
                continue;
            }

            Notify::to($user, 'Tenggat '.$label.' '.$when, 'Periode "'.$period->name.'": tenggat '.$label.' '.$deadline->translatedFormat('d M Y').'.', $url, 'tenggat', $key);
            $sent++;
        }

        return $sent;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function draftTeachers(AssessmentPeriod $period)
    {
        $ids = QuestionPackage::query()->where('assessment_period_id', $period->id)->where('status', PackageStatus::Draft->value)->pluck('teacher_id')->unique();

        return User::query()->whereIn('id', $ids)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function pendingValidators(AssessmentPeriod $period)
    {
        $ids = QuestionPackage::query()
            ->where('assessment_period_id', $period->id)
            ->whereIn('status', [PackageStatus::Terkirim->value, PackageStatus::Ditelaah->value, PackageStatus::PerluRevisi->value])
            ->whereNotNull('validator_id')
            ->pluck('validator_id')
            ->unique();

        return User::query()->whereIn('id', $ids)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function revisingTeachers(AssessmentPeriod $period)
    {
        $ids = QuestionPackage::query()
            ->where('assessment_period_id', $period->id)
            ->whereHas('items', fn ($q) => $q->where('status', ItemStatus::PerluGanti->value))
            ->pluck('teacher_id')
            ->unique();

        return User::query()->whereIn('id', $ids)->get();
    }
}
