<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $buildid
 * @property int $detailsid
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
        'sourcefile',
        'newstatus',
    ];

    protected $casts = [
        'buildid' => 'integer',
        'detailsid' => 'integer',
        'newstatus' => 'integer',
    ];

    /**
     * @return BelongsTo<Build, self>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return HasOne<RichBuildAlertDetails>
     */
    public function details(): HasOne
    {
        return $this->hasOne(RichBuildAlertDetails::class, 'id', 'detailsid');
    }
}
