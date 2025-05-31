<?php

namespace App\Models;

use App\Enums\BuildCommandType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $buildid
 * @property int $targetid
 * @property BuildCommandType $type
 * @property Carbon $starttime
 * @property int $duration Command running time in ms.
 * @property string $command
 * @property string $workingdirectory
 * @property string $result
 * @property string $source
 * @property string $language
 * @property string $config
 *
 * @mixin Builder<BuildCommand>
 */
class BuildCommand extends Model
{
    protected $table = 'buildcommands';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'starttime',
        'duration',
        'command',
        'workingdirectory',
        'result',
        'source',
        'language',
        'config',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'targetid' => 'integer',
        'type' => BuildCommandType::class,
        'starttime' => 'datetime',
        'duration' => 'integer',
    ];

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return HasMany<BuildMeasurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(BuildMeasurement::class, 'buildcommandid');
    }

    /**
     * Some types of commands are associated with a target.
     *
     * https://cmake.org/cmake/help/git-master/manual/cmake-instrumentation.7.html#v1-snippet-file
     *
     * @return BelongsTo<Target, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class, 'targetid');
    }

    /**
     * @return HasMany<BuildCommandOutput, $this>
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(BuildCommandOutput::class, 'buildcommandid');
    }
}
