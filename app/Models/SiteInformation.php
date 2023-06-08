<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $timestamp
 * @property int $processoris64bits
 * @property string $processorvendor
 * @property string $processorvendorid
 * @property int $processorfamilyid
 * @property int $processormodelid
 * @property int $processorcachesize
 * @property int $numberlogicalcpus
 * @property int $numberphysicalcpus
 * @property int $totalvirtualmemory
 * @property int $totalphysicalmemory
 * @property int $logicalprocessorsperphysical
 * @property int $processorclockfrequency
 * @property string $description
 * @property int $siteid
 *
 * @mixin Builder<SiteInformation>
 */
class SiteInformation extends Model
{
    protected $table = 'siteinformation';

    public $timestamps = false;

    protected $fillable = [
        'timestamp',
        'processoris64bits',
        'processorvendor',
        'processorvendorid',
        'processorfamilyid',
        'processormodelid',
        'processorcachesize',
        'numberlogicalcpus',
        'numberphysicalcpus',
        'totalvirtualmemory',
        'totalphysicalmemory',
        'logicalprocessorsperphysical',
        'processorclockfrequency',
        'description',
        'siteid',
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
                $this->description = $value;
                break;
            case 'IS64BITS':
                $this->processoris64bits = (int) $value;
                break;
            case 'VENDORSTRING':
                $this->processorvendor = $value;
                break;
            case 'VENDORID':
                $this->processorvendorid = $value;
                break;
            case 'FAMILYID':
                $this->processorfamilyid = (int) $value;
                break;
            case 'MODELID':
                $this->processormodelid = (int) $value;
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
