<?php
namespace CDash\Collection;

use Label;

class LabelCollection extends Collection
{
    public function add(Label $label)
    {
        parent::addItem($label, $label->Text);
    }
}
