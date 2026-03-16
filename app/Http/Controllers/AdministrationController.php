<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

final class AdministrationController extends AbstractController
{
    public function __invoke(): View
    {
        return $this->vue('administration-page', 'Administration');
    }
}
