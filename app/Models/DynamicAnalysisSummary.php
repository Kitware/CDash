<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $buildid
 * @property string $checker
 * @property int $numdefects
 *
 * @mixin Builder<DynamicAnalysisSummary>
 */
class DynamicAnalysisSummary extends Model
{
    protected $table = 'dynamicanalysissummary';

    public $timestamps = false;

    // TODO: add a dedicated ID column, leaving buildid as only a foreign key column.
    // Alternatively, just move these columns to the build table.
    protected $primaryKey = 'buildid';

    protected $fillable = [
        'buildid',
        'checker',
        'numdefects',
    ];

    protected $casts = [
        'buildid' => 'integer',
        'numdefects' => 'integer',
    ];
}
