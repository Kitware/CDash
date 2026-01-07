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
 * @property int $detailsid
 * @property string $workingdirectory
 * @property string $sourcefile
 * @property int $newstatus
 *
 * @mixin Builder<RichBuildAlert>
 */
class RichBuildAlert extends Model
{
    protected $table = 'buildfailure';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'detailsid',
        'workingdirectory',
        'sourcefile',
        'newstatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'detailsid' => 'integer',
        'newstatus' => 'integer',
    ];

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return HasOne<RichBuildAlertDetails, $this>
     */
    public function details(): HasOne
    {
        return $this->hasOne(RichBuildAlertDetails::class, 'id', 'detailsid');
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label2buildfailure', 'buildfailureid', 'labelid');
    }
}
