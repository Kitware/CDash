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
    /**
     * @return $this
     */
    public function add(BuildEmail $buildEmail): static
    {
        $email = $buildEmail->GetEmail();
        if (in_array($email, $this->keys)) {
            array_push($this->collection[$email], $buildEmail);
        } else {
            $this->addItem([$buildEmail], $email);
        }
        return $this;
    }

    public function sortByCategory(): CollectionCollection
    {
        $collection = new CollectionCollection();

        foreach ($this->collection as $key => $emails) {
            /** @var BuildEmail $email */
            foreach ($emails as $email) {
                $category = $email->GetCategory();
                if ($collection->has($category)) {
                    $sub = $collection->get($category);
                    $sub->add($email);
                } else {
                    $sub = new BuildEmailCollection();
                    $sub->add($email);
                    $collection->addItem($sub, $category);
                }
            }
        }

        return $collection;
    }
}
