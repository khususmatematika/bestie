<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\User\ToggleValidatorRole;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use App\Models\ValidatorScope;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Langkah 2 prosedur: Admin memilih validator dari guru yang sudah diinput (FR-VAL-01..03).
 */
class ValidatorController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $semester = Semester::current();

        $teachers = User::query()
            ->teachers()
            ->where('is_active', true)
            ->with('roles:id,name')
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->get();

        $scopes = $semester === null
            ? collect()
            : ValidatorScope::query()->where('semester_id', $semester->id)->get()->groupBy('validator_id');

        $rows = $teachers->map(function (User $u) use ($scopes): array {
            $userScopes = $scopes->get($u->id, collect());

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'nip' => $u->nip,
                'is_validator' => $u->roles->contains('name', RoleName::Validator->value),
                // kosong = berlaku untuk semua mapel
                'subject_ids' => $userScopes->pluck('subject_id')->filter()->values()->all(),
            ];
        });

        return Inertia::render('Admin/Validators/Index', [
            'teachers' => $rows->values(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'semester' => $semester === null ? null : ['id' => $semester->id, 'label' => $semester->label()],
            'filters' => ['search' => $search],
        ]);
    }

    public function toggle(User $teacher, ToggleValidatorRole $action): RedirectResponse
    {
        abort_if($teacher->hasRole(RoleName::Admin->value), 403);
        abort_unless($teacher->is_active, 422, 'Akun nonaktif tidak dapat dijadikan validator.');

        $isValidator = $action->handle($teacher);

        if (! $isValidator) {
            // Cabut juga pembatasan mapel agar tidak tersisa data yatim.
            ValidatorScope::query()->where('validator_id', $teacher->id)->delete();
        }

        return back()->with('success', $isValidator ? "{$teacher->name} kini menjadi validator." : "Peran validator {$teacher->name} dicabut.");
    }

    /**
     * Membatasi validator pada mapel tertentu untuk semester aktif. Daftar kosong = semua mapel.
     */
    public function scopes(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->hasRole(RoleName::Validator->value), 422, 'Akun ini bukan validator.');

        $semester = Semester::current();

        if ($semester === null) {
            return back()->with('error', 'Belum ada semester aktif.');
        }

        $data = $request->validate([
            'subject_ids' => ['present', 'array'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ]);

        ValidatorScope::query()
            ->where('semester_id', $semester->id)
            ->where('validator_id', $teacher->id)
            ->delete();

        foreach ($data['subject_ids'] as $subjectId) {
            ValidatorScope::query()->create([
                'semester_id' => $semester->id,
                'validator_id' => $teacher->id,
                'subject_id' => $subjectId,
            ]);
        }

        ActivityLogger::log('validator.scope_updated', $teacher, ['subjects' => $data['subject_ids']]);

        return back()->with('success', 'Cakupan mapel validator disimpan.');
    }
}
