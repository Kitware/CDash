<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $buildid
 * @property int $fileid
 * @property int $covered
 * @property int $loctested
 * @property int $locuntested
 * @property int $branchestested
 * @property int $branchesuntested
 * @property int $functionstested
 * @property int $functionsuntested
 *
 * @mixin Builder<Coverage>
 */
class Coverage extends Model
{
    protected $table = 'coverage';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'fileid',
        'covered',
        'loctested',
        'locuntested',
        'branchestested',
        'branchesuntested',
        'functionstested',
        'functionsuntested',
    ];

    /**
     * @return BelongsTo<Build, self>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }
}
