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
    public const PROJECT = 1;
    public const BUILD = 2;
    public const UPDATE = 3;
    public const CONFIGURE = 4;
    public const TEST = 5;
    public const COVERAGE = 6;
    public const DYNAMICANALYSIS = 7;
    public const USER = 8;
}
