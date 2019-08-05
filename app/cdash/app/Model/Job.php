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

class Job
{
    const SCHEDULED = 0;
    const RUNNING = 2;
    const FINISHED = 3;
    const ABORTED = 4;
    const FAILED = 5;

    const EXPERIMENTAL = 0;
    const NIGHTLY = 1;
    const CONTINOUS = 2;
}
