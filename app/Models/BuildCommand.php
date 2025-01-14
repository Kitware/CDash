<?php

namespace App\Models;

use App\Enums\BuildCommandType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $buildid
 * @property BuildCommandType $type
 * @property Carbon $starttime
 * @property Carbon $endtime
 * @property string $command
 * @property string $binarydirectory
 * @property string $returnvalue
 * @property string $output
 * @property string $language
 * @property string $target
 * @property string $targettype
 * @property string $source
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
        'endtime',
        'command',
        'binarydirectory',
        'returnvalue',
        'output',
        'language',
        'target',
        'targettype',
        'source',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'type' => BuildCommandType::class,
        'starttime' => 'datetime',
        'endtime' => 'datetime',
    ];

    /**
     * @return BelongsTo<Build, self>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return HasMany<BuildMeasurement>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(BuildMeasurement::class, 'buildcommandid');
    }

    /**
     * @return BelongsToMany<Label>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'buildcommands2labels', 'buildcommandid', 'labelid');
    }
}
