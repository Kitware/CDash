<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dynamicanalysisid
 * @property string $type
 * @property int $value
 *
 * @mixin Builder<DynamicAnalysisDefect>
 */
class DynamicAnalysisDefect extends Model
{
    protected $table = 'dynamicanalysisdefect';

    public $timestamps = false;

    protected $fillable = [
        'dynamicanalysisid',
        'type',
        'value',
    ];

    /**
     * @return BelongsTo<DynamicAnalysis, $this>
     */
    public function dynamicAnalysis(): BelongsTo
    {
        return $this->belongsTo(DynamicAnalysis::class, 'dynamicanalysisid');
    }
}
