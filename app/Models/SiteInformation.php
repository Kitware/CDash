<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteInformation extends Model
{
    protected $table = 'siteinformation';

//    const CREATED_AT = 'timestamp';
    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'timestamp' => '1980-01-01 00:00:00',
        'processoris64bits' => -1,
        'processorvendor' => -1,
        'processorvendorid' => -1,
        'processorfamilyid' => -1,
        'processormodelid' => -1,
        'processorcachesize' => -1,
        'numberlogicalcpus' => -1,
        'numberphysicalcpus' => -1,
        'totalvirtualmemory' => -1,
        'totalphysicalmemory' => -1,
        'logicalprocessorsperphysical' => -1,
        'processorclockfrequency' => -1,
        'description' => '',
        'siteid' => 0,
    ];

    public function save(array $options = [])
    {
        $this->processorcachesize = round($this->processorcachesize);
        $this->numberlogicalcpus = round($this->numberlogicalcpus);
        $this->numberphysicalcpus = round($this->numberphysicalcpus);
        $this->totalphysicalmemory = round($this->totalphysicalmemory);
        $this->logicalprocessorsperphysical = round($this->logicalprocessorsperphysical);
        $this->processorclockfrequency = round($this->processorclockfrequency);

        parent::save($options);
    }

    public function SetValue(string $tag, string|int $value): void
    {
        switch ($tag) {
            case 'DESCRIPTION':
                $this->description = $value;
                break;
            case 'IS64BITS':
                $this->processoris64bits = $value;
                break;
            case 'VENDORSTRING':
                $this->processorvendor = $value;
                break;
            case 'VENDORID':
                $this->processorvendorid = $value;
                break;
            case 'FAMILYID':
                $this->processorfamilyid = $value;
                break;
            case 'MODELID':
                $this->processormodelid = $value;
                break;
            case 'PROCESSORCACHESIZE':
                $this->processorcachesize = $value;
                break;
            case 'NUMBEROFLOGICALCPU':
                $this->numberlogicalcpus = $value;
                break;
            case 'NUMBEROFPHYSICALCPU':
                $this->numberphysicalcpus = $value;
                break;
            case 'TOTALVIRTUALMEMORY':
                $this->totalvirtualmemory = $value;
                break;
            case 'TOTALPHYSICALMEMORY':
                $this->totalphysicalmemory = $value;
                break;
            case 'LOGICALPROCESSORSPERPHYSICAL':
                $this->logicalprocessorsperphysical = $value;
                break;
            case 'PROCESSORCLOCKFREQUENCY':
                $this->processorclockfrequency = $value;
                break;
        }
    }
}
