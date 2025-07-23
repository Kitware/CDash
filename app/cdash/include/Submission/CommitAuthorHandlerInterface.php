<?php

namespace CDash\Submission;

use App\Http\Submission\Handlers\ActionableBuildInterface;

interface CommitAuthorHandlerInterface extends ActionableBuildInterface
{
    /**
     * Returns an array of email addresses from those committers that are not CDash users
     *
     * @return array
     */
    public function GetCommitAuthors();
}
