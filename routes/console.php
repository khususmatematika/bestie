<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Pengingat tenggat setiap pagi. Jalankan penjadwal: `php artisan schedule:work` (dev) atau cron `schedule:run` tiap menit.
Schedule::command('notifications:deadlines')->dailyAt('07:00');
