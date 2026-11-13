<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\User\CreateTeacher;
use App\Actions\User\ResetUserPassword;
use App\Actions\User\SyncTeacherAssignments;
use App\Models\GradeLevel;
use App\Models\Semester;
use App\Models\Subject;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $filter = (string) $request->query('role', '');

        $semester = Semester::current();

        $teachers = User::query()
            ->teachers()
            ->with(['roles:id,name', 'profile:id,user_id,phone', 'teachingAssignments' => fn ($q) => $q->where('semester_id', $semester?->id ?? 0)->with('subject:id,code')])
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"),
            ))
            ->when($filter === RoleName::Validator->value, fn ($q) => $q->role(RoleName::Validator->value))
            ->when($filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(PerPage::resolve($request))
            ->withQueryString()
            ->through(fn (User $u): array => $this->present($u));

        return Inertia::render('Admin/Teachers/Index', [
            'teachers' => $teachers,
            'filters' => ['search' => $search, 'role' => $filter],
            'semester' => $semester === null ? null : ['id' => $semester->id, 'label' => $semester->label(), 'archived' => $semester->isArchived()],
            'subjects' => Subject::query()->with('gradeLevels:id')->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Subject $s): array => ['id' => $s->id, 'name' => $s->name, 'code' => $s->code, 'grade_ids' => $s->gradeLevels->pluck('id')->values()])->values(),
            'grades' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(TeacherRequest $request, CreateTeacher $action, SyncTeacherAssignments $sync): RedirectResponse
    {
        $data = $request->validated();
        $assignments = $data['assignments'] ?? [];
        $given = ! empty($data['password']);
        unset($data['assignments']);

        $created = $action->handle($data);
        $sync->handle($created['user'], Semester::current(), $assignments);

        return back()
            ->with('success', 'Akun guru dibuat.')
            ->with('credential', [
                'name' => $created['user']->name,
                'email' => $created['user']->email,
                'nip' => $created['user']->nip,
                'password' => $created['password'],
                'given' => $given,
            ]);
    }

    public function update(TeacherRequest $request, User $teacher, SyncTeacherAssignments $sync): RedirectResponse
    {
        $this->ensureManageable($teacher);

        $data = $request->validated();
        $phone = $data['phone'] ?? null;
        $assignments = $data['assignments'] ?? null;
        unset($data['phone'], $data['assignments'], $data['password'], $data['must_change_password']);

        $teacher->update($data);
        $teacher->profile()->updateOrCreate([], ['phone' => $phone]);

        if (is_array($assignments)) {
            $sync->handle($teacher, Semester::current(), $assignments);
        }

        ActivityLogger::log('teacher.updated', $teacher);

        return back()->with('success', 'Data guru diperbarui.');
    }

    public function resetPassword(User $teacher, ResetUserPassword $action): RedirectResponse
    {
        $this->ensureManageable($teacher);

        $password = $action->handle($teacher);

        return back()
            ->with('success', 'Kata sandi diatur ulang.')
            ->with('credential', [
                'name' => $teacher->name,
                'email' => $teacher->email,
                'nip' => $teacher->nip,
                'password' => $password,
            ]);
    }

    public function toggleActive(User $teacher): RedirectResponse
    {
        $this->ensureManageable($teacher);

        $teacher->update(['is_active' => ! $teacher->is_active]);

        if (! $teacher->is_active) {
            \App\Support\Sessions::revoke($teacher);
        }

        ActivityLogger::log($teacher->is_active ? 'teacher.activated' : 'teacher.deactivated', $teacher);

        return back()->with('success', $teacher->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    /**
     * Akun dihapus lunak (soft delete) agar riwayat paket soal tetap utuh pada M1+.
     */
    public function destroy(User $teacher): RedirectResponse
    {
        $this->ensureManageable($teacher);

        ActivityLogger::log('teacher.deleted', $teacher, ['email' => $teacher->email]);
        $teacher->delete();
        \App\Support\Sessions::revoke($teacher);

        return back()->with('success', 'Akun guru dihapus.');
    }

    /**
     * Admin tidak boleh diubah lewat modul guru.
     */
    private function ensureManageable(User $user): void
    {
        abort_if($user->hasRole(RoleName::Admin->value), 403, 'Akun admin tidak dapat dikelola di sini.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nip' => $user->nip,
            'phone' => $user->profile?->phone,
            'is_active' => $user->is_active,
            'is_validator' => $user->roles->contains('name', RoleName::Validator->value),
            'must_change_password' => $user->must_change_password,
            'assignments' => $user->relationLoaded('teachingAssignments')
                ? $user->teachingAssignments->groupBy('subject_id')->map(fn ($rows, $subjectId): array => [
                    'subject_id' => (int) $subjectId,
                    'code' => $rows->first()->subject->code,
                    'grade_level_ids' => $rows->pluck('grade_level_id')->values(),
                ])->values()
                : [],
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
