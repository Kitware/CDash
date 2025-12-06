<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buildgroupid
 * @property int $position
 * @property Carbon $starttime
 * @property Carbon $endtime
 *
 * @mixin Builder<BuildGroupPosition>
 */
class BuildGroupPosition extends Model
{
    protected $table = 'buildgroupposition';

    public $timestamps = false;

    protected $fillable = [
        'position',
        'starttime',
        'endtime',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildgroupid' => 'integer',
        'position' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
    ];

    /**
     * @return BelongsTo<BuildGroup,$this>
     */
    public function buildGroup(): BelongsTo
    {
        return $this->belongsTo(BuildGroup::class, 'buildgroupid');
    }
}
