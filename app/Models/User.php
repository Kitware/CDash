<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

/**
 * @property int $id
 * @property bool $admin
 * @property string $firstname
 * @property string $lastname
 * @property string $email
 * @property string $password
 * @property Carbon $password_updated_at
 * @property string $institution
 * @property string $ldapdomain
 * @property string $ldapguid
 *
 * @mixin Builder<User>
 */
class User extends Authenticatable implements MustVerifyEmail, LdapAuthenticatable
{
    use Notifiable;
    use AuthenticatesWithLdap;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'password_updated_at',
        'institution',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'admin' => 'boolean',
        'password_updated_at' => 'datetime',
    ];

    public function getLdapDomainColumn(): string
    {
        return 'ldapdomain';
    }

    public function getLdapGuidColumn(): string
    {
        return 'ldapguid';
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /**
     * @return HasMany<AuthToken>
     */
    public function authTokens(): HasMany
    {
        return $this->hasMany(AuthToken::class, 'userid');
    }

    /**
     * @return BelongsToMany<Project>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'user2project', 'userid', 'projectid');
    }
}
