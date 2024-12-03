<?php

namespace App\Enums;

enum BuildCommandType: int
{
    case COMPILE_COMMAND = 0;
    case LINK_COMMAND = 1;
    case CMAKE_BUILD_COMMAND = 2;
    case CUSTOM_COMMAND = 3;
}
