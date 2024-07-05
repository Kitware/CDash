<?php

use CDash\Model\Build;

abstract class AbstractSubmissionHandler
{
    /** @var Build $Build */
    protected $Build;

    /**
     * TODO: This was copied here from legacy code.  In the future, we should re-evaluate
     *       whether this should be in the abstract handler or not, given that many handlers
     *       override it.
     *
     * @return Build[]
     */
    public function getBuilds(): array
    {
        return [$this->Build];
    }
}
