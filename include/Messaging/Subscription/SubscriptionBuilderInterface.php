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

namespace CDash\Messaging\Subscription;

use ActionableBuildInterface;

interface SubscriptionBuilderInterface
{
    /**
     * @param ActionableBuildInterface $submission
     * @return void
     */
    public function __construct(ActionableBuildInterface $submission);

    /**
     * @param SubscriptionCollection $subscriptions
     * @return void
     */
    public function build(SubscriptionCollection $subscriptions);
}
