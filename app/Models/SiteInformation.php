<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $timestamp
 * @property bool|null $processoris64bits
 * @property string|null $processorvendor
 * @property string|null $processorvendorid
 * @property int|null $processorfamilyid
 * @property int|null $processormodelid
 * @property string|null $processormodelname
 * @property int|null $processorcachesize
 * @property int|null $numberlogicalcpus
 * @property int|null $numberphysicalcpus
 * @property int|null $totalvirtualmemory
 * @property int|null $totalphysicalmemory
 * @property int|null $logicalprocessorsperphysical
 * @property int|null $processorclockfrequency
 * @property string|null $description
 * @property int $siteid
 *
 * @mixin Builder<SiteInformation>
 */
class SiteInformation extends Model
{
    protected $table = 'siteinformation';

    public $timestamps = false;

    protected $fillable = [
        'processoris64bits',
        'processorvendor',
        'processorvendorid',
        'processorfamilyid',
        'processormodelid',
        'processormodelname',
        'processorcachesize',
        'numberlogicalcpus',
        'numberphysicalcpus',
        'totalvirtualmemory',
        'totalphysicalmemory',
        'logicalprocessorsperphysical',
        'processorclockfrequency',
        'description',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'processoris64bits' => 'boolean',
        'processorfamilyid' => 'int',
        'processormodelid' => 'int',
        'processorcachesize' => 'int',
        'numberlogicalcpus' => 'int',
        'numberphysicalcpus' => 'int',
        'totalvirtualmemory' => 'int',
        'totalphysicalmemory' => 'int',
        'logicalprocessorsperphysical' => 'int',
        'processorclockfrequency' => 'int',
    ];

    /**
     * @return BelongsTo<Site, self>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo('App\Models\Site', 'id');
    }

    public function SetValue(string $tag, string|int $value): void
    {
        switch ($tag) {
            case 'DESCRIPTION':
                $this->description = (string) $value;
                break;
            case 'IS64BITS':
                $this->processoris64bits = (bool) $value;
                break;
            case 'VENDORSTRING':
                $this->processorvendor = (string) $value;
                break;
            case 'VENDORID':
                $this->processorvendorid = (string) $value;
                break;
            case 'FAMILYID':
                $this->processorfamilyid = (int) $value;
                break;
            case 'MODELID':
                $this->processormodelid = (int) $value;
                break;
            case 'MODELNAME':
                $this->processormodelname = (string) $value;
                break;
            case 'PROCESSORCACHESIZE':
                $this->processorcachesize = (int) $value;
                break;
            case 'NUMBEROFLOGICALCPU':
                $this->numberlogicalcpus = (int) $value;
                break;
            case 'NUMBEROFPHYSICALCPU':
                $this->numberphysicalcpus = (int) $value;
                break;
            case 'TOTALVIRTUALMEMORY':
                $this->totalvirtualmemory = (int) $value;
                break;
            case 'TOTALPHYSICALMEMORY':
                $this->totalphysicalmemory = (int) $value;
                break;
            case 'LOGICALPROCESSORSPERPHYSICAL':
                $this->logicalprocessorsperphysical = (int) $value;
                break;
            case 'PROCESSORCLOCKFREQUENCY':
                $this->processorclockfrequency = (int) $value;
                break;
        }
    }
}
