<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('question_packages')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('queued');
            $table->string('doc_number', 60)->nullable()->unique();
            $table->string('path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['package_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_exports');
    }
};
