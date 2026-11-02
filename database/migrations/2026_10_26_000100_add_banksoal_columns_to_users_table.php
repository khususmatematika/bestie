<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nip', 30)->nullable()->unique()->after('email');
            $table->boolean('must_change_password')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->string('locale', 5)->default('id');
            $table->string('theme', 10)->default('system');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['nip']);
            $table->dropIndex(['is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn(['nip', 'must_change_password', 'is_active', 'locale', 'theme', 'last_login_at']);
        });
    }
};
