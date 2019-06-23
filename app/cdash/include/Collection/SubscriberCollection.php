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

use CDash\Model\SubscriberInterface;

class SubscriberCollection extends Collection
{
    public function add(SubscriberInterface $subscriber)
    {
        parent::addItem($subscriber, $subscriber->getAddress());
        return $this;
    }
}
