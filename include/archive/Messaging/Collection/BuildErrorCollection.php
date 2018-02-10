<?php
namespace CDash\archive\Messaging\Collection;

class BuildErrorCollection extends Collection
{
    public function addBuildError(\BuildError $item, $label)
    {
        parent::add($item, $label);
    }

    public function add($item, $label = null)
    {
        $this->addBuildError($item, $label);
    }
}
