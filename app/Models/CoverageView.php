<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * This class serves as a READ-ONLY logical grouping of all the parts for a single file's coverage
 * results for a given build.  CoverageFile and CoverageFileLog deduplicate the largest fields in
 * this logical relation, but one cannot be had without the other.  Under the hood, this is simply
 * the Coverage model joined with the CoverageFile and CoverageFileLog models.
 *
 * @property int $buildid
 * @property int $covered
 * @property int $loctested
 * @property int $locuntested
 * @property int $branchestested
 * @property int $branchesuntested
 * @property int $functionstested
 * @property int $functionsuntested
 * @property ?string $fullpath
 * @property ?string $file
 * @property ?string $log
 *
 * @mixin Builder<CoverageView>
 */
class CoverageView extends Model
{
    protected $table = 'coverageview';

    public $timestamps = false;

    protected $fillable = [];

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
        return $this->belongsToMany(Label::class, 'label2coverage', 'coverageid', 'labelid');
    }
}
