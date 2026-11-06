<?php

namespace App\Models;

use Closure;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
 * @property ?string $ldapdomain
 * @property ?string $ldapguid
 * @property bool $password_login_disabled
 *
 * @mixin Builder<User>
 */
class User extends Authenticatable implements MustVerifyEmail, LdapAuthenticatable
{
    use AuthenticatesWithLdap;
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

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
        'password_login_disabled' => 'boolean',
    ];

    /**
     * Records a login through LDAP, OAuth, or SAML, after which the user can never log in with a
     * CDash password again.
     *
     * The first time, the password is replaced with a random one, which also logs out every other
     * session.  That includes any sessions of someone who registered the address before its owner,
     * since they knew the password.
     */
    public function recordExternalLogin(): void
    {
        if ($this->password_login_disabled) {
            return;
        }

        $this->password_login_disabled = true;
        $this->password = Hash::make(Str::random(40));
        $this->setRememberToken(Str::random(60));
        $this->save();

        Password::deleteToken($this);
    }

    /**
     * Users who have logged in with LDAP, OAuth, or SAML can never log in with a CDash password,
     * and neither can users with LDAP accounts, who log in with their LDAP password.
     */
    public function canLogInWithPassword(): bool
    {
        return !$this->password_login_disabled && !((bool) config('cdash.ldap_enabled') && $this->ldapguid !== null);
    }

    /**
     * A credential which limits user lookups to users who can log in with a CDash password, matching
     * canLogInWithPassword().  The user provider ignores credentials whose names contain "password".
     *
     * @return array<string, Closure>
     */
    public static function passwordLoginCredential(): array
    {
        return [
            'login_method' => function (Builder $query): void {
                $query->where('password_login_disabled', false);
                if ((bool) config('cdash.ldap_enabled')) {
                    $query->whereNull('ldapguid');
                }
            },
        ];
    }

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
     * @return HasMany<AuthToken, $this>
     */
    public function authenticationTokens(): HasMany
    {
        return $this->hasMany(AuthToken::class, 'userid');
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'user2project', 'userid', 'projectid');
    }
}
