<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $projectid
 * @property int $coveragetheshold
 * @property int $is_default // bool?
 * @property Carbon $starttime
 * @property Carbon $endtime
 * @property int $position
 *
 * @mixin Builder<SubProjectGroup>
 */
class SubProjectGroup extends Model
{
    protected $table = 'subprojectgroup';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'projectid',
        'coveragethreshold',
        'is_default',
        'starttime',
        'endtime',
        'position',
    ];

    protected $casts = [
        'id' => 'integer',
        'projectid' => 'integer',
        'coveragethreshold' => 'integer',
        'is_default' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
        'position' => 'integer',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectid');
    }

    /**
     * @return HasMany<SubProject, $this>
     */
    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class, 'groupid');
    }
}
