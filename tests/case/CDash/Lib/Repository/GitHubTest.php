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
use GuzzleHttp\ClientInterface;
use Ramsey\Uuid\Uuid;

class GitHubTest extends PHPUnit_Framework_TestCase
{
    public function testSetStatus()
    {
        $base_url = Config::getInstance()->getBaseUrl();

        $token = Uuid::uuid4()->toString();
        $owner = 'Foo';
        $repo = 'Bar';
        $hash = str_replace('-', '', Uuid::uuid4()->toString());
        $sut = new GitHub($token, $owner, $repo);

        $options = [
            'commit_hash' => $hash,
            'context' => 'CDash by Kitware',
            'description' => 'Build suchnsuch from site sonso',
            'state' => 'pending',
            'target_url' => "{$base_url}/buildSummary.php?buildid=1010",
        ];

        $json = [
            'context' => $options['context'],
            'description' => $options['description'],
            'state' => $options['state'],
            'target_url' => $options['target_url']
        ];

        /** @var ClientInterface|PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(ClientInterface::class)
            ->getMockForAbstractClass();

        $client_options = [
            'headers' => ['Authorization' => "token {$token}"],
            'body' => json_encode($json),
        ];

        $uri = GitHub::BASE_URI . "/repos/{$owner}/{$repo}/statuses/{$hash}";

        $client->expects($this->once())
            ->method('request')
            ->with('POST', $uri, $client_options);

        $sut->setStatus($client, $options);
    }
}
