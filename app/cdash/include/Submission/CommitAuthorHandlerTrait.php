<?php

namespace CDash\Submission;

use CDash\Model\Build;

trait CommitAuthorHandlerTrait
{
    /**
     * @return array
     */
    public function GetCommitAuthors()
    {
        $authors = [];
        /** @var Build $build */
        foreach ($this->Builds as $build) {
            $build_authors = $build->GetCommitAuthors();
            $authors = array_merge($authors, $build_authors);
        }
        return array_unique($authors);
    }
}
