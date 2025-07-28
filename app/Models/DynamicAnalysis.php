<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $buildid
 * @property string $status
 * @property string $checker
 * @property string $name
 * @property string $path
 * @property string $fullcommandline
 * @property string $log
 *
 * @mixin Builder<DynamicAnalysis>
 */
class DynamicAnalysis extends Model
{
    protected $table = 'dynamicanalysis';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'status',
        'checker',
        'name',
        'path',
        'fullcommandline',
        'log',
    ];

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label2dynamicanalysis', 'dynamicanalysisid', 'labelid');
    }
}
