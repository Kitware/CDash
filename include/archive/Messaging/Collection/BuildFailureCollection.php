<?php
namespace CDash\archive\Messaging\Collection;

class BuildFailureCollection extends Collection
{
    public function addBuildFailure(\BuildFailure $item, $label = null)
    {
        parent::add($item, $label);
    }

    public function add($item, $label = null)
    {
        $this->addBuildFailure($item, $label);
    }
}
