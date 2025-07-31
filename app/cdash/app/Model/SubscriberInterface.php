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

namespace CDash\Model;

use App\Http\Submission\Handlers\ActionableBuildInterface;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferencesInterface;
use CDash\Messaging\Topic\TopicCollection;
use Illuminate\Support\Collection;

interface SubscriberInterface
{
    /**
     * SubscriberInterface constructor.
     */
    public function __construct(
        NotificationPreferences $preferences,
        ?TopicCollection $topics = null,
    );

    public function hasBuildTopics(ActionableBuildInterface $build): bool;

    public function getTopics(): TopicCollection;

    public function getAddress(): string;

    public function setAddress($address): static;

    public function getLabels(): Collection;

    public function getNotificationPreferences(): NotificationPreferencesInterface;
}
