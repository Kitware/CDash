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

/**
 * Class RepositoryService
 * @package CDash\Service
 */
class RepositoryService
{
    /** @var RepositoryInterface $repository */
    protected $repository;

    /**
     * RepositoryService constructor.
     * @param RepositoryInterface $repository
     */
    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    protected function setStatus($context, $description, $revision, $state,
        $target_url)
    {
        if (strlen($revision) === 0 || strlen($context) === 0) {
            return;
        }

        $options = [
            'context'     => "ci/CDash/$context",
            'description' => $description,
            'commit_hash' => $revision,
            'state'       => $state,
            'target_url'  => $target_url,
        ];
        $this->repository->setStatus($options);
    }

    /**
     * @param Build $build
     */
    public function setStatusOnStart(Build $build, $context)
    {
        $this->setStatus($context, "Build: {$build->Name}",
            $build->GetBuildUpdate()->Revision, 'pending',
            $build->GetBuildSummaryUrl());
    }

    public function setStatusOnComplete(Build $build, $context)
    {
        $revision = $build->GetBuildUpdate()->Revision;
        $state = 'error';

        $num_configure_errors = $build->GetNumberOfConfigureErrors();
        $num_build_errors = $build->GetNumberOfErrors();
        $num_failed_tests = $build->GetNumberOfFailedTests();

        if ($num_configure_errors > 0) {
            $description = "$num_configure_errors configure errors";
            $target_url = $build->GetBuildSummaryUrl();
        } elseif ($build->GetNumberOfErrors() > 0) {
            $description = "$num_build_errors build errors";
            $target_url = $build->GetBuildErrorUrl();
        } elseif ($build->GetNumberOfFailedTests() > 0) {
            $description = "$num_failed_tests failed tests";
            $target_url = $build->GetTestUrl();
        } else {
            $description = "Build: {$build->Name}";
            $state = 'success';
            $target_url = $build->GetBuildSummaryUrl();
        }

        $this->setStatus($context, $description, $revision, $state, $target_url);
    }
}
