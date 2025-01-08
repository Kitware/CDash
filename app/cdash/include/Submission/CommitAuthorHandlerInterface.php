<?php

namespace CDash\Submission;

interface CommitAuthorHandlerInterface extends \ActionableBuildInterface
{
    /**
     * Returns an array of email addresses from those committers that are not CDash users
     *
     * @return array
     */
    public function GetCommitAuthors();
}
