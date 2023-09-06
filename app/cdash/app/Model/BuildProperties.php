<?php
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
namespace CDash\Model;

use App\Models\BuildProperties as EloquentBuildProperties;

/** BuildProperties class */
class BuildProperties
{
    public Build $Build;
    /** @var array<mixed> */
    public array $Properties = [];
    private bool $Filled = false;

    public function __construct(Build $build)
    {
        $this->Build = $build;
    }

    /** Return true if this build already has properties. */
    public function Exists(): bool
    {
        return EloquentBuildProperties::where('buildid', (int) $this->Build->Id)->exists();
    }

    /** Save these build properties to the database,
        overwriting any existing content. */
    public function Save(): bool
    {
        // Delete any previously existing properties for this build.
        if ($this->Exists()) {
            $this->Delete();
        }

        $properties_str = json_encode($this->Properties);
        if ($properties_str === false) {
            abort(500, 'Failed to encode JSON: ' . json_last_error_msg());
        }

        return EloquentBuildProperties::create([
            'buildid' => (int) $this->Build->Id,
            'properties' => $properties_str,
        ]) !== null;
    }

    /** Delete this record from the database. */
    public function Delete(): bool
    {
        if (!$this->Exists()) {
            abort(500, 'No properties exist for this build');
        }

        return (bool) EloquentBuildProperties::where('buildid', (int) $this->Build->Id)->delete();
    }

    /** Retrieve properties for a given build. */
    public function Fill(): void
    {
        $model = EloquentBuildProperties::find((int) $this->Build->Id);
        if ($model === null) {
            return;
        }

        $properties = json_decode($model->properties, true);
        if (is_array($properties)) {
            $this->Properties = $properties;
            $this->Filled = true;
        }
    }
}
