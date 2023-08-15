<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $buildid
 * @property int $loctested
 * @property int $locuntested
 *
 * @mixin Builder<CoverageSummaryDiff>
 */
class CoverageSummaryDiff extends Model
{
    protected $table = 'coveragesummarydiff';

    public $timestamps = false;

    protected $primaryKey = 'buildid';

    protected $fillable = [
        'buildid',
        'loctested',
        'locuntested',
    ];
}
