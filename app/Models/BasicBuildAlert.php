<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $buildid
 * @property int $type
 * @property int $logline
 * @property string $text
 * @property string $sourcefile
 * @property int $sourceline
 * @property string|null $precontext
 * @property string|null $postcontext
 * @property int $repeatcount
 * @property int $crc32
 * @property bool $newstatus
 *
 * @mixin Builder<BasicBuildAlert>
 */
class BasicBuildAlert extends Model
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

    protected $casts = [
        'buildid' => 'integer',
        'type' => 'integer', // TODO: Convert this to an enum
        'logline' => 'integer',
        'text' => 'string',
        'sourceline' => 'integer',
        'repeatcount' => 'integer',
        'crc32' => 'integer',
        'newstatus' => 'boolean',
    ];
}
