<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Hanya peran + tingkat X/XI/XII (mapel kosong, diisi admin) (rules.md §4.10). Akun admin dibuat dengan: php artisan bestie:create-admin
     * Data contoh: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            GradeLevelSeeder::class,
        ]);
    }
}
