<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $projectid
 * @property int $userid
 * @property ProjectRole|null $role
 * @property Carbon $timestamp
 * @property Project $project
 * @property User $user
 *
 * @mixin Builder<ProjectRoleHistory>
 */
class ProjectRoleHistory extends Model
{
    protected $table = 'project_role_history';

    public $timestamps = false;

    protected $fillable = [
        'projectid',
        'userid',
        'role',
        'timestamp',
    ];

    protected $casts = [
        'projectid' => 'integer',
        'userid' => 'integer',
        'role' => ProjectRole::class,
        'timestamp' => 'datetime',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
