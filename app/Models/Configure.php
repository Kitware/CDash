<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $command
 * @property string $log
 * @property int $status
 * @property int $warnings
 * @property int $crc32
 *
 * @mixin Builder<Configure>
 */
class Configure extends Model
{
    protected $table = 'configure';

    public $timestamps = false;

    protected $fillable = [
        'command',
        'log',
        'status',
        'warnings',
        'crc32',
    ];

    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'warnings' => 'integer',
        'crc32' => 'integer',
    ];

    /**
     * @return HasManyThrough<Build, BuildConfigure>
     */
    public function builds(): HasManyThrough
    {
        return $this->hasManyThrough(Build::class, BuildConfigure::class, 'configureid', 'id', 'id', 'buildid');
    }
}
