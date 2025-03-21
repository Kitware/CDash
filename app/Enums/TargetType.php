<?php

namespace App\Enums;

/**
 * See: https://cmake.org/cmake/help/latest/prop_tgt/TYPE.html
 *
 * We also include an "UNKNOWN" value in case CMake adds a new type before CDash does.
 */
enum TargetType: string
{
    case UNKNOWN = 'UNKNOWN';
    case STATIC_LIBRARY = 'STATIC_LIBRARY';
    case MODULE_LIBRARY = 'MODULE_LIBRARY';
    case SHARED_LIBRARY = 'SHARED_LIBRARY';
    case OBJECT_LIBRARY = 'OBJECT_LIBRARY';
    case INTERFACE_LIBRARY = 'INTERFACE_LIBRARY';
    case EXECUTABLE = 'EXECUTABLE';
}
