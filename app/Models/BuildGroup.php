<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $projectid
 * @property Carbon $starttime
 * @property Carbon $endtime
 * @property int $autoremovetimeframe
 * @property ?string $description
 * @property int $summaryemail
 * @property int $includesubprojectotal // Should this be a boolean?
 * @property int $emailcommitters // Should this be a boolean?
 * @property string $type
 *
 * @mixin Builder<BuildGroup>
 */
class BuildGroup extends Model
{
    protected $table = 'buildgroup';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'projectid',
        'starttime',
        'endtime',
        'autoremovetimeframe',
        'description',
        'summaryemail',
        'includesubprojectotal',
        'emailcommitters',
        'type',
    ];

    protected $casts = [
        'id' => 'integer',
        'projectid' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
        'autoremovetimeframe' => 'integer',
        'summaryemail' => 'integer',
        'includesubprojectotal' => 'integer',
        'emailcommitters' => 'integer',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id', 'projectid');
    }

    /**
     * @return BelongsToMany<Build, $this>
     */
    public function builds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class, 'build2group', 'groupid', 'buildid');
    }

    /**
     * All the positions this group has ever been in.  Most users probably want to use a scoped
     * version of this relationship instead.
     *
     * @return HasMany<BuildGroupPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(BuildGroupPosition::class, 'buildgroupid');
    }
}
