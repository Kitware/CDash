<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Collection;

use CDash\Model\BuildEmail;

class BuildEmailCollection extends Collection
{
    public function add(BuildEmail $buildEmail)
    {
        $email = $buildEmail->GetEmail();
        if (in_array($email, $this->keys)) {
            array_push($this->collection[$email], $buildEmail);
        } else {
            parent::addItem([$buildEmail], $email);
        }
        return $this;
    }
}
