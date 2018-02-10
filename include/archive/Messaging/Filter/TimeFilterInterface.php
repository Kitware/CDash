<?php
/**
 * Created by PhpStorm.
 * User: bryonbean
 * Date: 12/11/17
 * Time: 4:57 PM
 */

namespace CDash\archive\Messaging\Filter;


interface TimeFilterInterface
{
    public function is();

    public function isNot();

    public function isBefore();

    public function isAfter();
}
