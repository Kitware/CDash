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

namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Config;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Model\Build;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;

class BuildErrorDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testSetTopicWithBuildErrorOfTypeError()
    {
        $baseUrl = Config::getInstance()->getBaseUrl();

        $build = new Build();
        $build->Id = 1010;

        $one = new BuildError();
        $two = new BuildError();

        $build->AddError($one);
        $build->AddError($two);

        $two->Type = $one->Type = Build::TYPE_ERROR;

        $one->Text = 'This is build error number 1';
        $two->Text = 'Two, I am Two, build error 2';

        $one->PostContext = 'One: context';
        $two->PostContext = 'Two: context';

        $one->SourceFile = '/path/to/one';
        $two->SourceFile = '/path/to/two';

        $one->SourceLine = '1';
        $two->SourceLine = '2';

        $topic = new BuildErrorTopic();
        $topic->setType(Build::TYPE_ERROR)
            ->addBuild($build);

        $sut = (new BuildErrorDecorator())
            ->setMaxTopicItems(5);

        $expected = implode("\n", [
            '',
            "*Errors*",
            "/path/to/one line 1 ({$baseUrl}/viewBuildError.php?type=0&buildid=1010)",
            'This is build error number 1',
            'One: context',
            "/path/to/two line 2 ({$baseUrl}/viewBuildError.php?type=0&buildid=1010)",
            'Two, I am Two, build error 2',
            'Two: context',
            '',
            '',
        ]);

        $actual = $sut->setTopic($topic);

        $this->assertEquals($expected, $actual);
    }

    public function testSetTopicWithBuildErrorNoSourceFile()
    {
        $build = new Build();
        $build->Id = 1010;

        $one = new BuildError();
        $two = new BuildError();

        $build->AddError($one);
        $build->AddError($two);

        $two->Type = $one->Type = Build::TYPE_ERROR;

        $one->Text = 'This is build error number 1';
        $two->Text = 'Two, I am Two, build error 2';

        $one->PostContext = 'One: context';
        $two->PostContext = 'Two: context';


        $topic = new BuildErrorTopic();
        $topic->setType(Build::TYPE_ERROR)
            ->addBuild($build);

        $sut = (new BuildErrorDecorator())
            ->setMaxTopicItems(5);

        $expected = implode("\n", [
            '',
            "*Errors*",
            'This is build error number 1',
            'One: context',
            'Two, I am Two, build error 2',
            'Two: context',
            '',
            '',
        ]);

        $actual = $sut->setTopic($topic);

        $this->assertEquals($expected, $actual);
    }

    public function testSetTopicWithBuildErrorOfTypeWarning()
    {
        $baseUrl = Config::getInstance()->getBaseUrl();

        $build = new Build();
        $build->Id = 1010;

        $one = new BuildError();
        $two = new BuildError();

        $build->AddError($one);
        $build->AddError($two);

        $two->Type = $one->Type = Build::TYPE_WARN;

        $one->Text = 'This is build warning number 1';
        $two->Text = 'Two, I am Two, build warning 2';

        $one->PostContext = 'One: context';
        $two->PostContext = 'Two: context';

        $one->SourceFile = '/path/to/one';
        $two->SourceFile = '/path/to/two';

        $one->SourceLine = '1';
        $two->SourceLine = '2';

        $topic = new BuildErrorTopic();
        $topic->setType(Build::TYPE_WARN)
            ->addBuild($build);

        $sut = (new BuildErrorDecorator())
            ->setMaxTopicItems(5);

        $expected = implode("\n", [
            '',
            "*Warnings*",
            "/path/to/one line 1 ({$baseUrl}/viewBuildError.php?type=1&buildid=1010)",
            'This is build warning number 1',
            'One: context',
            "/path/to/two line 2 ({$baseUrl}/viewBuildError.php?type=1&buildid=1010)",
            'Two, I am Two, build warning 2',
            'Two: context',
            '',
            '',
        ]);

        $actual = $sut->setTopic($topic);

        $this->assertEquals($expected, $actual);
    }

    public function testSetTopicWithBuildWarningNoSourceFile()
    {
        $build = new Build();
        $build->Id = 1010;

        $one = new BuildError();
        $two = new BuildError();

        $build->AddError($one);
        $build->AddError($two);

        $two->Type = $one->Type = Build::TYPE_WARN;

        $one->Text = 'This is build warning number 1';
        $two->Text = 'Two, I am Two, build warning 2';

        $one->PostContext = 'One: context';
        $two->PostContext = 'Two: context';

        $topic = new BuildErrorTopic();
        $topic->setType(Build::TYPE_WARN)
            ->addBuild($build);

        $sut = (new BuildErrorDecorator())
            ->setMaxTopicItems(5);

        $expected = implode("\n", [
            '',
            "*Warnings*",
            'This is build warning number 1',
            'One: context',
            'Two, I am Two, build warning 2',
            'Two: context',
            '',
            '',
        ]);

        $actual = $sut->setTopic($topic);

        $this->assertEquals($expected, $actual);
    }

    public function testSetTopicWithBuildFailureOfTypeError()
    {
        $baseUrl = Config::getInstance()->getBaseUrl();

        $build = new Build();
        $build->Id = 1010;

        $one = new BuildFailure();
        $two = new BuildFailure();

        $build->AddError($one);
        $build->AddError($two);

        $two->Type = $one->Type = Build::TYPE_ERROR;

        $one->StdError = 'This is build error number 1';
        $two->StdError = 'Two, I am Two, build error 2';

        $one->StdOutput = 'One: context';
        $two->StdOutput = 'Two: context';

        $one->SourceFile = '/path/to/one';
        $two->SourceFile = '/path/to/two';

        $topic = new BuildErrorTopic();
        $topic->setType(Build::TYPE_ERROR)
            ->addBuild($build);

        $sut = (new BuildErrorDecorator())
            ->setMaxTopicItems(5);

        $expected = implode("\n", [
            '',
            "*Errors*",
            "/path/to/one ({$baseUrl}/viewBuildError.php?type=0&buildid=1010)",
            'One: context',
            'This is build error number 1',
            "/path/to/two ({$baseUrl}/viewBuildError.php?type=0&buildid=1010)",
            'Two: context',
            'Two, I am Two, build error 2',
            '',
            '',
        ]);

        $actual = $sut->setTopic($topic);

        $this->assertEquals($expected, $actual);
    }

    public function testSetTopicWithBuildFailureOfTypeWarning()
    {
        $baseUrl = Config::getInstance()->getBaseUrl();

        $build = new Build();
        $build->Id = 1010;

        $one = new BuildFailure();
        $two = new BuildFailure();

        $build->AddError($one);
        $build->AddError($two);

        $two->Type = $one->Type = Build::TYPE_WARN;

        $one->StdError = 'This is build warning number 1';
        $two->StdError = 'Two, I am Two, build warning 2';

        $one->StdOutput = 'One: context';
        $two->StdOutput = 'Two: context';

        $one->SourceFile = '/path/to/one';
        $two->SourceFile = '/path/to/two';

        $topic = new BuildErrorTopic();
        $topic->setType(Build::TYPE_WARN)
            ->addBuild($build);

        $sut = (new BuildErrorDecorator())
            ->setMaxTopicItems(5);

        $expected = implode("\n", [
            '',
            "*Warnings*",
            "/path/to/one ({$baseUrl}/viewBuildError.php?type=1&buildid=1010)",
            'One: context',
            'This is build warning number 1',
            "/path/to/two ({$baseUrl}/viewBuildError.php?type=1&buildid=1010)",
            'Two: context',
            'Two, I am Two, build warning 2',
            '',
            '',
        ]);

        $actual = $sut->setTopic($topic);

        $this->assertEquals($expected, $actual);
    }
}
