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

use CDash\Messaging\Notification\Email\Decorator\Decorator;

class DecoratorTest extends PHPUnit_Framework_TestCase
{
    public function test__toString()
    {
        $body = new TestBodyDecorator();
        /** @var Decorator $sut */
        $sut = $this->getMockForAbstractClass(Decorator::class, [$body]);
        $expected = 'way __tooString';
        $actual = "{$sut}";
        $this->assertEquals($expected, $actual);
    }

    public function testSetMaxTopicItems()
    {
        $max = 5;
        $body = new TestBodyDecorator();
        /** @var Decorator $sut */
        $sut = $this->getMockForAbstractClass(Decorator::class, [$body]);
        $this->assertSame($sut, $sut->setMaxTopicItems($max));
    }

    public function testSetMaxChars()
    {
        $max = 70;
        $body = new TestBodyDecorator();
        /** @var Decorator $sut */
        $sut = $this->getMockForAbstractClass(Decorator::class, [$body]);
        $this->assertSame($sut, $sut->setMaxChars($max));
    }

    public function testDecorateWith()
    {
        $body = new TestBodyDecorator();
        /** @var Decorator $sut */
        $sut = $this->getMockForAbstractClass(Decorator::class, [$body]);

        // @courtesy http://preshing.com/20110811/xkcd-password-generator/
        $template = "{{ double }}, {{ tomorrow }}: {{ warm }}\npopular {{ popular }}";
        $data = [
            'double' => 'x 2',
            'tomorrow' => 'weekend',
            'warm' => '69 F',
            'popular' => 'https://youtu.be/hAFuD-S-e_E',
        ];

        $expected = "x 2, weekend: 69 F\npopular https://youtu.be/hAFuD-S-e_E";
        $actual = $sut->decorateWith($template, $data);
        $this->assertEquals($expected, $actual);
    }
}

class TestBodyDecorator extends Decorator
{
    public function __toString()
    {
        return 'way __tooString';
    }

    /**
     * @param \CDash\Messaging\Topic\Topic $topic
     * @return string|void
     */
    public function setTopic(\CDash\Messaging\Topic\Topic $topic)
    {
        // TODO: Implement setTopic() method.
    }
}
