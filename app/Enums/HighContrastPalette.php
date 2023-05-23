<?php

declare(strict_types=1);

namespace App\Enums;

enum HighContrastPalette: string
{
    case Success = '#91bfdb';
    case Warning = '#ffffbf';
    case Failure = '#fc8d59';
}
