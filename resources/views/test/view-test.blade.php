@php
    use App\Http\Controllers\AbstractController;

    $js_version = AbstractController::getJsVersion();
    $xsl = false;
    $controller = 'ViewTestController';
    $content = file_get_contents(public_path() . '/build/views/viewTest.html');
@endphp

@extends('cdash')
