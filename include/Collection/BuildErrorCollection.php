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

namespace CDash\Collection;

class BuildErrorCollection extends Collection
{
    /**
     * TODO: Find way to type-hint argument
     * Normally we would type-hint this argument but the strangeness of
     * BuildError and BuildFailure both being equally valid in terms of
     * the Build::Errors property prevents us from doing so.
     *
     * @param $error
     * @return $this
     */
    public function add($error)
    {
        parent::addItem($error);
        return $this;
    }
}
