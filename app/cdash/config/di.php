<?php

return [
    'CDash\Controller\Auth\Session' => \DI\create()
        ->constructor(\DI\get('CDash\System'), \CDash\Config::getInstance()),
];
