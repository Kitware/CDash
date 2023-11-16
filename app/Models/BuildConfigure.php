<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * TODO: This class has a one-to-one relationship with the Build model.
 *       In the future, the columns of this model could simply be moved to the Build model.
 *
 * @property int $buildid
 * @property int $configureid
 * @property Carbon $starttime
 * @property Carbon $endtime
 *
 * @mixin Builder<BuildConfigure>
 */
class BuildConfigure extends Model
{
    protected $table = 'build2configure';

    protected $primaryKey = 'buildid';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'configureid',
        'starttime',
        'endtime',
    ];

    protected $casts = [
        'buildid' => 'integer',
        'configureid' => 'integer',
        'starttime' => 'datetime',
        'endtime' => 'datetime',
    ];

    /**
     * @return BelongsTo<Build, self>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return HasOne<Configure>
     */
    public function configure(): HasOne
    {
        return $this->hasOne(Configure::class, 'id', 'buildid');
    }
}
