<?php
namespace CDash\Collection;

use CDash\Model\Label;

class LabelCollection extends Collection
{
    /**
     * @param Label $label
     * @return $this
     */
    public function add(Label $label)
    {
        parent::addItem($label, $label->Text);
        return $this;
    }
}
