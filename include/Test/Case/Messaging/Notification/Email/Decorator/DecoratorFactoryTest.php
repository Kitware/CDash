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

use CDash\Collection\TestCollection;
use CDash\Messaging\Notification\Email\Decorator\BuildErrorDecorator;
use CDash\Messaging\Notification\Email\Decorator\ConfigureDecorator;
use CDash\Messaging\Notification\Email\Decorator\Decorator;
use CDash\Messaging\Notification\Email\Decorator\DecoratorFactory;
use CDash\Messaging\Notification\Email\Decorator\DynamicAnalysisDecorator;
use CDash\Messaging\Notification\Email\Decorator\LabeledDecorator;
use CDash\Messaging\Notification\Email\Decorator\TestFailureDecorator;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Messaging\Topic\ConfigureTopic;
use CDash\Messaging\Topic\DynamicAnalysisTopic;
use CDash\Messaging\Topic\LabeledTopic;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Model\Build;

class DecoratorFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testCreateFromCollection()
    {
        /** @var Decorator $mock_decorator */
        $mock_decorator = $this->getMockForAbstractClass(Decorator::class);

        $decorator = DecoratorFactory::createFromTopic(new TestFailureTopic(), $mock_decorator);
        $this->assertInstanceOf(TestFailureDecorator::class, $decorator);

        $decorator = DecoratorFactory::createFromTopic(new ConfigureTopic(), $mock_decorator);
        $this->assertInstanceOf(ConfigureDecorator::class, $decorator);

        $decorator = DecoratorFactory::createFromTopic(new LabeledTopic(), $mock_decorator);
        $this->assertInstanceOf(LabeledDecorator::class, $decorator);

        $buildErrorTopic = new BuildErrorTopic();
        $buildErrorTopic->setType(Build::TYPE_ERROR);
        $this->assertEquals('BuildError', $buildErrorTopic->getTopicName());
        $decorator = DecoratorFactory::createFromTopic($buildErrorTopic, $mock_decorator);
        $this->assertInstanceOf(BuildErrorDecorator::class, $decorator);

        $buildErrorTopic->setType(Build::TYPE_WARN);
        $this->assertEquals('BuildWarning', $buildErrorTopic->getTopicName());
        $decorator = DecoratorFactory::createFromTopic($buildErrorTopic, $mock_decorator);
        $this->assertInstanceOf(BuildErrorDecorator::class, $decorator);

        $decorator = DecoratorFactory::createFromTopic(new DynamicAnalysisTopic(), $mock_decorator);
        $this->assertInstanceOf(DynamicAnalysisDecorator::class, $decorator);
    }

    public function testCreateFromTopic()
    {
        /** @var Decorator $mock_decorator */
        $mock_decorator = $this->getMockForAbstractClass(Decorator::class);

        $decorator = DecoratorFactory::createFromCollection(new TestCollection(), $mock_decorator);
        $this->assertInstanceOf(TestFailureDecorator::class, $decorator);
    }
}
