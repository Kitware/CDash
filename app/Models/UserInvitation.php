<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property string $email
 * @property int $invited_by_id
 * @property int $project_id
 * @property ProjectRole $role
 * @property Carbon $invitation_timestamp
 * @property URL $invitation_url
 *
 * @mixin Builder<UserInvitation>
 */
class UserInvitation extends Model
{
    protected $table = 'user_invitations';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'invited_by_id',
        'project_id',
        'role',
        'invitation_timestamp',
    ];

    protected $casts = [
        'invited_by_id' => 'integer',
        'project_id' => 'integer',
        'role' => ProjectRole::class,
        'invitation_timestamp' => 'datetime',
    ];

    /**
     * @return Attribute<string,void>
     */
    protected function invitationUrl(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => url('/invitations/' . $attributes['id']),
        );
    }

    /**
     * @return BelongsTo<User, UserInvitation>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    /**
     * @return BelongsTo<Project, UserInvitation>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
