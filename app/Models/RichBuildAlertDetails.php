<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $type
 * @property string $stdoutput
 * @property string $stderror
 * @property string $exitcondition
 * @property string $language
 * @property string $targetname
 * @property string $outputfile
 * @property string $outputtype
 * @property int $crc32
 *
 * @mixin Builder<RichBuildAlertDetails>
 */
class RichBuildAlertDetails extends Model
{
    protected $table = 'buildfailuredetails';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'stdoutput',
        'stderror',
        'exitcondition',
        'language',
        'targetname',
        'outputfile',
        'outputtype',
        'crc32',
    ];

    protected $casts = [
        'type' => 'integer',
        'crc32' => 'integer',
    ];

    /**
     * @return HasManyThrough<Build>
     */
    public function builds(): HasManyThrough
    {
        return $this->hasManyThrough(Build::class, RichBuildAlert::class, 'detailsid', 'id', 'id', 'buildid');
    }
}
