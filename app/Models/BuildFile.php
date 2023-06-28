<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $type
 * @property string $filename
 * @property string $md5
 * @property int $buildid
 *
 * @mixin Builder<BuildFile>
 */
class BuildFile extends Model
{
    protected $table = 'buildfile';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'filename',
        'md5',
        'buildid',
    ];
}
