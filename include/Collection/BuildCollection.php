<?php
namespace CDash\Collection;

use Build;

class BuildCollection extends Collection
{
    public function add(Build $build)
    {
        $name = $build->SubProjectName ? $build->SubProjectName : $build->Name;
        parent::addItem($build, $name);
    }
}
