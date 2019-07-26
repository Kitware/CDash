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
            $authors = array_merge($authors, array_map(function ($email) {
                return $email;
            }, $build->GetCommitAuthors()));
        }
        return array_unique($authors);
    }
}
