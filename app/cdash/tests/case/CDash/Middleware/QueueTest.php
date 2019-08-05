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

use Bernard\Driver;
use Bernard\Message\PlainMessage;
use CDash\Middleware\Queue;
use CDash\Test\CDashTestCase;

class QueueTest extends CDashTestCase
{
    /**
     * TODO: FlatFileDriver poorly written, save yourself 5 sec every test run, use other driver
     */
    public function testQueue()
    {
        $class = 'IsATestService';

        eval("
            use Bernard\Message;
            class {$class} {
                public function isA(Message \$message) {
                }
            }
        ");

        $mock_service = $this->getMockBuilder($class)
            ->setMethods(['isA'])
            ->getMock();

        $directory = sys_get_temp_dir();
        $driver = new Driver\FlatFileDriver($directory);
        $sut = new Queue($driver);

        $sut->addService('IsA', $mock_service);

        $m1 = new PlainMessage('IsA', ['isA' => true]);
        $m2 = new PlainMessage('IsA', ['isA' => false]);

        $sut->produce($m1);
        $sut->produce($m2);

        // This should be FIFO, but, it's not
        // @see https://github.com/bernardphp/bernard/pull/308
        $mock_service
            ->expects($this->at(0))
            ->method('isA')
            ->with($m2)
            ->willReturnCallback(function ($message) {
                $this->assertFalse($message->isA);
            });

        $mock_service
            ->expects($this->at(1))
            ->method('isA')
            ->with($m1)
            ->willReturnCallback(function ($message) {
                $this->assertTrue($message->isA);
            });

        $sut->consume('is-a', ['max-runtime' => 0.1]);

        // Bug in driver forces us to remove the queue files and directory ourselves
        $deleteTempDir = function ($path) use (&$deleteTempDir) {
            $it = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
            $it = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $file) {
                if ($file->isDir()) {
                    $deleteTempDir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            return rmdir($path);
        };

        $this->assertTrue($deleteTempDir("{$directory}/is-a/"));
    }
}
