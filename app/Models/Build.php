<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $siteid
 * @property int $projectid
 * @property int $parentid
 * @property string $stamp
 * @property string $name
 * @property string $type
 * @property string $generator
 * @property Carbon $starttime
 * @property Carbon $endtime
 * @property Carbon $submittime
 * @property string $command
 * @property int $configureerrors
 * @property int $configurewarnings
 * @property int $configureduration
 * @property int $builderrors
 * @property int $buildwarnings
 * @property int $buildduration
 * @property int $testnotrun
 * @property int $testfailed
 * @property int $testpassed
 * @property int $testtimestatusfailed
 * @property int $testduration
 * @property bool $notified
 * @property bool $done
 * @property string $uuid
 * @property string $changeid
 *
 * @method static Builder betweenDates(?Carbon $starttime, ?Carbon $endtime)
 *
 * @mixin Builder<Build>
 */
class Build extends Model
{
    public const TYPE_ERROR = 0;
    public const TYPE_WARN = 1;

    protected $table = 'build';

    public $timestamps = false;

    protected $fillable = [
        'siteid',
        'projectid',
        'parentid',
        'stamp',
        'name',
        'type',
        'generator',
        'starttime',
        'endtime',
        'submittime',
        'command',
        'configureerrors',
        'configurewarnings',
        'configureduration',
        'builderrors',
        'buildwarnings',
        'buildduration',
        'testnotrun',
        'testfailed',
        'testpassed',
        'testtimestatusfailed',
        'testduration',
        'notified',
        'done',
        'uuid',
        'changeid',
    ];

    protected $casts = [
        'id' => 'integer',
        'siteid' => 'integer',
        'projectid' => 'integer',
        'parentid' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
        'submittime' => 'datetime',
        'configureerrors' => 'integer',
        'configurewarnings' => 'integer',
        'configureduration' => 'integer',
        'builderrors' => 'integer',
        'buildwarnings' => 'integer',
        'buildduration' => 'integer',
        'testnotrun' => 'integer',
        'testfailed' => 'integer',
        'testpassed' => 'integer',
        'testduration' => 'integer',
        'notified' => 'boolean',
        'done' => 'boolean',
    ];

    /**
     * @return HasOne<BuildInformation>
     */
    public function information(): HasOne
    {
        return $this->hasOne(BuildInformation::class, 'buildid');
    }

    /**
     * @return BelongsToMany<Note>
     */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'build2note', 'buildid', 'noteid')
            ->withPivot('time');
    }

    /**
     * @return BelongsTo<Project, self>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectid');
    }

    /**
     * Adds a betweenDates() query builder filter method...
     *
     * @param Builder<self> $query
     */
    public function scopeBetweenDates(Builder $query, ?Carbon $starttime, ?Carbon $endtime): void
    {
        if ($starttime !== null) {
            $query->where('starttime', '>', $starttime);
        }

        if ($endtime !== null) {
            $query->where('endtime', '<=', $endtime);
        }
    }

    /**
     * @return HasMany<Test>
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class, 'buildid');
    }

    /**
     * @return HasOneThrough<Configure>
     */
    public function configure(): HasOneThrough
    {
        return $this->hasOneThrough(Configure::class, BuildConfigure::class, 'buildid', 'id', 'id', 'configureid');
    }

    /**
     * @return BelongsTo<Site, self>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'siteid', 'id');
    }

    /**
     * @return HasMany<BasicBuildAlert>
     */
    public function basicAlerts(): HasMany
    {
        return $this->hasMany(BasicBuildAlert::class, 'buildid');
    }

    /**
     * @return HasMany<BasicBuildAlert>
     */
    public function basicErrors(): HasMany
    {
        return $this->basicAlerts()
            ->where('type', self::TYPE_ERROR);
    }

    /**
     * @return HasMany<BasicBuildAlert>
     */
    public function basicWarnings(): HasMany
    {
        return $this->basicAlerts()
            ->where('type', self::TYPE_WARN);
    }

    /**
     * @return HasMany<Comment>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'buildid');
    }
}
