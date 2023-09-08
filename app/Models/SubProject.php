<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $projectid
 * @property int $groupid
 * @property string $path
 * @property int $position
 * @property Carbon $starttime
 * @property Carbon $endtime
 *
 * @mixin Builder<SubProject>
 */
class SubProject extends Model
{
    protected $table = 'subproject';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'projectid',
        'groupid',
        'path',
        'position',
        'starttime',
        'endtime',
    ];

    protected $casts = [
        'id' => 'integer',
        'projectid' => 'integer',
        'groupid' => 'integer',
        'position' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
    ];

    /**
     * @return BelongsTo<Project, self>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id', 'projectid');
    }

    /**
     * Return the subprojects which depended upon this subproject on the specified date.
     * If no date is provided, the current date is used.
     *
     * @return BelongsToMany<self>
     */
    public function children(?Carbon $date = null): BelongsToMany
    {
        if ($date === null) {
            $date = Carbon::now()->setTimezone('UTC');
        }

        return $this->belongsToMany(SubProject::class, 'subproject2subproject', 'subprojectid', 'dependsonid')
            ->wherePivot('starttime', '<=', Carbon::now()->setTimezone('UTC'))
            ->where(function ($query) use ($date) {
                $query->where('subproject2subproject.endtime', '>', $date)
                    ->orWhere('subproject2subproject.endtime', '=', Carbon::create(1980));
            });
    }
}
