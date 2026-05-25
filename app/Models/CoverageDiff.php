<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $basebuildid
 * @property int $comparebuildid
 * @property int $coveredlinesadded
 * @property int $coveredlinesremoved
 * @property int $coveredlinesuncovered
 * @property int $uncoveredlinesadded
 * @property int $uncoveredlinesremoved
 * @property int $uncoveredlinescovered
 *
 * @mixin Builder<CoverageDiff>
 */
class CoverageDiff extends Model
{
    protected $table = 'coveragediff';

    public $timestamps = false;

    protected $fillable = [
        'basebuildid',
        'comparebuildid',
        'coveredlinesadded',
        'coveredlinesremoved',
        'coveredlinesuncovered',
        'uncoveredlinesadded',
        'uncoveredlinesremoved',
        'uncoveredlinescovered',
    ];

    protected $casts = [
        'id' => 'integer',
        'basebuildid' => 'integer',
        'comparebuildid' => 'integer',
        'coveredlinesadded' => 'integer',
        'coveredlinesremoved' => 'integer',
        'coveredlinesuncovered' => 'integer',
        'uncoveredlinesadded' => 'integer',
        'uncoveredlinesremoved' => 'integer',
        'uncoveredlinescovered' => 'integer',
    ];

    /**
     * @return BelongsTo<Build, $this>
     */
    public function baseBuild(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'basebuildid');
    }

    /**
     * @return BelongsTo<Build, $this>
     */
    public function compareBuild(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'comparebuildid');
    }
}
