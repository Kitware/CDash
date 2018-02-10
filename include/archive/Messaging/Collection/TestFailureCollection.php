<?php
namespace CDash\archive\Messaging\Collection;

class TestFailureCollection extends Collection
{
    public function addTestFailure(\Test $item, $label = null)
    {
        parent::add($item, $label);
    }

    public function add($item, $label = null)
    {
        $this->addTestFailure($item, $label);
    }
}
