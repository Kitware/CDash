<?php
/**
 * Created by PhpStorm.
 * User: bryonbean
 * Date: 12/11/17
 * Time: 4:59 PM
 */

namespace CDash\archive\Messaging\Filter;


interface StringFilterInterface
{
    public function contains();

    public function doesNotContain();

    public function is();

    public function isNot();

    public function startsWith();

    public function endsWith();
}
