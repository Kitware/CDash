<?php
namespace CDash\archive\Messaging\Filter;

use ActionableBuildInterface;

class BuildLabelFilter extends Filter implements FilterInterface, StringFilterInterface
{

    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function meetsCriteria(ActionableBuildInterface $handler = null)
    {
        $labels = [];
        foreach ($handler->getActionableBuilds() as $build) {
            $labels = array_unique($labels, $build->GetLabels());
        }

        return in_array($this->getValue(), $labels);
    }
}
