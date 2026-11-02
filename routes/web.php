<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AssessmentPeriodController;
use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\GradeLevelController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\PackageDownloadController;
use App\Http\Controllers\Shared\DownloadController;
use App\Http\Controllers\Shared\PackageFileController;
use App\Http\Controllers\Teacher\ItemController as TeacherItemController;
use App\Http\Controllers\Teacher\PackageController as TeacherPackageController;
use App\Http\Controllers\Teacher\PackageFileController as TeacherPackageFileController;
use App\Http\Controllers\Teacher\TemplateController;
use App\Http\Controllers\Validator\InboxController;
use App\Http\Controllers\Validator\ReviewController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\SubjectImportController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeacherImportController;
use App\Http\Controllers\Admin\ValidatorController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Shared\ContextController;
use App\Http\Controllers\Shared\DashboardController;
use App\Http\Controllers\Shared\GuideController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Shared\ProfileController;
use App\Http\Middleware\ScopeAcademicPeriod;
use App\Support\ActiveRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tamu (belum login)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
});

Route::get('/', function (Request $request) {
    return $request->user()
        ? redirect()->route(ActiveRole::homeRoute(ActiveRole::current($request)))
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Sudah login — tetapi boleh belum mengganti kata sandi awal
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/ganti-password', [PasswordController::class, 'force'])->name('password.force');
    Route::put('/ganti-password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Sudah login + kata sandi sudah diganti
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'password.changed', ScopeAcademicPeriod::class])->group(function (): void {
    // Konteks & profil (semua peran)
    Route::post('/role/switch', [ContextController::class, 'switchRole'])->name('role.switch');
    Route::post('/semester/switch', [ContextController::class, 'switchPeriod'])->name('period.switch');

    // Unduh berkas paket (terotorisasi: guru pemilik, validator terkait, admin)
    Route::get('/packages/{package}/files/{file}', [PackageFileController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('packages.files.download');

    // Rekap, soal tervalidasi, dan naskah final (langkah 7-8)
    Route::middleware('throttle:30,1')->prefix('packages/{package}/downloads')->name('downloads.')->group(function (): void {
        Route::get('/recap', [DownloadController::class, 'recap'])->name('recap');
        Route::get('/validated', [DownloadController::class, 'validated'])->name('validated');
        Route::post('/final', [DownloadController::class, 'requestFinal'])->name('final');
        Route::get('/export/{export}/{format}', [DownloadController::class, 'export'])->whereIn('format', ['docx', 'pdf'])->name('export');
    });

    // Panduan penggunaan + template standar (semua peran)
    Route::get('/panduan', [GuideController::class, 'show'])->name('guide');
    Route::get('/templates/kisi-kisi', [TemplateController::class, 'blueprint'])->middleware('throttle:30,1')->name('templates.blueprint');
    Route::get('/templates/naskah', [TemplateController::class, 'naskah'])->middleware('throttle:30,1')->name('templates.naskah');

    // Notifikasi dalam aplikasi (semua peran)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password');

    // Guru
    Route::middleware('active.role:teacher')->prefix('teacher')->name('teacher.')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'teacher'])->name('dashboard');

        // Paket soal (langkah 3)
        Route::get('/packages', [TeacherPackageController::class, 'index'])->name('packages.index');
        Route::get('/packages/create', [TeacherPackageController::class, 'create'])->name('packages.create');
        Route::post('/packages', [TeacherPackageController::class, 'store'])->name('packages.store');
        Route::get('/packages/{package}', [TeacherPackageController::class, 'show'])->name('packages.show');
        Route::put('/packages/{package}', [TeacherPackageController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{package}', [TeacherPackageController::class, 'destroy'])->name('packages.destroy');
        Route::post('/packages/{package}/files', [TeacherPackageFileController::class, 'store'])->name('packages.files.store');
        Route::post('/packages/{package}/submit', [TeacherPackageController::class, 'submit'])->name('packages.submit');
        Route::post('/packages/{package}/items', [TeacherItemController::class, 'store'])->name('items.store');
        Route::post('/packages/{package}/revision-document', [TeacherItemController::class, 'reviseDocument'])->name('items.revise-document');
        Route::put('/items/{item}', [TeacherItemController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}', [TeacherItemController::class, 'destroy'])->name('items.destroy');
        Route::post('/items/{item}/images', [TeacherItemController::class, 'pasteImage'])->name('items.images.store');
        Route::post('/items/{item}/revision', [TeacherItemController::class, 'revise'])->name('items.revise');
    });

    // Validator
    Route::middleware('active.role:validator')->prefix('validator')->name('validator.')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'validator'])->name('dashboard');

        // Inbox & telaah (langkah 4-6)
        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
        Route::get('/packages/{package}/review/{number?}', [ReviewController::class, 'show'])->whereNumber('number')->name('review');
        Route::post('/items/{item}/approve', [ReviewController::class, 'approve'])->name('items.approve');
        Route::post('/items/{item}/request-change', [ReviewController::class, 'requestChange'])->name('items.request-change');
        Route::post('/items/{item}/reopen', [ReviewController::class, 'reopen'])->name('items.reopen');
    });

    // Admin
    Route::middleware('active.role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

        // Akademik & multi semester
        Route::resource('academic-years', AcademicYearController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('semesters', SemesterController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/semesters/{semester}/activate', [SemesterController::class, 'activate'])->name('semesters.activate');
        Route::resource('periods', AssessmentPeriodController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/periods/{period}/toggle', [AssessmentPeriodController::class, 'toggleStatus'])->name('periods.toggle');

        // Master data
        Route::get('/subjects/import', [SubjectImportController::class, 'index'])->name('subjects.import');
        Route::post('/subjects/import', [SubjectImportController::class, 'store'])->name('subjects.import.store');
        Route::get('/subjects/import/template', [SubjectImportController::class, 'template'])->name('subjects.import.template');
        Route::resource('subjects', SubjectController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('grade-levels', GradeLevelController::class)->only(['index', 'store', 'update', 'destroy']);

        // Akun guru + impor (langkah 1)
        Route::get('/teachers/import', [TeacherImportController::class, 'index'])->name('teachers.import');
        Route::post('/teachers/import', [TeacherImportController::class, 'store'])->name('teachers.import.store');
        Route::get('/teachers/import/template', [TeacherImportController::class, 'template'])->name('teachers.import.template');
        Route::get('/teachers/import/{batch}/accounts', [TeacherImportController::class, 'accounts'])->name('teachers.import.accounts');
        Route::resource('teachers', TeacherController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/teachers/{teacher}/reset-password', [TeacherController::class, 'resetPassword'])->name('teachers.reset-password');
        Route::post('/teachers/{teacher}/toggle-active', [TeacherController::class, 'toggleActive'])->name('teachers.toggle-active');

        // Monitoring paket
        Route::get('/packages', [AdminPackageController::class, 'index'])->name('packages.index');
        Route::get('/packages/download', PackageDownloadController::class)->middleware('throttle:6,1')->name('packages.download');
        Route::post('/packages/{package}/lock', [AdminPackageController::class, 'lock'])->name('packages.lock');
        Route::post('/packages/{package}/change-validator', [AdminPackageController::class, 'changeValidator'])->name('packages.change-validator');

        // Arsip soal tahun lalu (kemiripan)
        Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
        Route::post('/archive', [ArchiveController::class, 'store'])->name('archive.store');
        Route::delete('/archive/{archive}', [ArchiveController::class, 'destroy'])->name('archive.destroy');

        // Penunjukan validator (langkah 2)
        Route::get('/validators', [ValidatorController::class, 'index'])->name('validators.index');
        Route::post('/validators/{teacher}/toggle', [ValidatorController::class, 'toggle'])->name('validators.toggle');
        Route::put('/validators/{teacher}/scopes', [ValidatorController::class, 'scopes'])->name('validators.scopes');

        // Penugasan guru-mapel-tingkat
        Route::post('/assignments/rollover', [AssignmentController::class, 'rollover'])->name('assignments.rollover');
        Route::resource('assignments', AssignmentController::class)->only(['index', 'store', 'destroy']);

        // Pengaturan & audit
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('logs.index');
    });
});
