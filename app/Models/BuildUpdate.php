<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $starttime
 * @property Carbon $endtime
 * @property string $command
 * @property string $type
 * @property string $status
 * @property int $nfiles
 * @property int $warnings
 * @property int $errors
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
        'status',
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
     * @return Attribute<int,null>
     */
    protected function errors(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): int => $attributes['status'] !== null && $attributes['status'] !== '' && $attributes['status'] !== '0' ? 1 : 0,
        );
    }

    /**
     * @return BelongsToMany<Build, $this>
     */
    public function builds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class, 'build2update', 'updateid', 'buildid');
    }

    /**
     * @return HasMany<BuildUpdateFile, $this>
     */
    public function updateFiles(): HasMany
    {
        return $this->hasMany(BuildUpdateFile::class, 'updateid');
    }
}
