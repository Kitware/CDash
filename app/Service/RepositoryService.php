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

namespace CDash\Service;

use CDash\Lib\Repository\RepositoryInterface;
use CDash\Model\Build;
use GuzzleHttp\ClientInterface;

/**
 * Class RepositoryService
 * @package CDash\Service
 */
class RepositoryService
{
    /** @var RepositoryInterface $repository */
    protected $repository;

    /** @var ClientInterface $client */
    protected $client;

    /**
     * RepositoryService constructor.
     * @param RepositoryInterface $repository
     * @param ClientInterface $client
     */
    public function __construct(RepositoryInterface $repository, ClientInterface $client)
    {
        $this->repository = $repository;
        $this->client = $client;
    }

    /**
     * @param Build $build
     */
    public function setStatusOnStart(Build $build)
    {
        $options = [
            'context' => 'CDash by Kitware',
            'description' => "Build: {$build->Name}",
            'commit_hash' => $build->GetBuildUpdate()->Revision,
            'state' => 'pending',
            'target_url' => $build->GetBuildSummaryUrl(),
        ];

        $this->repository->setStatus($this->client, $options);
    }

    public function setStatusOnComplete(Build $build)
    {
        $options = [
            'context' => 'CDash by Kitware',
            'commit_hash' => $build->GetBuildUpdate()->Revision,
            'state' => 'error'
        ];

        $num_configure_errors = $build->GetNumberOfConfigureErrors();
        $num_build_errors = $build->GetNumberOfErrors();
        $num_failed_tests = $build->GetNumberOfFailedTests();

        if ($num_configure_errors > 0) {
            $options['description'] = "$num_configure_errors configure errors";
            $options['target_url'] = $build->GetBuildSummaryUrl();
        } elseif ($build->GetNumberOfErrors() > 0) {
            $options['description'] = "$num_build_errors build errors";
            $options['target_url'] = $build->GetBuildErrorUrl();
        } elseif ($build->GetNumberOfFailedTests() > 0) {
            $options['description'] = "$num_failed_tests failed tests";
            $options['target_url'] = $build->GetTestUrl();
        } else {
            $options['description'] = "Build: {$build->Name}";
            $options['state'] = 'success';
            $options['target_url'] = $build->GetBuildSummaryUrl();
        }

        $this->repository->setStatus($this->client, $options);
    }
}
