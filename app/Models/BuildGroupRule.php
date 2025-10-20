<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $groupid
 * @property int $parentgroupid
 * @property string $buildtype
 * @property string $buildname
 * @property int $siteid
 * @property int $expected // bool?
 * @property Carbon $starttime
 * @property Carbon $endtime
 *
 * @mixin Builder<BuildGroupRule>
 */
class BuildGroupRule extends Model
{
    protected $table = 'build2grouprule';

    public $timestamps = false;

    protected $fillable = [
        'groupid',
        'parentgroupid',
        'buildtype',
        'buildname',
        'siteid',
        'expected',
        'starttime',
        'endtime',
    ];

    protected $casts = [
        'id' => 'integer',
        'groupid' => 'integer',
        'parentgroupid' => 'integer',
        'siteid' => 'integer',
        'expected' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
    ];

    /**
     * @param Builder<$this> $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('endtime', '1980-01-01 00:00:00');
    }
}
