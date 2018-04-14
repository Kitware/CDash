<?php

return [
    'CDash\Controller\Auth\Session' => \DI\object()
        ->constructor(\DI\get('CDash\System'), \CDash\Config::getInstance()),
];
