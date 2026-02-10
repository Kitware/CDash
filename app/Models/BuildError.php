<?php

namespace App\Models;

use Database\Factories\BuildErrorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $buildid
 * @property ?string $workingdirectory
 * @property string $sourcefile
 * @property bool $newstatus
 * @property int $type
 * @property string $stdoutput
 * @property string $stderror
 * @property ?string $exitcondition
 * @property ?string $language
 * @property ?string $targetname
 * @property ?string $outputfile
 * @property ?string $outputtype
 * @property ?int $logline
 * @property ?int $sourceline
 * @property ?int $repeatcount
 *
 * @mixin Builder<BuildError>
 */
class BuildError extends Model
{
    /** @use HasFactory<BuildErrorFactory> */
    use HasFactory;

    protected $table = 'builderrors';

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
        'logline',
        'sourceline',
        'repeatcount',
    ];

    protected $casts = [
        'buildid' => 'integer',
        'type' => 'integer', // TODO: Convert this to an enum
        'logline' => 'integer',
        'stdoutput' => 'string',
        'stderror' => 'string',
        'sourceline' => 'integer',
        'repeatcount' => 'integer',
        'newstatus' => 'boolean',
    ];
}
