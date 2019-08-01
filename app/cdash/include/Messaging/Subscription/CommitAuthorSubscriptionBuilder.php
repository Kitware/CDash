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
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Model\Subscriber;

/**
 * Class CommitAuthorSubscriptionBuilder
 * @package CDash\Messaging\Subscription
 */
class CommitAuthorSubscriptionBuilder implements SubscriptionBuilderInterface
{
    /** @var SubscriptionFactory $factory */
    protected $factory;

    /** @var ActionableBuildInterface $submission */
    protected $submission;

    /**
     * SubscriptionBuilder constructor.
     * @param ActionableBuildInterface $submission
     * TODO: PHP 7.4 (finally) has covariant and contravariant parameters: this parameter should be
     *   the contravariant CommitAuthorHandlerInterface
     *   @see https://wiki.php.net/rfc/covariant-returns-and-contravariant-parameters
     */
    public function __construct(ActionableBuildInterface $submission)
    {
        $this->submission = $submission;
    }

    /**
     * @param SubscriptionCollection $subscriptions
     * @return void
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function build(SubscriptionCollection $subscriptions)
    {
        $group = $this->submission->GetBuildGroup();
        if (!$group->isNotifyingCommitters()) {
            return;
        }

        $project = $this->submission->GetProject();
        $site = $this->submission->GetSite();
        $authors = $this->submission->GetCommitAuthors();
        $factory = $this->getSubscriptionFactory();
        $buildGroup = $this->submission->GetBuildGroup();

        foreach ($authors as $author) {
            $preferences = (new BitmaskNotificationPreferences())
                ->set(NotifyOn::TEST_FAILURE, true)
                ->set(NotifyOn::BUILD_ERROR, true)
                ->set(NotifyOn::AUTHORED, true);

            $subscriber = (new Subscriber($preferences))
                ->setAddress($author);

            if ($subscriber->hasBuildTopics($this->submission)) {
                $subscription = $factory->create();
                $subscription
                    ->setSubscriber($subscriber)
                    ->setProject($project)
                    ->setSite($site)
                    ->setBuildGroup($buildGroup);

                $subscriptions->add($subscription);
            }
        }
    }

    public function getSubscriptionFactory()
    {
        if (!$this->factory) {
            $this->factory = new SubscriptionFactory();
        }
        return $this->factory;
    }
}
