<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $buildid
 * @property int $type
 * @property int $logline
 * @property string $stdoutput
 * @property string $stderror
 * @property string $sourcefile
 * @property int $sourceline
 * @property int $repeatcount
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
        'stdoutput',
        'stderror',
        'sourcefile',
        'sourceline',
        'repeatcount',
        'newstatus',
    ];

    protected $casts = [
        'buildid' => 'integer',
        'type' => 'integer', // TODO: Convert this to an enum
        'logline' => 'integer',
        'stdoutput' => 'string',
        'stderror' => 'string',
        'sourceline' => 'integer',
        'repeatcount' => 'integer',
        'newstatus' => 'boolean',
    ];

    /**
     * @return Attribute<string,void>
     */
    protected function precontext(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): null => null,
        );
    }

    /**
     * @return Attribute<string,void>
     */
    protected function postcontext(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): null => null,
        );
    }
}
