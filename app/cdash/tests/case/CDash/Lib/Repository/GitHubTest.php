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

use CDash\Config;
use CDash\Lib\Repository\GitHub;
use CDash\Model\Project;
use GuzzleHttp\ClientInterface;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class GitHubTest extends TestCase
{
    private $baseUrl;
    private $project;
    public function setUp() : void
    {
        parent::setUp();
        $this->project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->baseUrl = Config::getInstance()->getBaseUrl();
    }

    public function testSetStatus()
    {
        $sut = $this->setupAuthentication();
        $options = [
            'commit_hash' => str_replace('-', '', Uuid::uuid4()->toString()),
            'context' => 'CDash by Kitware',
            'description' => 'Build suchnsuch from site sonso',
            'state' => 'pending',
            'target_url' => "{$this->baseUrl}/build/1010",
        ];
        $sut->setStatus($options);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Unable to find installation ID for repository
     */
    public function testAuthenticateThrowsExceptionGivenNoInstallationId()
    {
        $this->project->expects($this->once())
            ->method('GetRepositories')
            ->willReturn([]);
        $sut = new GitHub($this->project);
        $sut->authenticate();
    }

    public function testSkipCheckPropertyIsHonored()
    {
        $this->project->expects($this->once())
            ->method('GetRepositories')
            ->willReturn([]);
        $sut = new GitHub($this->project);

        $build_row = ['properties' => '{ "skip checks": 1}'];
        $this->assertNull($sut->getCheckSummaryForBuildRow($build_row));
    }

    public function testCheckSummaryForBuildRow()
    {
        $this->project->expects($this->once())
            ->method('GetRepositories')
            ->willReturn([]);
        $sut = new GitHub($this->project);

        $build_row = [
            'name' => 'my build',
            'id' => 99999,
            'properties' => '',
            'configureerrors' => 1,
        ];
        $summary_url = "$this->baseUrl/build/99999";
        $common = "[my build]($summary_url) | ";

        // Single configure error.
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $link = "$this->baseUrl/build/99999/configure";
        $expected = $common . ":x: | [1 configure error]($link)";
        $this->assertEquals($expected, $actual);

        // Plural configure errors.
        $build_row['configureerrors'] = 2;
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $expected = $common . ":x: | [2 configure errors]($link)";
        $this->assertEquals($expected, $actual);
        $build_row['configureerrors'] = 0;

        // Single build error.
        $build_row['builderrors'] = 1;
        $link = "$this->baseUrl/viewBuildError.php?buildid=99999";
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $expected = $common . ":x: | [1 build error]($link)";
        $this->assertEquals($expected, $actual);

        // Plural build errors.
        $build_row['builderrors'] = 2;
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $expected = $common . ":x: | [2 build errors]($link)";
        $this->assertEquals($expected, $actual);
        $build_row['builderrors'] = 0;

        // Single test failure.
        $build_row['testfailed'] = 1;
        $link = "$this->baseUrl/viewTest.php?buildid=99999";
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $expected = $common . ":x: | [1 failed test]($link)";
        $this->assertEquals($expected, $actual);

        // Plural test failures.
        $build_row['testfailed'] = 2;
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $expected = $common . ":x: | [2 failed tests]($link)";
        $this->assertEquals($expected, $actual);
        $build_row['testfailed'] = 0;

        // Pending.
        $build_row['done'] = 0;
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $expected = $common . ":hourglass_flowing_sand: | [pending]($summary_url)";
        $this->assertEquals($expected, $actual);

        // Success.
        $build_row['done'] = 1;
        $actual = $sut->getCheckSummaryForBuildRow($build_row);
        $expected = $common . ":white_check_mark: | [success]($summary_url)";
        $this->assertEquals($expected, $actual);
    }

    public function testGenerateCheckPayloadFromBuildRows()
    {
        $this->project->expects($this->once())
            ->method('GetRepositories')
            ->willReturn([]);
        $this->project->Name = 'TestChecksProject';
        $sut = new GitHub($this->project);

        $index_url = "$this->baseUrl/index.php?project=TestChecksProject&filtercount=1&showfilters=1&field1=revision&compare1=61&value1=zzz";

        // No builds.
        $expected = [
            'name' => 'CDash',
            'head_sha' => 'zzz',
            'details_url' => $index_url,
            'status' => 'in_progress'
        ];
        $expected['output'] = [
            'title' => 'Awaiting results',
            'summary' => "[CDash has not parsed any results for this check yet.]($index_url)"
        ];
        $build_rows = [];
        $actual = $sut->generateCheckPayloadFromBuildRows($build_rows, 'zzz');
        unset($actual['started_at']);
        $this->assertEquals($expected, $actual);

        // Pending check.
        $table_header = "Build Name | Status | Details\n";
        $table_header .= ":-: | :-: | :-:";
        $expected['output']['title'] = 'Pending';
        $expected['output']['summary'] = "[Some builds have not yet finished submitting their results to CDash.]($index_url)";
        $expected['output']['text'] = "$table_header\n[a]($this->baseUrl/build/99995) | :hourglass_flowing_sand: | [pending]($this->baseUrl/build/99995)";

        $build_row = [
            'name' => 'a',
            'id' => 99995,
            'properties' => '',
            'configureerrors' => 0,
            'builderrors' => 0,
            'testfailed' => 0,
            'done' => 0
        ];
        $build_rows[] = $build_row;
        $actual = $sut->generateCheckPayloadFromBuildRows($build_rows, 'zzz');
        unset($actual['started_at']);
        $this->assertEquals($expected, $actual);

        // Successful check.
        $expected['status'] = 'completed';
        $expected['conclusion'] = 'success';
        $expected['output']['title'] = 'Success';
        $expected['output']['summary'] = "[All builds completed successfully :shipit:]($index_url)";
        $expected['output']['text'] = "$table_header\n[a]($this->baseUrl/build/99995) | :white_check_mark: | [success]($this->baseUrl/build/99995)";
        $build_rows[0]['done'] = 1;
        $actual = $sut->generateCheckPayloadFromBuildRows($build_rows, 'zzz');
        unset($actual['started_at']);
        unset($actual['completed_at']);
        $this->assertEquals($expected, $actual);

        // Error check.
        $expected['conclusion'] = 'failure';
        $expected['output']['title'] = 'Failure';
        $expected['output']['summary'] = "[CDash detected configure errors, build errors and failed tests.]($index_url)";
        $expected['output']['text'] = "$table_header\n";
        $expected['output']['text'] .= "[a]($this->baseUrl/build/99995) | :white_check_mark: | [success]($this->baseUrl/build/99995)\n";
        $expected['output']['text'] .= "[b]($this->baseUrl/build/99996) | :x: | [5 configure errors]($this->baseUrl/build/99996/configure)\n";
        $expected['output']['text'] .= "[c]($this->baseUrl/build/99997) | :x: | [1 build error]($this->baseUrl/viewBuildError.php?buildid=99997)\n";
        $expected['output']['text'] .= "[d]($this->baseUrl/build/99998) | :x: | [7 failed tests]($this->baseUrl/viewTest.php?buildid=99998)";
        $build_rows[] = [
            'name' => 'b',
            'id' => 99996,
            'properties' => '',
            'configureerrors' => 5,
            'builderrors' => 0,
            'testfailed' => 0,
            'done' => 1
        ];
        $build_rows[] = [
            'name' => 'c',
            'id' => 99997,
            'properties' => '',
            'configureerrors' => 0,
            'builderrors' => 1,
            'testfailed' => 0,
            'done' => 1
        ];
        $build_rows[] = [
            'name' => 'd',
            'id' => 99998,
            'properties' => '',
            'configureerrors' => 0,
            'builderrors' => 0,
            'testfailed' => 7,
            'done' => 1
        ];
        $actual = $sut->generateCheckPayloadFromBuildRows($build_rows, 'zzz');
        unset($actual['started_at']);
        unset($actual['completed_at']);
        $this->assertEquals($expected, $actual);
    }

    public function testDedupeAndSortBuildRows()
    {
        $this->project->expects($this->once())
            ->method('GetRepositories')
            ->willReturn([]);
        $sut = new GitHub($this->project);

        $rows = [
            ['id' => 1, 'name' => 'c', 'starttime' => '2019-05-01 18:08:35'],
            ['id' => 2, 'name' => 'a', 'starttime' => '2019-05-01 18:08:36'],
            ['id' => 3, 'name' => 'a', 'starttime' => '2019-05-01 18:08:37'],
            ['id' => 4, 'name' => 'a', 'starttime' => '2019-05-01 18:08:38'],
            ['id' => 5, 'name' => 'b', 'starttime' => '2019-05-01 18:08:39'],
            ['id' => 6, 'name' => 'b', 'starttime' => '2019-05-01 18:08:40'],
        ];
        $actual = $sut->dedupeAndSortBuildRows($rows);
        $expected = [
            ['id' => 4, 'name' => 'a', 'starttime' => '2019-05-01 18:08:38'],
            ['id' => 6, 'name' => 'b', 'starttime' => '2019-05-01 18:08:40'],
            ['id' => 1, 'name' => 'c', 'starttime' => '2019-05-01 18:08:35']
        ];
        $this->assertEquals($expected, $actual);
    }

    private function setupAuthentication()
    {
        $github_url = 'https://github.com/Foo/Bar';
        $repositories = [];
        $repositories[] = [
            'url'      => $github_url,
            'username' => 12345
        ];
        $this->project->CvsUrl = $github_url;
        $this->project->expects($this->once())
            ->method('GetRepositories')
            ->willReturn($repositories);

        $sut = new GitHub($this->project);

        $builder = $this->getMockBuilder(\Lcobucci\JWT\Builder::class)
            ->disableOriginalConstructor()
            ->setMethods(['setIssuer', 'setIssuedAt', 'setExpiration', 'sign',
                          'getToken'])
            ->getMock();
        $builder->expects($this->once())
            ->method('setIssuer')
            ->will($this->returnSelf());
        $builder->expects($this->once())
            ->method('setIssuedAt')
            ->will($this->returnSelf());
        $builder->expects($this->once())
            ->method('setExpiration')
            ->will($this->returnSelf());
        $builder->expects($this->once())
            ->method('sign')
            ->will($this->returnSelf());
        $builder->expects($this->once())
            ->method('getToken');
        $sut->setJwtBuilder($builder);

        $statuses = $this->getMockBuilder(\Github\Api\Apps::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $statuses->expects($this->any())
            ->method('create')
            ->willReturn(true);

        $api = $this->getMockBuilder(\Github\Api\Apps::class)
            ->disableOriginalConstructor()
            ->setMethods(['createInstallationToken', 'statuses'])
            ->getMock();
        $api->expects($this->any())
            ->method('createInstallationToken');
        $api->expects($this->any())
            ->method('statuses')
            ->willReturn($statuses);

        $client = $this->getMockBuilder(\Github\Client::class)
            ->disableOriginalConstructor()
            ->setMethods(['api', 'authenticate', 'getHttpClient'])
            ->getMock();
        $client->expects($this->any())
            ->method('authenticate')
            ->willReturn(true);
        $client->expects($this->any())
            ->method('api')
            ->willReturn($api);

        $sut->setApiClient($client);
        Config::getInstance()->set('CDASH_GITHUB_PRIVATE_KEY', __FILE__);
        config(['cdash.github_app_id' => 12345]);

        return $sut;
    }

    public function testCreateCheck()
    {
        $sut = $this->setupAuthentication();
        $check = $this->getMockBuilder(\CDash\Lib\Repository\Check::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $sut->setCheck($check);
        $sut->createCheck(str_replace('-', '', Uuid::uuid4()->toString()));
    }
}
