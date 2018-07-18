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

namespace CDash\Middleware\Queue;

use Bernard\Message\DefaultMessage;
use CDash\Middleware\Queue;

function do_submit($fh, $project_id, $expected_md5 = '', $do_checksum = true, $submission_id = 0)
{
    SubmissionServiceTest::checkExpectedArguments([
        'do_submit',
        [
            'fh' => $fh,
            'project_id' => $project_id,
            'expected_md5' => $expected_md5,
            'do_checksum' => $do_checksum,
            'submission_id' => $submission_id,
        ]
    ]);
}

function fopen($filename, $mode, $use_include_path = '', $context = '')
{
    SubmissionServiceTest::checkExpectedArguments([
        'fopen',
        [
            'filename' => $filename,
            'mode' => $mode,
            'use_include_path' => $use_include_path,
            'context' => $context
        ]
    ]);
    return $filename;
}

class SubmissionServiceTest extends \PHPUnit_Framework_TestCase
{
    private static $expected_arguments;

    /** @var self $instance */
    private static $instance;

    private $parameters;

    /**
     * We have a two php functions that are called from the doSubmit method of SubmissionService.
     * We can override these functions, even the built-in function, "fopen," via our namespaced
     * test. These overridden functions (whose definitions are above) call a static method on our
     * test which references this instance of this test which we have also set to a static variable
     * precisely for the purpose of being called by this method. Then we simply run the assertions
     * on the statically set instance.
     *
     * @param array $arguments
     */
    public static function checkExpectedArguments(array $arguments)
    {
        $fn = self::$expected_arguments[$arguments[0]];
        foreach ($arguments[1] as $key => $argument) {
            self::$instance->assertEquals($fn[$key], $argument);
        }
    }

    public function setUp()
    {
        parent::setUp();

        self::$expected_arguments = [];
        self::$instance = $this;

        $hash = md5(time());
        $this->parameters = [
            'file' => 'test_file_name',
            'project' => '1100',
            'md5' => $hash,
            'checksum' => true,
        ];
    }

    public function testCreateSubmissionMessage()
    {
        $message = SubmissionService::createMessage($this->parameters);

        $this->assertInstanceOf(DefaultMessage::class, $message);
        $this->assertEquals(SubmissionService::NAME, $message->getName());
        $this->assertEquals($this->parameters['file'], $message->file);
        $this->assertEquals($this->parameters['project'], $message->project);
        $this->assertEquals($this->parameters['checksum'], $message->checksum);
        $this->assertEquals($this->parameters['md5'], $message->md5);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /^Cannot create message: Missing parameters:/
     */
    public function testCreateSubmissionMessageThrowsExceptionWithMissingParameters()
    {
        SubmissionService::createMessage([]);
    }

    public function testGetConsumerName()
    {
        $sut = new SubmissionService();
        $this->assertEquals('do-submit', $sut->getConsumerName());
    }

    public function testDoSubmit()
    {
        $message = SubmissionService::createMessage($this->parameters);
        self::$expected_arguments = [
            'do_submit' => [
                'fh' => $this->parameters['file'],
                'project_id' => $this->parameters['project'],
                'expected_md5' => $this->parameters['md5'],
                'do_checksum' => true,
                'submission_id' => 0,
            ],
            'fopen' => [
                'filename' => $this->parameters['file'],
                'mode' => 'r',
                'use_include_path' => '',
                'context' => ''
            ],
        ];
        $sut = new SubmissionService();
        $sut->doSubmit($message);
    }

    public function testRegister()
    {
        /** @var Queue|\PHPUnit_Framework_MockObject_MockObject $mock_queue */
        $mock_queue = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->setMethods(['addService'])
            ->getMock();
        $sut = new SubmissionService();

        $mock_queue
            ->expects($this->once())
            ->method('addService')
            ->with(SubmissionService::NAME, $sut);

        $sut->register($mock_queue);
    }

    public function testServiceAcceptsAlternateQueueName()
    {
        $queue_name = 'DrakeCdash';
        $this->parameters['queue_name'] = $queue_name;
        self::$expected_arguments = [
            'do_submit' => [
                'fh' => $this->parameters['file'],
                'project_id' => $this->parameters['project'],
                'expected_md5' => $this->parameters['md5'],
                'do_checksum' => true,
                'submission_id' => 0,
            ],
            'fopen' => [
                'filename' => $this->parameters['file'],
                'mode' => 'r',
                'use_include_path' => '',
                'context' => ''
            ],
        ];
        $sut = new SubmissionService($queue_name);
        $message = SubmissionService::createMessage($this->parameters);

        $this->assertEquals($queue_name, $message->getName());
        $sut->drakeCdash($message);
    }
}
