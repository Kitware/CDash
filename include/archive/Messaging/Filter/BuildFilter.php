<?php
namespace CDash\archive\Messaging\Filter;

use ActionableBuildInterface;

class BuildFilter implements FilterInterface
{

    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function meetsCriteria(ActionableBuildInterface $handler = null)
    {

    }
}
