<?php
namespace CDash\archive\Messaging\Filter;

use ActionableBuildInterface;
interface FilterInterface
{
    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function meetsCriteria(ActionableBuildInterface $handler = null);
}
