<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuthTokenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
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
    /** @use HasFactory<AuthTokenFactory> */
    use HasFactory;

    public const SCOPE_FULL_ACCESS = 'full_access';
    public const SCOPE_SUBMIT_ONLY = 'submit_only';

    // Eloquent requires this since we use a non-default creation date column
    // and have no updated timestamp column at all.
    public const CREATED_AT = 'created';
    public const UPDATED_AT = null;

    protected $table = 'authtoken';

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
        'id' => 'integer',
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
     * @param Builder<AuthToken> $query
     */
    public function scopeVisibleToCurrentUser(Builder $query): void
    {
        $user = auth()->user();

        if ($user === null) {
            // Show no results for anonymous users
            $query->where('userid', -1);
        } elseif ($user->admin) {
            // Admins can see all tokens
            return;
        } else {
            $query->where('userid', $user->id);
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectid');
    }
}
