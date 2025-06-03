<?php

namespace App\Models;

use App\Enums\GlobalRole;
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
 * @property GlobalRole $role
 * @property Carbon $invitation_timestamp
 * @property URL $invitation_url
 * @property string $password
 *
 * @mixin Builder<ProjectInvitation>
 */
class GlobalInvitation extends Model
{
    protected $table = 'global_invitations';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'invited_by_id',
        'role',
        'invitation_timestamp',
        'password',
    ];

    protected $casts = [
        'invited_by_id' => 'integer',
        'role' => GlobalRole::class,
        'invitation_timestamp' => 'datetime',
    ];

    /**
     * @return Attribute<string,void>
     */
    protected function invitationUrl(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => URL::signedRoute('invitations', ['invitationId' => $attributes['id']]),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }
}
