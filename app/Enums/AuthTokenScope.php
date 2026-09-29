<?php

declare(strict_types=1);

namespace App\Enums;

use GraphQL\Type\Definition\Description;

enum AuthTokenScope: string
{
    #[Description('Provides full API access.')]
    case FULL_ACCESS = 'FULL_ACCESS';

    #[Description('Only valid for authenticated submissions.  If associated with a project, only valid for that project.')]
    case SUBMIT_ONLY = 'SUBMIT_ONLY';
}
