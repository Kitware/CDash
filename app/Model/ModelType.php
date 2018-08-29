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

class ModelType
{
    const PROJECT = 1;
    const BUILD = 2;
    const UPDATE = 3;
    const CONFIGURE = 4;
    const TEST = 5;
    const COVERAGE = 6;
    const DYNAMICANALYSIS = 7;
    const USER = 8;
}
