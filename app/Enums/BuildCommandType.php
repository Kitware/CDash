<?php

namespace App\Enums;

enum BuildCommandType: string
{
    case COMPILE = 'COMPILE';
    case LINK = 'LINK';
    case CUSTOM = 'CUSTOM';
    case CMAKE_BUILD = 'CMAKE_BUILD';
    case CMAKE_INSTALL = 'CMAKE_INSTALL';
    case INSTALL = 'INSTALL';
}
