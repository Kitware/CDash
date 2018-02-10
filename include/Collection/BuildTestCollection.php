<?php

namespace CDash\Collection;

use BuildTest;

class BuildTestCollection extends Collection
{
    public function add(BuildTest $buildTest)
    {
        $name = $buildTest->GetTestName();
        $key = empty($name) ? $this->count() : $name;
        parent::addItem($buildTest, $key);
    }
}
