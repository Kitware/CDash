<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $projectid
 * @property Carbon $starttime
 * @property Carbon $endtime
 * @property int $autoremovetimeframe
 * @property string $description
 * @property int $summaryemail
 * @property int $includesubprojectotal  // Should this be a boolean?
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
     * @return BelongsTo<Project, self>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id', 'projectid');
    }
}
