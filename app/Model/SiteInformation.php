<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/
namespace CDash\Model;

// It is assumed that appropriate headers should be included before including this file
class SiteInformation
{
    public $TimeStamp;
    public $ProcessorIs64Bits;
    public $ProcessorVendor;
    public $ProcessorVendorId;
    public $ProcessorFamilyId;
    public $ProcessorModelId;
    public $ProcessorCacheSize;
    public $NumberLogicalCpus;
    public $NumberPhysicalCpus;
    public $TotalVirtualMemory;
    public $TotalPhysicalMemory;
    public $LogicalProcessorsPerPhysical;
    public $ProcessorClockFrequency;
    public $Description;
    public $SiteId;

    /** Constructor */
    public function __construct()
    {
        $this->TimeStamp = '1980-01-01 00:00:00';
        $this->ProcessorIs64Bits = -1;
        $this->ProcessorVendor = -1;
        $this->ProcessorVendorId = -1;
        $this->ProcessorFamilyId = -1;
        $this->ProcessorModelId = -1;
        $this->ProcessorCacheSize = -1;
        $this->NumberLogicalCpus = -1;
        $this->NumberPhysicalCpus = -1;
        $this->TotalVirtualMemory = -1;
        $this->TotalPhysicalMemory = -1;
        $this->LogicalProcessorsPerPhysical = -1;
        $this->ProcessorClockFrequency = -1;
        $this->Description = '';
        $this->SiteId = 0;
    }

    public function SetValue($tag, $value)
    {
        switch ($tag) {
            case 'DESCRIPTION':
                $this->Description = $value;
                break;
            case 'IS64BITS':
                $this->ProcessorIs64Bits = $value;
                break;
            case 'VENDORSTRING':
                $this->ProcessorVendor = $value;
                break;
            case 'VENDORID':
                $this->ProcessorVendorId = $value;
                break;
            case 'FAMILYID':
                $this->ProcessorFamilyId = $value;
                break;
            case 'MODELID':
                $this->ProcessorModelId = $value;
                break;
            case 'PROCESSORCACHESIZE':
                $this->ProcessorCacheSize = $value;
                break;
            case 'NUMBEROFLOGICALCPU':
                $this->NumberLogicalCpus = $value;
                break;
            case 'NUMBEROFPHYSICALCPU':
                $this->NumberPhysicalCpus = $value;
                break;
            case 'TOTALVIRTUALMEMORY':
                $this->TotalVirtualMemory = $value;
                break;
            case 'TOTALPHYSICALMEMORY':
                $this->TotalPhysicalMemory = $value;
                break;
            case 'LOGICALPROCESSORSPERPHYSICAL':
                $this->LogicalProcessorsPerPhysical = $value;
                break;
            case 'PROCESSORCLOCKFREQUENCY':
                $this->ProcessorClockFrequency = $value;
                break;
        }
    }

    /** Check if the site already exists */
    public function Exists()
    {
        // If no id specify return false
        if (!$this->SiteId) {
            return false;
        }

        $query = pdo_query("SELECT count(*) FROM siteinformation WHERE siteid='" . $this->SiteId . "'");
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] == 0) {
            return false;
        }
        return true;
    }

    /** Save the site information */
    public function Save()
    {
        if ($this->Exists()) {
            // Update the project
            $query = 'UPDATE siteinformation SET';
            $query .= " timestamp='" . $this->TimeStamp . "'";
            $query .= ",processoris64bits='" . $this->ProcessorIs64Bits . "'";
            $query .= ",processorvendor='" . $this->ProcessorVendor . "'";
            $query .= ",processorvendorid='" . $this->ProcessorVendorId . "'";
            $query .= ",processorfamilyid='" . $this->ProcessorFamilyId . "'";
            $query .= ",processormodelid='" . $this->ProcessorModelId . "'";
            $query .= ",processorcachesize='" . round($this->ProcessorCacheSize) . "'";
            $query .= ",numberlogicalcpus='" . round($this->NumberLogicalCpus) . "'";
            $query .= ",numberphysicalcpus='" . round($this->NumberPhysicalCpus) . "'";
            $query .= ",totalvirtualmemory='" . round($this->TotalVirtualMemory) . "'";
            $query .= ",totalphysicalmemory='" . round($this->TotalPhysicalMemory) . "'";
            $query .= ",logicalprocessorsperphysical='" . round($this->LogicalProcessorsPerPhysical) . "'";
            $query .= ",processorclockfrequency='" . round($this->ProcessorClockFrequency) . "'";
            $query .= ",description='" . $this->Description . "'";
            $query .= " WHERE siteid='" . $this->SiteId . "'";

            if (!pdo_query($query)) {
                add_last_sql_error('SiteInformation Update');
                return false;
            }
        } else {
            if (!pdo_query('INSERT INTO siteinformation (siteid,timestamp,
                     processoris64bits,processorvendor,processorvendorid,
                     processorfamilyid,processormodelid,processorcachesize,
                     numberlogicalcpus,numberphysicalcpus,totalvirtualmemory,
                     totalphysicalmemory,logicalprocessorsperphysical,processorclockfrequency,
                     description
                     )
                     VALUES (' . qnum($this->SiteId) . ",'$this->TimeStamp','$this->ProcessorIs64Bits',
                     '$this->ProcessorVendor','$this->ProcessorVendorId',
                     " . qnum($this->ProcessorFamilyId) . ',' . qnum($this->ProcessorModelId) . ',
                     ' . qnum(round($this->ProcessorCacheSize)) . ',' . qnum(round($this->NumberLogicalCpus)) . ',
                     ' . qnum(round($this->NumberPhysicalCpus)) . ',' . qnum(round($this->TotalVirtualMemory)) . ',
                     ' . qnum(round($this->TotalPhysicalMemory)) . ',' . qnum(round($this->LogicalProcessorsPerPhysical)) . ',
                      ' . qnum(round($this->ProcessorClockFrequency)) . ",'$this->Description'
                     )")
            ) {
                add_last_sql_error('SiteInformation Insert');
                return false;
            }
        }
    }
}
