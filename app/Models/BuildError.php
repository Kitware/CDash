<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $buildid
 * @property int $type
 * @property int $ogline
 * @property string $text
 * @property string $sourcefile
 * @property int $sourceline
 * @property string $precontext
 * @property string $postcontext
 * @property int $repeatcount
 * @property int $crc32
 * @property int $newstatus
 *
 * @mixin Builder<BuildError>
 */
class BuildError extends Model
{
    protected $table = 'builderror';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'type',
        'logline',
        'text',
        'sourcefile',
        'sourceline',
        'precontext',
        'postcontext',
        'repeatcount',
        'crc32',
        'newstatus',
    ];
}
