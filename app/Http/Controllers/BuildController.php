<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BuildController extends Controller
{
    public function summary()
    {
        return view('build.summary')->with('title', 'Build Summary');
    }
}
