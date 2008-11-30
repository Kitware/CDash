<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
// It is assumed that appropriate headers should be included before including this file
class SiteInformation
{
  var $Timestamp;
  var $ProcessorIs64Bits;
  var $ProcessorVendor;
  var $ProcessorVendorId;
  var $ProcessorFamilyId;
  var $ProcessorModelId;
  var $ProcessorCacheSize;
  var $NumberLogicalCpus;
  var $NumberPhysicalCpus;
  var $TotalVirtualMemory;
  var $TotalPhysicalMemory;
  var $LogicalProcessorsPerPhysical;
  var $ProcessorClockFrequency;
  var $Description;
  var $SiteId;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "NAME": $this->Name = $data;break;
      case "DESCRIPTION": $this->Description = $data;break;
      case "PROCESSORIS64BITS": $this->ProcessorIs64Bits = $data;break;
      case "PROCESSORVENDOR": $this->ProcessorVendor = $data;break;
      case "PROCESSORVENDORID": $this->ProcessorVendorId = $data;break;
      case "PROCESSORFAMILYID": $this->ProcessorFamilyId = $data;break;
      case "PROCESSORMODELID": $this->ProcessorModelId = $data;break;
      case "PROCESSORCACHESIZE": $this->ProcessorCacheSize = $data;break;
      case "NUMBERLOGICALCPUS": $this->NumberLogicalCpus = $data;break;
      case "NUMBERPHYSICALCPUS": $this->NumberPhysicalCpus = $data;break;
      case "TOTALVIRTUALMEMORY": $this->TotalVirtualMemory = $data;break;
      case "TOTALPHYSICALMEMORY": $this->TotalPhysicalMemory = $data;break;
      case "LOGICALPROCESSORSPERPHYSICAL": $this->LogicalProcessorsPerPhysical = $data;break;
      case "PROCESSORCLOCKFREQUENCY": $this->ProcessorClockFrequency = $data;break;
      }
    }
    
  /** Check if the site already exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->SiteId)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM siteinformation WHERE siteid='".$this->SiteId."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    return true;  
    }
    
  /** Save the site information */
  function Save()
    {
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE siteinformation SET";
      $query .= " timestamp='".$this->TimeStamp."'";
      $query .= ",processoris64bits='".$this->ProcessorIs64Bits."'";
      $query .= ",processorvendor='".$this->ProcessorVendor."'";
      $query .= ",processorvendorid='".$this->ProcessorVendorId."'";
      $query .= ",processorfamilyid='".$this->ProcessorFamilyId."'";
      $query .= ",processormodelid='".$this->ProcessorModelId."'";
      $query .= ",processorcachesize='".$this->ProcessorCacheSize."'";
      $query .= ",numberlogicalcpus='".$this->NumberLogicalCpus."'";
      $query .= ",numberphysicalcpus='".$this->NumberPhysicalCpus."'";
      $query .= ",totalvirtualmemory='".$this->TotalVirtualMemory."'";
      $query .= ",totalphysicalmemory='".$this->TotalPhysicalMemory."'";
      $query .= ",logicalprocessorsperphysical='".$this->LogicalProcessorsPerPhysical."'";
      $query .= ",processorclockfrequency='".$this->ProcessorClockFrequency."'";
      $query .= ",description='".$this->Description."'";
      $query .= " WHERE siteid='".$this->SiteId."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("SiteInformation Update");
        return false;
        }
      }
    else
      {                                              
      if(!pdo_query("INSERT INTO siteinformation (siteid,timestamp,
                     processoris64bits,processorvendor,processorvendorid,
                     processorfamilyid,processormodelid,processorcachesize,
                     numberlogicalcpus,numberphysicalcpus,totalvirtualmemory,
                     totalphysicalmemory,logicalprocessorsperphysical,processorclockfrequency,
                     description
                     )
                     VALUES ('$this->SiteId','$this->TimeStamp','$this->ProcessorIs64Bits',
                     '$this->ProcessorVendor','$this->ProcessorVendorId',
                     '$this->ProcessorFamilyId','$this->ProcessorModelId',
                     '$this->ProcessorCacheSize','$this->NumberLogicalCpus',
                     '$this->NumberPhysicalCpus','$this->TotalVirtualMemory',
                     '$this->TotalPhysicalMemory','$this->LogicalProcessorsPerPhysical',
                     '$this->LogicalProcessorsPerPhysical','$this->ProcessorClockFrequency',
                     '$this->Description'
                     )"))
         {
         add_last_sql_error("SiteInformation Insert");
         return false;
         }
      }  
    } // end function save  
}

class Site
{
  var $Id;
  var $Name;
  var $Ip;
  var $Latitude;
  var $Longitude;
    
  function SetInformation($information)
    {
    $information->SiteId = $this->Id;
    $information->Save();
    }
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "ID": $this->Id = $value;break;
      case "NAME": $this->Name = $value;break;
      case "IP": $this->Ip = $value;break;
      case "LATITUDE": $this->Latitude = $value;break;
      case "LONGITUDE": $this->Longitude = $value;break;
      }
    }
    
  /** Check if the site already exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id || !$this->Name)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM site WHERE id='".$this->Id."' AND name='".$this->Name."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    return true;  
    }
    
  /** Save the site */
  function Save()
    {
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE site SET";
      $query .= " name='".$this->Name."'";
      $query .= ",ip='".$this->Ip."'";
      $query .= ",latitude='".$this->Latitude."'";
      $query .= ",longitude='".$this->Longitude."'";
      $query .= " WHERE id='".$this->Id."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("Site Update");
        return false;
        }
      }
    else
      {                                              
      if(pdo_query("INSERT INTO site (name,ip,latitude,longitude)
                     VALUES ('$this->Name','$this->Ip','$this->Latitude','$this->Longitude')"))
         {
         $this->Id = pdo_insert_id("site");
         }
       else
         {
         add_last_sql_error("Site Insert");
         return false;
         }
      }  
    } // end function save  
}

?>
