<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string $osname
 * @property string $osplatform
 * @property string $osrelease
 * @property string $osversion
 * @property string $compilername
 * @property string $compilerversion
 *
 * @method static Builder<Build> betweenDates(?Carbon $starttime, ?Carbon $endtime)
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
        'osname',
        'osplatform',
        'osrelease',
        'osversion',
        'compilername',
        'compilerversion',
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
     * @return BelongsToMany<Note, $this>
     */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'build2note', 'buildid', 'noteid')
            ->withPivot('time');
    }

    /**
     * @return BelongsTo<Project, $this>
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
     * @return HasMany<Test, $this>
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class, 'buildid');
    }

    /**
     * @return HasOneThrough<Configure, BuildConfigure, $this>
     */
    public function configure(): HasOneThrough
    {
        return $this->hasOneThrough(Configure::class, BuildConfigure::class, 'buildid', 'id', 'id', 'configureid');
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'siteid', 'id');
    }

    /**
     * @return HasMany<BasicBuildAlert, $this>
     */
    public function basicAlerts(): HasMany
    {
        return $this->hasMany(BasicBuildAlert::class, 'buildid');
    }

    /**
     * @return HasMany<BasicBuildAlert, $this>
     */
    public function basicErrors(): HasMany
    {
        return $this->basicAlerts()
            ->where('type', self::TYPE_ERROR);
    }

    /**
     * @return HasMany<BasicBuildAlert, $this>
     */
    public function basicWarnings(): HasMany
    {
        return $this->basicAlerts()
            ->where('type', self::TYPE_WARN);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'buildid');
    }

    /**
     * @return BelongsToMany<BuildGroup, $this>
     */
    public function buildGroups(): BelongsToMany
    {
        return $this->belongsToMany(BuildGroup::class, 'build2group', 'groupid', 'buildid');
    }

    /**
     * @return HasMany<Coverage, $this>
     */
    public function coverageResults(): HasMany
    {
        return $this->hasMany(Coverage::class, 'buildid');
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label2build', 'buildid', 'labelid');
    }

    /**
     * @return BelongsToMany<UploadFile, $this>
     */
    public function uploadedFiles(): BelongsToMany
    {
        return $this->belongsToMany(UploadFile::class, 'build2uploadfile', 'buildid', 'fileid');
    }

    /**
     * @return HasMany<BuildCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(BuildCommand::class, 'buildid');
    }

    /**
     * @return HasMany<Target, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(Target::class, 'buildid');
    }

    /**
     * @return HasMany<Build, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parentid');
    }

    /**
     * @return HasOne<self, $this>
     */
    public function parent(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'parentid');
    }

    /**
     * TODO: Perhaps rename this function in the future to make it less similar to Laravel's update()?
     *
     * @return BelongsToMany<BuildUpdate, $this>
     */
    public function updates(): BelongsToMany
    {
        return $this->belongsToMany(BuildUpdate::class, 'build2update', 'buildid', 'updateid');
    }

    /**
     * @return HasMany<DynamicAnalysis, $this>
     */
    public function dynamicAnalyses(): HasMany
    {
        return $this->hasMany(DynamicAnalysis::class, 'buildid');
    }
}
