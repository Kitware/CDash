<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $starttime
 * @property Carbon $endtime
 * @property string $command
 * @property string $type
 * @property int $nfiles
 * @property int $warnings
 * @property string $revision
 * @property string $priorrevision
 * @property string $path
 *
 * @mixin Builder<BuildUpdate>
 */
class BuildUpdate extends Model
{
    protected $table = 'buildupdate';

    public $timestamps = false;

    protected $fillable = [
        'starttime',
        'endtime',
        'command',
        'type',
        'nfiles',
        'warnings',
        'revision',
        'priorrevision',
        'path',
    ];

    protected $casts = [
        'id' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
        'nfiles' => 'integer',
        'warnings' => 'integer',
    ];

    /**
     * @return BelongsToMany<Build>
     */
    public function builds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class, 'build2update', 'updateid', 'buildid');
    }
}
