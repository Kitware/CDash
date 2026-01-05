<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
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

    /**
     * @return HasOne<CoverageFile, $this>
     */
    public function file(): HasOne
    {
        return $this->hasOne(CoverageFile::class, 'id', 'fileid');
    }

    /**
     * A helper method used by Lighthouse to hide the fact that the path lives in a separate table.
     */
    public function getFilePath(): ?string
    {
        return $this->file?->fullpath;
    }
}
