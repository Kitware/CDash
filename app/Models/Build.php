<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 * @property string $log
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
 *
 * @mixin Builder<Build>
 */
class Build extends Model
{
    protected $table = 'build';

    public $timestamps = false;

    protected $fillable = [
        'id',
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
        'log',
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
}
