<?php

namespace App\Http\Submission\Handlers;

/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\SubProject;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SubProjectDirectoriesHandler extends AbstractSubmissionHandler
{
    private $SubProjectOrder;

    public function __construct(Build|Project $init)
    {
        parent::__construct($init);

        $this->SubProjectOrder = [];
    }

    /**
     * Parse a text file containing a Bazel package per line.
     **/
    public function Parse($filename)
    {
        $open_list = [];
        $fp = Storage::readStream($filename);
        if ($fp === null) {
            Log::error("Could not open $filename for parsing", [
                'function' => 'SubProjectDirectoriesHandler::Parse',
            ]);
            return false;
        }
        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $open_list[] = $line;
        }

        // Record the order that the packages were listed in.
        $order = 1;
        foreach ($open_list as $package_path) {
            $this->SubProjectOrder[$package_path] = $order;
            $order++;
        }

        // Determine unique names for each SubProject path.
        $closed_list = [];
        $closed_list = $this->ParseSubProjects($open_list, $closed_list, 0);

        // Save all of our SubProjects.
        foreach ($closed_list as $name => $path) {
            $subproject = new SubProject();
            $subproject->SetProjectId($this->GetProject()->Id);
            $subproject->SetName($name);
            $subproject->SetPath($path);
            $subproject->SetPosition($this->SubProjectOrder[$path]);
            $subproject->Save();
        }
    }

    /**
     * Recursive function to determine unique SubProject names from package
     * paths.  By default a SubProject's name will be the leaf directory of its
     * package path.  In the case of collisions, the leaf's parent directory
     * will be included in the SubProject name.  This process repeats
     * recursively until each SubProject has a unique name.  Results are stored
     * in $closed_list, whose keys are the unique SubProject names and whose
     * values are the original package paths.  $level is an integer defining
     * how many levels of parent directories we need to include in our
     * SubProject names (initially zero).
     *
     * @param array $open_list
     * @param array $closed_list
     * @param int $level
     *
     **/
    public function ParseSubProjects($open_list, $closed_list, $level)
    {
        if (empty($open_list)) {
            return $closed_list;
        }

        $collisions = [];
        foreach ($open_list as $package_path) {
            if (empty($package_path)) {
                continue;
            }

            // Generate a name for this package from its path.
            // By default this is the leaf directory of the package, but in the
            // case of collisions we include its parent directory in the name
            // recursively until the collision is resolved.
            $package_name = basename($package_path);
            $parent_dir = dirname($package_path);
            for ($i = 0; $i < $level; $i++) {
                $parent_basename = basename($parent_dir);
                // If the parent directory is '.' don't include it in the name
                // of the package.
                if ($parent_basename !== '.') {
                    $package_name = $parent_basename . "_$package_name";
                }
                $parent_dir = dirname($parent_dir);
            }

            if (array_key_exists($package_name, $closed_list)) {
                $collisions[] = $package_path;
                $existing_package = $closed_list[$package_name];
                if (!in_array($existing_package, $collisions)) {
                    $collisions[] = $existing_package;
                }
            } else {
                $closed_list[$package_name] = $package_path;
            }
        }

        // Remove collisions from closed list.
        $closed_list = array_diff($closed_list, $collisions);

        return $this->ParseSubProjects($collisions, $closed_list, $level + 1);
    }
}
