<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $buildid
 * @property string $workingdirectory
 * @property string $sourcefile
 * @property int $newstatus
 * @property int $type
 * @property string $stdoutput
 * @property string $stderror
 * @property string $exitcondition
 * @property string $language
 * @property string $targetname
 * @property string $outputfile
 * @property string $outputtype
 *
 * @mixin Builder<RichBuildAlert>
 */
class RichBuildAlert extends Model
{
    protected $table = 'buildfailure';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'workingdirectory',
        'sourcefile',
        'newstatus',
        'type',
        'stdoutput',
        'stderror',
        'exitcondition',
        'language',
        'targetname',
        'outputfile',
        'outputtype',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'newstatus' => 'integer',
        'type' => 'integer',
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
        return $this->belongsToMany(Label::class, 'label2buildfailure', 'buildfailureid', 'labelid');
    }
}
