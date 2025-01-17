<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $hash
 * @property int $userid
 * @property Carbon $created
 * @property Carbon $expires
 * @property string $description
 * @property int $projectid
 * @property string $scope
 *
 * @method static Builder<AuthToken> expired()
 *
 * @mixin Builder<AuthToken>
 */
class AuthToken extends Model
{
    public const SCOPE_FULL_ACCESS = 'full_access';
    public const SCOPE_SUBMIT_ONLY = 'submit_only';

    // Eloquent requires this since we use a non-default creation date column
    // and have no updated timestamp column at all.
    public const CREATED_AT = 'created';
    public const UPDATED_AT = null;

    protected $table = 'authtoken';

    protected $primaryKey = 'hash';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'hash',
        'userid',
        'created',
        'expires',
        'description',
        'projectid',
        'scope',
    ];

    protected $casts = [
        'userid' => 'integer',
        'created' => 'datetime',
        'expires' => 'datetime',
        'projectid' => 'integer',
    ];

    /**
     * @param Builder<AuthToken> $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires', '<', Carbon::now());
    }

    /**
     * @return HasOne<User>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'userid');
    }
}
