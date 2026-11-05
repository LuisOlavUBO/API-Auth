<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'rut', 'entra_oid'])]
#[Hidden(['password', 'remember_token', 'entra_oid'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The public identifier other systems store goes in "uuid"; "id" stays the
     * auto-incrementing key.
     *
     * @return string[]
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Always in lowercase: SQL Server returns uniqueidentifier in uppercase.
     */
    protected function uuid(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value === null ? null : strtolower($value));
    }

    /**
     * Roles of this user, in any application.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    /**
     * Roles of this user that belong to one application.
     *
     * @return Collection<int, Role>
     */
    public function rolesIn(int $applicationId): Collection
    {
        return $this->roles()->where('roles.application_id', $applicationId)->orderBy('roles.name')->get();
    }

    /**
     * Names of the scopes the user's roles in one application allow in their tokens.
     *
     * @return string[]
     */
    public function allowedScopeNames(int $applicationId): array
    {
        return OAuthScope::whereHas('roles', fn (Builder $roles) => $roles
            ->where('roles.application_id', $applicationId)
            ->whereHas('users', fn (Builder $users) => $users->whereKey($this->getKey())))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * The user of a Microsoft Entra ID account: the one with its object id, else the one with
     * its email, which gets linked (and keeps its password), else a new user without password
     * and without roles. Null when the email belongs to a user linked to another account.
     */
    public static function fromMicrosoft(string $oid, string $email, string $name): ?self
    {
        $linked = static::where('entra_oid', $oid)->first();

        if ($linked !== null) {
            return $linked;
        }

        $sameEmail = static::where('email', $email)->first();

        if ($sameEmail === null) {
            return static::create(['name' => $name, 'email' => $email, 'entra_oid' => $oid]);
        }

        if ($sameEmail->entra_oid !== null) {
            return null;
        }

        $sameEmail->update(['entra_oid' => $oid]);

        return $sameEmail;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
