<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'nip',
        'password',
        'must_change_password',
        'is_active',
        'locale',
        'theme',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasOne<TeacherProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /**
     * @return HasMany<TeachingAssignment, $this>
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    /**
     * Akun yang dikelola sebagai guru (guru biasa atau validator).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeTeachers(Builder $query): Builder
    {
        return $query->whereHas(
            'roles',
            static fn (Builder $roles) => $roles->whereIn('name', [RoleName::Teacher->value, RoleName::Validator->value]),
        );
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin->value);
    }

    public function isValidator(): bool
    {
        return $this->hasRole(RoleName::Validator->value);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters);
    }
}
